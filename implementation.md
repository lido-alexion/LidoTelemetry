# Lido Telemetry — implementation reference

Living technical reference for agents and developers. Update this file when architecture or runbook details change.

## Stack

| Layer | Technology |
|-------|------------|
| API + admin shell | Laravel 13, PHP 8.4 |
| UI auth | Sanctum session cookies (StoX-style) |
| Ingestion auth | Bearer tokens (`telemetry_ingestion_credentials`) |
| Query API auth | Personal API tokens or Sanctum session |
| Admin UI | React 19, React Router, Bootstrap 5, Vite |
| Database | MySQL, `telemetry_*` table prefix |
| SDKs | `packages/sdk-browser`, `packages/sdk-php` |

## Architecture boundaries

```
API / Admin UI
      ↓
Application Services (IngestionService, ProductManagementService, …)
      ↓
Storage contracts (EventWriterInterface, TelemetryQueryServiceInterface)
      ↓
Relational adapters (RelationalEventWriter, RelationalTelemetryQueryService)
```

Controllers never query `telemetry_events` directly. Analytics logic stays in query services/adapters.

## Core loop

1. **Products** — `telemetry_products` + `telemetry_product_environments`
2. **Ingestion credentials** — write-only; product/environment derived from credential, not payload
3. **Ingest** — `/api/v1/ingest/{events,metrics,logs,traces,otel}`
4. **Materialize** — sessions, views, hourly/daily aggregates on ingest
5. **Query** — `/api/v1/query`, explorer endpoints, dashboard data
6. **Admin** — products, credentials, users, invites

## Retention defaults

Configured in `config/telemetry.php`:

- Raw telemetry: **90 days** (`TELEMETRY_RAW_RETENTION_DAYS`)
- Aggregates: **730 days** (`TELEMETRY_AGGREGATE_RETENTION_DAYS`)

Per-environment overrides via `telemetry_product_environments.raw_retention_days`.

Run manually: `php artisan telemetry:apply-retention`

## Local development

```bash
cd app
php artisan serve --host=127.0.0.1 --port=8002
npm run dev   # http://127.0.0.1:5174
```

`.env` essentials:

```env
APP_URL=http://127.0.0.1:8002
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,127.0.0.1:8002
SESSION_SECURE_COOKIE=false
TELEMETRY_BRAND_NAME="Lido Telemetry"
```

## API surface (v1)

| Area | Prefix | Auth |
|------|--------|------|
| Ingestion | `/api/v1/ingest/*` | Ingestion Bearer |
| Remote config | `/api/v1/remote-config` | Ingestion Bearer |
| Query / Explorer | `/api/v1/query`, `/api/v1/explorer/*` | API token or session |
| Dashboards | `/api/v1/dashboards/*` | Session |
| Admin | `/api/v1/admin/*` | Session + admin role |
| Auth | `/api/v1/auth/*` | Public / session |

## Seeded demo data

- Admin: `admin@lidotelemetry.local` / `password123`
- Product: `stox`, environment: `production`
- Ingestion token: `lti_demo_stox_production_seed_token_change_me`

## StoX integration (future)

StoX remains a separate repo. Integration = SDK dependency + ingestion credential + explicit business events. No shared auth or embedded telemetry module.

## Tests

```bash
cd app
php artisan test
npm run test:js   # Node 20+
```
