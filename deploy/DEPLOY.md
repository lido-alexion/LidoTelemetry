# Deploy — Lido Telemetry

Production target: same cPanel host as StoX (`lidoalexion.com`), subdirectory deploy (e.g. `/telemetry`).

## Build

```bash
cd app
cp .env.production.example .env   # configure DB, APP_URL, secrets
composer install --no-dev --optimize-autoloader
npm ci
VITE_APP_BASE=/telemetry/build/ npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Environment

```env
APP_URL=https://lidoalexion.com/telemetry
TELEMETRY_BRAND_NAME="Lido Telemetry"
DB_*=shared MySQL (telemetry_* tables)
SANCTUM_STATEFUL_DOMAINS=lidoalexion.com,www.lidoalexion.com
SESSION_SECURE_COOKIE=true
```

## Document root

Point the `/telemetry` path to `app/public/` (same pattern as StoX `/portfolio`).

## Post-deploy

1. Change seeded demo ingestion token
2. Create production admin invites (disable default password if exposed)
3. Register real product credentials per environment
4. Schedule `php artisan telemetry:apply-retention` daily

## Shared database

`telemetry_*` tables coexist with StoX `portfolio_*` tables in the same MySQL database.
