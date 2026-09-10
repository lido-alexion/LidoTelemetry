import { OfflineBuffer } from './buffer.js';
import { ContextManager } from './context.js';
import { generateId, getOrCreateAnonymousId, getOrCreateSessionId } from './identity.js';
import { Transport } from './transport.js';
import type {
  DiagnosticState,
  InteractionMetadata,
  Metadata,
  NavigationMetadata,
  RemoteConfig,
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
      ],
    },
  },
};

export type {
  DiagnosticState,
  InteractionMetadata,
  Metadata,
  NavigationMetadata,
  RemoteConfig,
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
  private sequenceNumber = 0;
  private currentViewId: string | null = null;
  private currentViewStartedAt: number | null = null;
  private isVisible = true;
  private heartbeatTimer: ReturnType<typeof setInterval> | null = null;
  private flushTimer: ReturnType<typeof setTimeout> | null = null;
  private pendingBatch: TelemetryEvent[] = [];
  private retryTimer: ReturnType<typeof setTimeout> | null = null;
  private lastDeliveryError: string | null = null;
  private lastRemoteConfigFetch: string | null = null;
  private lifecycleBound = false;

  /** Initialize the SDK. Call once per application load. */
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

    if (options.autoLifecycle !== false) {
      this.bindLifecycle();
    }

    if (this.remoteConfig.config.automatic_navigation_tracking) {
      this.trackNavigation({
        route: typeof location !== 'undefined' ? location.pathname : undefined,
        path: typeof location !== 'undefined' ? location.pathname + location.search : undefined,
        title: typeof document !== 'undefined' ? document.title : undefined,
        referrer: typeof document !== 'undefined' ? document.referrer : undefined,
      });
    }

    this.startHeartbeat();
    this.initialized = true;

    void this.flushOfflineBuffer();
  }

  /** Set authenticated user ID for subsequent events. */
  setUserId(userId: string | null): void {
    this.userId = userId;
  }

  /** Replace application-level context metadata. */
  setApplicationContext(metadata: Metadata): void {
    this.context.setApplicationContext(metadata);
  }

  /** Merge into application-level context. */
  mergeApplicationContext(metadata: Metadata): void {
    this.context.mergeApplicationContext(metadata);
  }

  /** Replace session-level context metadata. */
  setSessionContext(metadata: Metadata): void {
    this.context.setSessionContext(metadata);
  }

  /** Replace view-level context metadata. */
  setViewContext(metadata: Metadata): void {
    this.context.setViewContext(metadata);
  }

  /** Track a custom event. */
  track(eventType: string, metadata?: Metadata): void {
    this.enqueueEvent(eventType, metadata);
  }

  /** Track route/page navigation. */
  trackNavigation(metadata?: NavigationMetadata): void {
    this.enqueueEvent('navigation.page_view', {
      ...metadata,
      route: metadata?.route ?? (typeof location !== 'undefined' ? location.pathname : undefined),
      path: metadata?.path ?? (typeof location !== 'undefined' ? location.pathname + location.search : undefined),
      title: metadata?.title ?? (typeof document !== 'undefined' ? document.title : undefined),
      referrer: metadata?.referrer ?? (typeof document !== 'undefined' ? document.referrer : undefined),
    });
  }

  /** Start a logical view instance. */
  trackViewStart(metadata?: ViewMetadata): void {
    if (this.currentViewId) {
      this.trackViewEnd();
    }

    this.currentViewId = generateId();
    this.currentViewStartedAt = Date.now();
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

  /** End the active view instance. */
  trackViewEnd(metadata?: ViewMetadata): void {
    if (!this.currentViewId) {
      return;
    }

    const wallClockMs =
      this.currentViewStartedAt !== null ? Date.now() - this.currentViewStartedAt : undefined;

    this.enqueueEvent('navigation.view_ended', {
      view_instance_id: this.currentViewId,
      view_name: metadata?.view_name,
      route: metadata?.route,
      path: metadata?.path,
      wall_clock_ms: wallClockMs,
      ...metadata,
    });

    this.currentViewId = null;
    this.currentViewStartedAt = null;
    this.context.clearViewContext();
  }

  /** Track an explicit user interaction. */
  trackInteraction(metadata?: InteractionMetadata): void {
    this.enqueueEvent('interaction.performed', metadata);
  }

  /** Flush pending events and shut down listeners. */
  async shutdown(): Promise<void> {
    if (this.currentViewId) {
      this.trackViewEnd();
    }

    this.stopHeartbeat();
    this.unbindLifecycle();

    if (this.flushTimer) {
      clearTimeout(this.flushTimer);
      this.flushTimer = null;
    }

    await this.deliverBatch(this.pendingBatch.splice(0));
    this.initialized = false;
  }

  /** Current diagnostic state for developer tooling. */
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
      this.lastDeliveryError = error instanceof Error ? error.message : String(error);
      this.diagnostic('delivery failed', error);
      await this.bufferEvents(events);
      throw error;
    }
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

        this.lastDeliveryError = error instanceof Error ? error.message : String(error);
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
      const event = this.buildEvent('navigation.view_ended', {
        view_instance_id: this.currentViewId,
        abrupt: true,
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

      this.enqueueEvent('session.heartbeat', {
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
