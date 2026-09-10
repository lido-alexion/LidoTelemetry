import type { TelemetryEvent } from './types.js';

export interface TransportOptions {
  endpoint: string;
  ingestionToken: string;
}

type MetricPayload = Record<string, unknown>;
type LogPayload = Record<string, unknown>;
type SpanPayload = Record<string, unknown>;

export class Transport {
  private readonly baseUrl: string;
  private readonly ingestionToken: string;

  constructor(options: TransportOptions) {
    this.baseUrl = options.endpoint.replace(/\/$/, '');
    this.ingestionToken = options.ingestionToken;
  }

  async sendEvents(events: TelemetryEvent[]): Promise<void> {
    await this.post('/ingest/events', { events });
  }

  async sendMetrics(metrics: MetricPayload[]): Promise<void> {
    await this.post('/ingest/metrics', { metrics });
  }

  async sendLogs(logs: LogPayload[]): Promise<void> {
    await this.post('/ingest/logs', { logs });
  }

  async sendTraces(spans: SpanPayload[]): Promise<void> {
    await this.post('/ingest/traces', { spans });
  }

  async fetchRemoteConfig(): Promise<Record<string, unknown>> {
    const response = await fetch(`${this.baseUrl}/remote-config`, {
      method: 'GET',
      headers: this.headers(),
    });

    if (!response.ok) {
      throw new Error(`Remote config fetch failed (${response.status})`);
    }

    const json = (await response.json()) as { data?: Record<string, unknown> };
    return json.data ?? {};
  }

  private async post(path: string, body: Record<string, unknown>): Promise<void> {
    const response = await fetch(`${this.baseUrl}${path}`, {
      method: 'POST',
      headers: this.headers(),
      body: JSON.stringify(body),
      keepalive: true,
    });

    if (!response.ok) {
      const text = await response.text().catch(() => '');
      throw new Error(`Telemetry delivery failed (${response.status}): ${text}`);
    }
  }

  private headers(): Record<string, string> {
    return {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${this.ingestionToken}`,
    };
  }
}
