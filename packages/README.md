# Lido Telemetry SDKs

Official client libraries for integrating applications with the [Lido Telemetry](https://github.com/lido-alexion/LidoTelemetry) platform.

## Packages

| Package | Language | Install | Description |
|---------|----------|---------|-------------|
| [@lido-alexion/telemetry-browser](./sdk-browser/) | TypeScript / Browser | `npm install @lido-alexion/telemetry-browser` | Sessions, views, visibility, heartbeat, offline IndexedDB buffering |
| [lido-alexion/telemetry-php](./sdk-php/) | PHP | `composer require lido-alexion/telemetry-php` | Async durable queue for events, metrics, logs, and traces |

## When to use which SDK

**Browser SDK** — client-side product analytics and navigation instrumentation. Use in SPAs, static sites, or any browser application that needs per-tab sessions, view lifecycle tracking, and offline event delivery.

**PHP SDK** — server-side operational telemetry from PHP backends (Laravel, Symfony, plain PHP). Business requests enqueue signals and return immediately; delivery happens asynchronously via file or database queue.

## Shared concepts

Both SDKs follow the Lido Telemetry ingestion contract:

- **Authentication** — Bearer ingestion token; `product_id` and `environment` are derived server-side from the credential
- **At-least-once delivery** — stable IDs, retries, deduplication at ingestion
- **Context metadata** — application/session/view layers (browser) or application context (PHP)
- **Non-blocking** — telemetry failures never interrupt business workflows
- **Diagnostics** — optional callbacks for delivery/buffer health

## API base URL

Point both SDKs at your Telemetry API root:

```text
https://your-telemetry-host/api/v1
```

## Remote configuration (browser only)

The browser SDK fetches `GET /api/v1/remote-config` on init to apply heartbeat interval, batching, navigation tracking, and metadata policy. The PHP SDK uses local configuration.

## Documentation

- [Browser SDK README](./sdk-browser/README.md)
- [PHP SDK README](./sdk-php/README.md)
- [Platform design spec](../specs/designDoc.md)
