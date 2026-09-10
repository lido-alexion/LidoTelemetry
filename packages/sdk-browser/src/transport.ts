import type { TelemetryEvent } from './types.js';

export interface TransportOptions {
  endpoint: string;
  ingestionToken: string;
}

export class Transport {
  private readonly eventsUrl: string;
  private readonly remoteConfigUrl: string;
  private readonly ingestionToken: string;

  constructor(options: TransportOptions) {
    const base = options.endpoint.replace(/\/$/, '');
    this.eventsUrl = `${base}/ingest/events`;
    this.remoteConfigUrl = `${base}/remote-config`;
    this.ingestionToken = options.ingestionToken;
  }

  async sendEvents(events: TelemetryEvent[]): Promise<void> {
    if (events.length === 0) {
      return;
    }

    const response = await fetch(this.eventsUrl, {
      method: 'POST',
      headers: this.headers(),
      body: JSON.stringify({ events }),
      keepalive: events.length === 1,
    });

    if (!response.ok) {
      const body = await response.text().catch(() => '');
      throw new Error(`Telemetry delivery failed (${response.status}): ${body}`);
    }
  }

  async fetchRemoteConfig(): Promise<Record<string, unknown>> {
    const response = await fetch(this.remoteConfigUrl, {
      method: 'GET',
      headers: this.headers(),
    });

    if (!response.ok) {
      throw new Error(`Remote config fetch failed (${response.status})`);
    }

    const json = (await response.json()) as { data?: Record<string, unknown> };
    return json.data ?? {};
  }

  private headers(): Record<string, string> {
    return {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${this.ingestionToken}`,
    };
  }
}
