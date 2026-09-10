export type Metadata = Record<string, unknown>;

export interface TelemetryInitOptions {
  /** Ingestion credential token (Bearer). */
  ingestionToken: string;
  /** Base API URL, e.g. `https://telemetry.example.com/api/v1`. */
  endpoint: string;
  /** Optional product key for application context metadata. */
  productKey?: string;
  /** Authenticated product-scoped user ID. */
  userId?: string;
  /** Application correlation ID propagated on all signals. */
  correlationId?: string;
  /** Application-level metadata merged into every event. */
  applicationContext?: Metadata;
  /** Heartbeat interval in seconds (overrides remote config when set). */
  heartbeatIntervalSeconds?: number;
  /** Batch size; 0 = unbatched (default). */
  batchSize?: number;
  /** Enable automatic visibility and heartbeat listeners. */
  autoLifecycle?: boolean;
  /** Max offline buffer size (default 500). */
  offlineBufferMax?: number;
  /** Diagnostic callback for delivery/buffer issues. */
  onDiagnostic?: (message: string, detail?: unknown) => void;
}

export interface RemoteConfig {
  version: number;
  config: {
    heartbeat_interval_seconds: number;
    batch_size: number;
    automatic_navigation_tracking: boolean;
    signal_families: {
      events: boolean;
      metrics: boolean;
      logs: boolean;
      traces: boolean;
    };
    metadata_policy: {
      mode: 'allowlist' | 'denylist';
      forbidden_keys?: string[];
      allowed_keys?: string[];
    };
  };
}

export interface TelemetryEvent {
  event_id: string;
  event_type: string;
  occurred_at: string;
  category?: string;
  user_id?: string;
  anonymous_id?: string;
  session_id?: string;
  view_instance_id?: string;
  sequence_number?: number;
  correlation_id?: string;
  trace_id?: string;
  span_id?: string;
  metadata?: Metadata;
}

export interface NavigationMetadata extends Metadata {
  route?: string;
  path?: string;
  referrer?: string;
  title?: string;
}

export interface ViewMetadata extends Metadata {
  view_name?: string;
  route?: string;
  path?: string;
}

export interface InteractionMetadata extends Metadata {
  element?: string;
  action?: string;
  label?: string;
}

export interface MetricMetadata extends Metadata {
  type?: 'counter' | 'gauge' | 'histogram' | 'timer';
}

export interface LogMetadata extends Metadata {
  service?: string;
  message_code?: string;
}

export interface SpanMetadata extends Metadata {
  trace_id: string;
  span_id: string;
  parent_span_id?: string;
  name: string;
  started_at?: string;
  ended_at?: string;
  duration_ms?: number;
  status?: string;
  correlation_id?: string;
  attributes?: Metadata;
}

export interface DiagnosticState {
  initialized: boolean;
  online: boolean;
  bufferedCount: number;
  lastDeliveryError: string | null;
  lastRemoteConfigFetch: string | null;
  sessionId: string | null;
  anonymousId: string | null;
}
