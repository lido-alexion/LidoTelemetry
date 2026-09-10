# @lido-alexion/telemetry-browser

Browser SDK for [Lido Telemetry](https://github.com/lido-alexion/LidoTelemetry). Captures product analytics events with per-tab sessions, offline buffering, visibility tracking, and remote configuration.

## Install

```bash
npm install @lido-alexion/telemetry-browser
```

## Quick start

```typescript
import { TelemetryClient } from '@lido-alexion/telemetry-browser';

const telemetry = new TelemetryClient();

await telemetry.init({
  ingestionToken: 'your-ingestion-token',
  endpoint: 'https://telemetry.example.com/api/v1',
  productKey: 'my-app',
});

telemetry.track('workflow.completed', { workflow: 'onboarding', step: 'verify_email' });
telemetry.trackInteraction({ element: 'button', action: 'click', label: 'save' });
```

## Initialization

| Option | Required | Description |
|--------|----------|-------------|
| `ingestionToken` | Yes | Bearer ingestion credential |
| `endpoint` | Yes | API base URL, e.g. `https://host/api/v1` |
| `productKey` | No | Added to application context metadata |
| `userId` | No | Authenticated product-scoped user ID |
| `applicationContext` | No | Persistent metadata for all events |
| `heartbeatIntervalSeconds` | No | Override remote config (default 60s) |
| `batchSize` | No | `0` = unbatched (default) |
| `autoLifecycle` | No | Bind visibility/heartbeat listeners (default `true`) |
| `offlineBufferMax` | No | IndexedDB buffer cap (default 500) |
| `onDiagnostic` | No | Callback for delivery/buffer issues |

## Identity

- **Session ID** — one per browser tab (`sessionStorage`). A new tab always gets a new session.
- **Anonymous ID** — persistent across restarts (`localStorage`).

Call `setUserId(id)` when the user authenticates.

## Context metadata layers

Metadata is merged in precedence order (more specific wins):

```
application → session → view → event
```

```typescript
telemetry.setApplicationContext({ plan: 'pro', region: 'us-east' });
telemetry.setSessionContext({ experiment: 'checkout-v2' });
telemetry.trackViewStart({ view_name: 'dashboard' });
telemetry.track('item.selected', { item_id: 'abc' });
```

## View lifecycle

```typescript
telemetry.trackViewStart({ view_name: 'portfolio', route: '/portfolio' });
// ... user interacts ...
telemetry.trackViewEnd();
```

`trackViewEnd` emits `navigation.view_ended` with wall-clock duration. Visibility changes emit `navigation.visibility_hidden` / `navigation.visibility_visible`.

## Navigation and interactions

```typescript
telemetry.trackNavigation({ route: '/settings', title: 'Settings' });
telemetry.trackInteraction({ element: 'button', action: 'click', label: 'Export' });
```

## Offline delivery

Failed or offline events are stored in IndexedDB and retried when the network recovers. Original `event_id`, `session_id`, and timestamps are preserved.

## Remote configuration

On init the SDK fetches `GET /api/v1/remote-config` and applies:

- heartbeat interval
- batch size
- automatic navigation tracking
- signal family toggles
- metadata allow/deny policy

If remote config is unavailable, safe defaults are used.

## Build

```bash
npm install
npm run build
```

Output is emitted to `dist/`.

## API reference

### `TelemetryClient`

| Method | Description |
|--------|-------------|
| `init(options)` | Initialize SDK (call once) |
| `track(eventType, metadata?)` | Emit a custom event |
| `trackNavigation(metadata?)` | Emit `navigation.page_view` |
| `trackViewStart(metadata?)` | Start a view instance |
| `trackViewEnd(metadata?)` | End the active view |
| `trackInteraction(metadata?)` | Emit `interaction.performed` |
| `setUserId(userId)` | Attach authenticated user |
| `setApplicationContext(metadata)` | Replace application context |
| `mergeApplicationContext(metadata)` | Merge application context |
| `setSessionContext(metadata)` | Replace session context |
| `setViewContext(metadata)` | Replace view context |
| `getDiagnosticState()` | Async delivery/buffer diagnostics |
| `shutdown()` | Flush and tear down listeners |
