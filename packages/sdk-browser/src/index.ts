import { OfflineBuffer } from './buffer.js';
import { ContextManager } from './context.js';
import { generateId, getOrCreateAnonymousId, getOrCreateSessionId } from './identity.js';
import { Transport } from './transport.js';
import type {
  DiagnosticState,
  InteractionMetadata,
  LogMetadata,
  Metadata,
  MetricMetadata,
  NavigationMetadata,
  RemoteConfig,
  SpanMetadata,
  TelemetryEvent,
  TelemetryInitOptions,
  ViewMetadata,
} from './types.js';

const DEFAULT_REMOTE_CONFIG: RemoteConfig = {
  version: 1,
  config: {
    heartbeat_interval_seconds: 60,
    batch_size: 0,
    automatic_navigation_tracking: true,
    signal_families: {
      events: true,
      metrics: true,
      logs: true,
      traces: true,
    },
    metadata_policy: {
      mode: 'denylist',
      forbidden_keys: [
        'password',
        'token',
        'secret',
        'api_key',
        'authorization',
        'body',
        'request_body',
        'response_body',
        'note',
        'prompt',
        'search_text',
      ],
    },
  },
};

export type {
  DiagnosticState,
  InteractionMetadata,
  LogMetadata,
  Metadata,
  MetricMetadata,
  NavigationMetadata,
  RemoteConfig,
  SpanMetadata,
  TelemetryEvent,
  TelemetryInitOptions,
  ViewMetadata,
};

export class TelemetryClient {
  private initialized = false;
  private options: TelemetryInitOptions | null = null;
  private transport: Transport | null = null;
  private buffer: OfflineBuffer | null = null;
  private context = new ContextManager();
  private remoteConfig: RemoteConfig = DEFAULT_REMOTE_CONFIG;

  private sessionId: string | null = null;
  private anonymousId: string | null = null;
  private userId: string | null = null;
  private correlationId: string | null = null;
  private sequenceNumber = 0;
  private currentViewId: string | null = null;
  private currentViewStartedAt: number | null = null;
  private activeVisibleMs = 0;
  private lastActiveTickAt: number | null = null;
  private isVisible = true;
  private heartbeatTimer: ReturnType<typeof setInterval> | null = null;
  private flushTimer: ReturnType<typeof setTimeout> | null = null;
  private pendingBatch: TelemetryEvent[] = [];
  private retryTimer: ReturnType<typeof setTimeout> | null = null;
  private lastDeliveryError: string | null = null;
  private lastRemoteConfigFetch: string | null = null;
  private lifecycleBound = false;
  private navigationBound = false;
  private linkedAnonymousId: string | null = null;

  async init(options: TelemetryInitOptions): Promise<void> {
    if (this.initialized) {
      return;
    }

    this.options = options;
    this.transport = new Transport({
      endpoint: options.endpoint,
      ingestionToken: options.ingestionToken,
    });
    this.buffer = new OfflineBuffer(options.offlineBufferMax ?? 500);

    this.anonymousId = getOrCreateAnonymousId();
    this.sessionId = getOrCreateSessionId();
    this.userId = options.userId ?? null;
    this.correlationId = options.correlationId ?? null;

    if (options.applicationContext) {
      this.context.setApplicationContext(options.applicationContext);
    }

    if (options.productKey) {
      this.context.mergeApplicationContext({ product_key: options.productKey });
    }

    this.context.setSessionContext({
      session_id: this.sessionId,
      anonymous_id: this.anonymousId,
    });

    await this.loadRemoteConfig();

    this.enqueueEvent('navigation.session_started', {
      user_agent: typeof navigator !== 'undefined' ? navigator.userAgent : undefined,
    });

    if (options.autoLifecycle !== false) {
      this.bindLifecycle();
    }

    if (this.remoteConfig.config.automatic_navigation_tracking) {
      this.bindNavigationTracking();
      this.captureCurrentRoute();
    }

    this.capturePagePerformance();
    this.startHeartbeat();
    this.tickActiveTime();
    this.initialized = true;

    void this.flushOfflineBuffer();
  }

  setUserId(userId: string | null): void {
    const previousAnonymous = this.anonymousId;
    this.userId = userId;

    if (userId && previousAnonymous && !this.linkedAnonymousId) {
      this.linkedAnonymousId = previousAnonymous;
      this.enqueueEvent('identity.linked', {
        anonymous_id: previousAnonymous,
        user_id: userId,
      });
    }

    this.context.mergeSessionContext({ user_id: userId ?? undefined });
  }

  setCorrelationId(correlationId: string | null): void {
    this.correlationId = correlationId;
  }

  setApplicationContext(metadata: Metadata): void {
    this.context.setApplicationContext(metadata);
  }

  mergeApplicationContext(metadata: Metadata): void {
    this.context.mergeApplicationContext(metadata);
  }

  setSessionContext(metadata: Metadata): void {
    this.context.setSessionContext(metadata);
  }

  setViewContext(metadata: Metadata): void {
    this.context.setViewContext(metadata);
  }

  track(eventType: string, metadata?: Metadata): void {
    this.enqueueEvent(eventType, metadata);
  }

  trackNavigation(metadata?: NavigationMetadata): void {
    this.captureCurrentRoute(metadata);
  }

  trackViewStart(metadata?: ViewMetadata): void {
    if (this.currentViewId) {
      this.trackViewEnd();
    }

    this.currentViewId = generateId();
    this.currentViewStartedAt = Date.now();
    this.activeVisibleMs = 0;
    this.lastActiveTickAt = this.isVisible ? Date.now() : null;

    this.context.setViewContext({
      view_instance_id: this.currentViewId,
      ...metadata,
    });

    this.enqueueEvent('navigation.view_started', {
      view_instance_id: this.currentViewId,
      view_name: metadata?.view_name,
      route: metadata?.route,
      path: metadata?.path,
    });
  }

  trackViewEnd(metadata?: ViewMetadata): void {
    if (!this.currentViewId) {
      return;
    }

    this.flushActiveTime();
    const wallClockMs =
      this.currentViewStartedAt !== null ? Date.now() - this.currentViewStartedAt : undefined;

    this.enqueueEvent('navigation.view_ended', {
      view_instance_id: this.currentViewId,
      view_name: metadata?.view_name,
      route: metadata?.route,
      path: metadata?.path,
      wall_clock_ms: wallClockMs,
      active_duration_ms: this.activeVisibleMs,
      ...metadata,
    });

    this.currentViewId = null;
    this.currentViewStartedAt = null;
    this.activeVisibleMs = 0;
    this.lastActiveTickAt = null;
    this.context.clearViewContext();
  }

  trackInteraction(action: string, metadata?: InteractionMetadata): void {
    this.enqueueEvent(`interaction.${action}`, {
      action,
      ...metadata,
    });
  }

  trackMetric(name: string, value: number, type: MetricMetadata['type'] = 'gauge', dimensions?: Metadata): void {
    if (!this.remoteConfig.config.signal_families.metrics || !this.transport) {
      return;
    }

    void this.transport.sendMetrics([
      {
        id: generateId(),
        name,
        type,
        value,
        occurred_at: new Date().toISOString(),
        user_id: this.userId ?? undefined,
        anonymous_id: this.anonymousId ?? undefined,
        session_id: this.sessionId ?? undefined,
        correlation_id: this.correlationId ?? undefined,
        dimensions: this.filterMetadata(this.context.resolve(dimensions)),
      },
    ]).catch((error) => this.handleDeliveryError(error));
  }

  trackLog(severity: string, message: string, metadata?: LogMetadata): void {
    if (!this.remoteConfig.config.signal_families.logs || !this.transport) {
      return;
    }

    void this.transport.sendLogs([
      {
        id: generateId(),
        severity,
        message,
        service: metadata?.service,
        message_code: metadata?.message_code,
        occurred_at: new Date().toISOString(),
        user_id: this.userId ?? undefined,
        session_id: this.sessionId ?? undefined,
        correlation_id: this.correlationId ?? undefined,
        metadata: this.filterMetadata(this.context.resolve(metadata)),
      },
    ]).catch((error) => this.handleDeliveryError(error));
  }

  trackSpan(metadata: SpanMetadata): void {
    if (!this.remoteConfig.config.signal_families.traces || !this.transport) {
      return;
    }

    void this.transport.sendTraces([
      {
        trace_id: metadata.trace_id,
        span_id: metadata.span_id,
        parent_span_id: metadata.parent_span_id,
        name: metadata.name,
        started_at: metadata.started_at ?? new Date().toISOString(),
        ended_at: metadata.ended_at,
        duration_ms: metadata.duration_ms,
        status: metadata.status,
        user_id: this.userId ?? undefined,
        session_id: this.sessionId ?? undefined,
        correlation_id: this.correlationId ?? metadata.correlation_id ?? this.correlationId ?? undefined,
        attributes: this.filterMetadata(this.context.resolve(metadata.attributes)),
      },
    ]).catch((error) => this.handleDeliveryError(error));
  }

  async shutdown(): Promise<void> {
    if (this.currentViewId) {
      this.trackViewEnd({ abrupt: true });
    }

    this.enqueueEvent('navigation.session_ended');
    this.stopHeartbeat();
    this.unbindLifecycle();
    this.unbindNavigationTracking();

    if (this.flushTimer) {
      clearTimeout(this.flushTimer);
      this.flushTimer = null;
    }

    await this.deliverBatch(this.pendingBatch.splice(0));
    this.initialized = false;
  }

  async getDiagnosticState(): Promise<DiagnosticState> {
    const bufferedCount = this.buffer ? await this.buffer.count() : 0;

    return {
      initialized: this.initialized,
      online: typeof navigator !== 'undefined' ? navigator.onLine : true,
      bufferedCount,
      lastDeliveryError: this.lastDeliveryError,
      lastRemoteConfigFetch: this.lastRemoteConfigFetch,
      sessionId: this.sessionId,
      anonymousId: this.anonymousId,
    };
  }

  private captureCurrentRoute(metadata?: NavigationMetadata): void {
    const route = metadata?.route ?? (typeof location !== 'undefined' ? location.pathname : undefined);
    const path = metadata?.path ?? (typeof location !== 'undefined' ? location.pathname + location.search : undefined);

    this.trackViewStart({
      ...metadata,
      view_name: (metadata?.view_name as string | undefined) ?? route,
      route,
      path,
      title: metadata?.title ?? (typeof document !== 'undefined' ? document.title : undefined),
      referrer: metadata?.referrer ?? (typeof document !== 'undefined' ? document.referrer : undefined),
    });
  }

  private capturePagePerformance(): void {
    if (typeof performance === 'undefined') {
      return;
    }

    const nav = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming | undefined;
    if (!nav) {
      return;
    }

    this.enqueueEvent('performance.page_load', {
      dom_content_loaded_ms: Math.round(nav.domContentLoadedEventEnd),
      load_event_ms: Math.round(nav.loadEventEnd),
      ttfb_ms: Math.round(nav.responseStart - nav.requestStart),
    });
  }

  private bindNavigationTracking(): void {
    if (this.navigationBound || typeof window === 'undefined') {
      return;
    }

    this.navigationBound = true;
    window.addEventListener('popstate', this.onRouteChange);
    window.addEventListener('hashchange', this.onRouteChange);

    const originalPushState = history.pushState.bind(history);
    const originalReplaceState = history.replaceState.bind(history);
    const client = this;

    history.pushState = function (...args) {
      originalPushState(...args);
      client.onRouteChange();
    };

    history.replaceState = function (...args) {
      originalReplaceState(...args);
      client.onRouteChange();
    };
  }

  private unbindNavigationTracking(): void {
    if (!this.navigationBound || typeof window === 'undefined') {
      return;
    }

    window.removeEventListener('popstate', this.onRouteChange);
    window.removeEventListener('hashchange', this.onRouteChange);
    this.navigationBound = false;
  }

  private readonly onRouteChange = (): void => {
    if (!this.remoteConfig.config.automatic_navigation_tracking) {
      return;
    }

    this.captureCurrentRoute();
  };

  private enqueueEvent(eventType: string, metadata?: Metadata): void {
    if (!this.initialized || !this.options) {
      this.diagnostic('track called before init', { eventType });
      return;
    }

    if (!this.remoteConfig.config.signal_families.events) {
      return;
    }

    const event = this.buildEvent(eventType, metadata);
    const batchSize = this.effectiveBatchSize();

    if (batchSize <= 0) {
      void this.deliverOrBuffer([event]);
      return;
    }

    this.pendingBatch.push(event);

    if (this.pendingBatch.length >= batchSize) {
      void this.deliverBatch(this.pendingBatch.splice(0, batchSize));
      return;
    }

    if (!this.flushTimer) {
      this.flushTimer = setTimeout(() => {
        this.flushTimer = null;
        void this.deliverBatch(this.pendingBatch.splice(0));
      }, 2000);
    }
  }

  private buildEvent(eventType: string, metadata?: Metadata): TelemetryEvent {
    this.sequenceNumber += 1;

    const category = eventType.includes('.') ? eventType.split('.')[0] : undefined;
    const resolvedMetadata = this.filterMetadata(this.context.resolve(metadata));

    return {
      event_id: generateId(),
      event_type: eventType,
      occurred_at: new Date().toISOString(),
      category,
      user_id: this.userId ?? undefined,
      anonymous_id: this.anonymousId ?? undefined,
      session_id: this.sessionId ?? undefined,
      view_instance_id: this.currentViewId ?? undefined,
      sequence_number: this.sequenceNumber,
      correlation_id: this.correlationId ?? undefined,
      metadata: Object.keys(resolvedMetadata).length > 0 ? resolvedMetadata : undefined,
    };
  }

  private filterMetadata(metadata: Metadata): Metadata {
    const policy = this.remoteConfig.config.metadata_policy;
    const filtered: Metadata = {};

    for (const [key, value] of Object.entries(metadata)) {
      if (policy.mode === 'denylist') {
        const forbidden = policy.forbidden_keys ?? [];
        if (forbidden.some((f) => key.toLowerCase().includes(f.toLowerCase()))) {
          continue;
        }
      } else if (policy.mode === 'allowlist') {
        const allowed = policy.allowed_keys ?? [];
        if (!allowed.includes(key)) {
          continue;
        }
      }

      filtered[key] = value;
    }

    return filtered;
  }

  private effectiveBatchSize(): number {
    if (this.options?.batchSize !== undefined) {
      return this.options.batchSize;
    }

    return this.remoteConfig.config.batch_size;
  }

  private effectiveHeartbeatSeconds(): number {
    if (this.options?.heartbeatIntervalSeconds !== undefined) {
      return this.options.heartbeatIntervalSeconds;
    }

    return this.remoteConfig.config.heartbeat_interval_seconds;
  }

  private tickActiveTime(): void {
    if (this.isVisible && this.lastActiveTickAt !== null) {
      this.activeVisibleMs += Date.now() - this.lastActiveTickAt;
    }

    this.lastActiveTickAt = this.isVisible ? Date.now() : null;
  }

  private flushActiveTime(): void {
    this.tickActiveTime();
  }

  private async deliverOrBuffer(events: TelemetryEvent[]): Promise<void> {
    try {
      await this.deliverBatch(events);
    } catch (error) {
      await this.bufferEvents(events);
      this.scheduleRetry();
    }
  }

  private async deliverBatch(events: TelemetryEvent[]): Promise<void> {
    if (!this.transport || events.length === 0) {
      return;
    }

    if (typeof navigator !== 'undefined' && !navigator.onLine) {
      await this.bufferEvents(events);
      this.scheduleRetry();
      return;
    }

    try {
      await this.transport.sendEvents(events);
      this.lastDeliveryError = null;
    } catch (error) {
      this.handleDeliveryError(error);
      await this.bufferEvents(events);
      throw error;
    }
  }

  private handleDeliveryError(error: unknown): void {
    this.lastDeliveryError = error instanceof Error ? error.message : String(error);
    this.diagnostic('delivery failed', error);
  }

  private async bufferEvents(events: TelemetryEvent[]): Promise<void> {
    if (!this.buffer) {
      return;
    }

    for (const event of events) {
      await this.buffer.enqueue(event);
    }
  }

  private async flushOfflineBuffer(): Promise<void> {
    if (!this.buffer || !this.transport) {
      return;
    }

    const batchSize = Math.max(this.effectiveBatchSize(), 1);

    while (true) {
      const events = await this.buffer.dequeueBatch(batchSize);
      if (events.length === 0) {
        break;
      }

      try {
        await this.transport.sendEvents(events);
        await this.buffer.remove(events.map((e) => e.event_id));
        this.lastDeliveryError = null;
      } catch (error) {
        for (const event of events) {
          await this.buffer.enqueue(event);
        }

        this.handleDeliveryError(error);
        this.scheduleRetry();
        break;
      }
    }
  }

  private scheduleRetry(): void {
    if (this.retryTimer) {
      return;
    }

    this.retryTimer = setTimeout(() => {
      this.retryTimer = null;
      void this.flushOfflineBuffer();
    }, 5000);
  }

  private async loadRemoteConfig(): Promise<void> {
    if (!this.transport) {
      return;
    }

    try {
      const data = await this.transport.fetchRemoteConfig();
      this.remoteConfig = this.parseRemoteConfig(data);
      this.lastRemoteConfigFetch = new Date().toISOString();
      this.restartHeartbeat();
    } catch (error) {
      this.diagnostic('remote config unavailable, using defaults', error);
      this.remoteConfig = DEFAULT_REMOTE_CONFIG;
    }
  }

  private parseRemoteConfig(data: Record<string, unknown>): RemoteConfig {
    const config = (data.config as RemoteConfig['config']) ?? DEFAULT_REMOTE_CONFIG.config;

    return {
      version: typeof data.version === 'number' ? data.version : DEFAULT_REMOTE_CONFIG.version,
      config: {
        heartbeat_interval_seconds:
          config.heartbeat_interval_seconds ?? DEFAULT_REMOTE_CONFIG.config.heartbeat_interval_seconds,
        batch_size: config.batch_size ?? DEFAULT_REMOTE_CONFIG.config.batch_size,
        automatic_navigation_tracking:
          config.automatic_navigation_tracking ??
          DEFAULT_REMOTE_CONFIG.config.automatic_navigation_tracking,
        signal_families: {
          ...DEFAULT_REMOTE_CONFIG.config.signal_families,
          ...(config.signal_families ?? {}),
        },
        metadata_policy: {
          ...DEFAULT_REMOTE_CONFIG.config.metadata_policy,
          ...(config.metadata_policy ?? {}),
        },
      },
    };
  }

  private bindLifecycle(): void {
    if (this.lifecycleBound || typeof document === 'undefined') {
      return;
    }

    this.lifecycleBound = true;
    this.isVisible = document.visibilityState !== 'hidden';

    document.addEventListener('visibilitychange', this.onVisibilityChange);
    window.addEventListener('online', this.onOnline);
    window.addEventListener('pagehide', this.onPageHide);
  }

  private unbindLifecycle(): void {
    if (!this.lifecycleBound || typeof document === 'undefined') {
      return;
    }

    document.removeEventListener('visibilitychange', this.onVisibilityChange);
    window.removeEventListener('online', this.onOnline);
    window.removeEventListener('pagehide', this.onPageHide);
    this.lifecycleBound = false;
  }

  private readonly onVisibilityChange = (): void => {
    const visible = document.visibilityState !== 'hidden';

    if (visible === this.isVisible) {
      return;
    }

    this.flushActiveTime();
    this.isVisible = visible;

    if (visible) {
      this.enqueueEvent('navigation.visibility_visible');
      this.startHeartbeat();
    } else {
      this.enqueueEvent('navigation.visibility_hidden');
      this.stopHeartbeat();
    }
  };

  private readonly onOnline = (): void => {
    void this.flushOfflineBuffer();
  };

  private readonly onPageHide = (): void => {
    if (this.currentViewId) {
      this.flushActiveTime();
      const event = this.buildEvent('navigation.view_ended', {
        view_instance_id: this.currentViewId,
        abrupt: true,
        active_duration_ms: this.activeVisibleMs,
        wall_clock_ms:
          this.currentViewStartedAt !== null ? Date.now() - this.currentViewStartedAt : undefined,
      });

      void this.deliverOrBuffer([event]);
    }
  };

  private startHeartbeat(): void {
    if (this.heartbeatTimer || !this.isVisible) {
      return;
    }

    const intervalMs = this.effectiveHeartbeatSeconds() * 1000;

    this.heartbeatTimer = setInterval(() => {
      if (!this.isVisible) {
        return;
      }

      this.tickActiveTime();
      this.enqueueEvent('navigation.heartbeat', {
        view_instance_id: this.currentViewId ?? undefined,
      });
    }, intervalMs);
  }

  private stopHeartbeat(): void {
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer);
      this.heartbeatTimer = null;
    }
  }

  private restartHeartbeat(): void {
    this.stopHeartbeat();
    this.startHeartbeat();
  }

  private diagnostic(message: string, detail?: unknown): void {
    this.options?.onDiagnostic?.(message, detail);
  }
}
