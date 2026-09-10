# Lido Telemetry

Standalone telemetry and product-analytics platform for [StoX](https://github.com/lido-alexion/LidoPortfolio) and other products.

- **Backend:** Laravel 13 (PHP 8.4+)
- **Admin UI:** React + Bootstrap (Vite)
- **Database:** MySQL with `telemetry_*` tables (can share a database with StoX)
- **SDKs:** Browser (TypeScript) and server (PHP)

Brand name is configurable via `TELEMETRY_BRAND_NAME` (default: **Lido Telemetry**).

## Documentation

| File | Description |
|------|-------------|
| [DOCS.md](DOCS.md) | Documentation ingestion tree |
| [specs/designDoc.md](specs/designDoc.md) | V7 architecture specification |
| [implementation.md](implementation.md) | Living technical reference |
| [packages/README.md](packages/README.md) | SDK packages |

## Project structure

```
LidoTelemetry/
├── app/                 # Laravel application + React admin UI
├── packages/
│   ├── sdk-browser/     # @lido-alexion/telemetry-browser
│   └── sdk-php/         # lido-alexion/telemetry-php
├── deploy/              # Production deploy notes
├── specs/               # Product specifications
└── .github/workflows/   # CI
```

## Quick start (local)

```bash
cd app
cp .env.example .env
# Configure DB_* for MySQL, or use sqlite for smoke tests

php ../composer.phar install   # or composer install
php artisan key:generate
php artisan migrate --seed

npm install
npm run dev                    # Vite on :5174

php artisan serve --host=127.0.0.1 --port=8002
```

Open **http://127.0.0.1:8002**

**Login:** `admin@lidotelemetry.local` / `password123`

**Demo ingestion token (seed):** `lti_demo_stox_production_seed_token_change_me`

## Acceptance loop (§28)

1. Register product (`stox` seeded)
2. Create ingestion credential (seeded)
3. Instrument via SDK or HTTP API
4. Ingest events / metrics / logs / traces
5. Sessions + views materialized automatically
6. Explore in UI or Query API
7. Built-in dashboards + custom dashboards
8. Export CSV / JSON

## StoX integration

Telemetry is a **separate product and repository**. StoX integrates via SDK + credentials only — no embedded telemetry module in StoX.

## Requirements

| Tool | Version |
|------|---------|
| PHP | 8.4+ |
| MySQL | 8+ (or SQLite for tests) |
| Node.js | 20+ (frontend build) |
| Composer | 2.x |

## License

Proprietary — private repository.
