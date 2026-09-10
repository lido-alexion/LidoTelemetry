# lido-alexion/telemetry-php

PHP server SDK for [Lido Telemetry](https://github.com/lido-alexion/LidoTelemetry). Queues events, metrics, logs, and traces asynchronously with a durable bounded queue so business requests never block on delivery.

## Install

```bash
composer require lido-alexion/telemetry-php
```

## Quick start

```php
<?php

use LidoAlexion\Telemetry\TelemetryClient;

$telemetry = new TelemetryClient([
    'endpoint' => 'https://telemetry.example.com/api/v1',
    'ingestion_token' => 'your-ingestion-token',
    'application_context' => ['service' => 'billing-api'],
]);

$telemetry->setCorrelationId('req-abc-123');
$telemetry->trackEvent('operational.api_failed', ['route' => '/invoices', 'status' => 500]);
$telemetry->trackMetric('http.request.duration_ms', 142.5, 'timer', ['route' => '/invoices']);
$telemetry->trackLog('error', 'Invoice export failed', 'billing-api', 'invoice.export_failed');
$telemetry->trackSpan(
    traceId: 'trace-1',
    spanId: 'span-1',
    name: 'export_invoice',
    endedAt: (new DateTimeImmutable())->format(DATE_ATOM),
    durationMs: 142,
    status: 'ERROR',
);

// Optional explicit flush (also runs on shutdown)
$result = $telemetry->flush();
```

## Configuration

| Option | Required | Description |
|--------|----------|-------------|
| `endpoint` | Yes | API base URL, e.g. `https://host/api/v1` |
| `ingestion_token` | Yes | Bearer ingestion credential |
| `batch_size` | No | Delivery batch size (default 50) |
| `application_context` | No | Metadata merged into every signal |
| `queue` | No | Queue driver options (see below) |
| `on_diagnostic` | No | Callable for delivery/buffer issues |

## Queue drivers

### File (default)

```php
$telemetry = new TelemetryClient([
    'endpoint' => 'https://telemetry.example.com/api/v1',
    'ingestion_token' => 'token',
    'queue' => [
        'driver' => 'file',
        'file_path' => '/var/lib/myapp/telemetry_queue.jsonl',
        'max_size' => 5000,
    ],
]);
```

### Database (PDO)

```sql
CREATE TABLE telemetry_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    payload TEXT NOT NULL,
    created_at VARCHAR(32) NOT NULL
);
```

```php
$telemetry = new TelemetryClient([
    'endpoint' => 'https://telemetry.example.com/api/v1',
    'ingestion_token' => 'token',
    'queue' => [
        'driver' => 'database',
        'pdo' => $pdo,
        'table' => 'telemetry_queue',
        'max_size' => 5000,
    ],
]);
```

## API reference

### `TelemetryClient`

| Method | Description |
|--------|-------------|
| `trackEvent($eventType, $metadata = [], $eventId = null)` | Queue an analytics/operational event |
| `trackMetric($name, $value, $type = 'gauge', $dimensions = [])` | Queue a metric point |
| `trackLog($severity, $message, $service = null, $messageCode = null, $metadata = [])` | Queue a structured log |
| `trackSpan(...)` | Queue a trace span |
| `setUserId($userId)` | Attach product-scoped user ID |
| `setSessionId($sessionId)` | Attach session ID |
| `setCorrelationId($correlationId)` | Attach correlation ID |
| `setApplicationContext($context)` | Replace application metadata |
| `flush()` | Deliver queued payloads; returns `['delivered' => int, 'failed' => int]` |
| `getQueueDepth()` | Current queue size |
| `getLastDeliveryError()` | Last delivery error message |

### `EventQueue`

Low-level durable bounded FIFO queue used internally. Supports `file` and `database` drivers with `push`, `pop`, `acknowledge`, and `count`.

## Delivery semantics

- At-least-once delivery with stable `event_id` for events
- Automatic flush on PHP shutdown
- Failed batches are re-queued for retry
- Queue is bounded; oldest entries are dropped when full

## Ingestion endpoints

| Signal | Endpoint | Body key |
|--------|----------|----------|
| Events | `POST /ingest/events` | `events` |
| Metrics | `POST /ingest/metrics` | `metrics` |
| Logs | `POST /ingest/logs` | `logs` |
| Traces | `POST /ingest/traces` | `spans` |

All requests use `Authorization: Bearer <ingestion_token>`.
