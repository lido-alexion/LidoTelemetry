# LidoTelemetry — stoxla.in VPS deployment

## Production target

| Item | Value |
|---|---|
| URL | `https://telemetry.stoxla.in` |
| VPS | Hostinger `82.112.230.20` |
| SSH user | `nitty` |
| Application root | `/var/www/lidotelemetry` |
| Document root | `/var/www/lidotelemetry/current/public` |
| Database | Separate MariaDB database `lidotelemetry` |
| Runtime | PHP 8.4-FPM, Node 22 build, Nginx |

LidoTelemetry is isolated from StoX. Do not modify `/var/www/stoxla`, the
`stoxla` database, or the `stoxla-queue` service.

## Readiness gates

Deployment is allowed only when:

1. PHP tests pass.
2. JavaScript tests and production build pass.
3. Browser SDK and PHP SDK builds/validation pass.
4. A clean MySQL migration succeeds without seed data.
5. `APP_ENV=production`, `APP_DEBUG=false`, and HTTPS cookies are configured.
6. The default demo admin and demo ingestion credential are not seeded.

Never run `php artisan db:seed` or `php artisan migrate --seed` in production.
`TelemetrySeeder` contains public development credentials.

## One-time DNS

Create an A record:

```text
telemetry.stoxla.in -> 82.112.230.20
```

## One-time database provisioning

Run interactively as a MariaDB administrator. Use a newly generated password;
do not reuse or print the StoX database password.

```sql
CREATE DATABASE lidotelemetry
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
CREATE USER 'lidotelemetry_app'@'localhost' IDENTIFIED BY '<STRONG_PASSWORD>';
GRANT ALL PRIVILEGES ON lidotelemetry.* TO 'lidotelemetry_app'@'localhost';
FLUSH PRIVILEGES;
```

## One-time filesystem provisioning

```bash
sudo mkdir -p /var/www/lidotelemetry/{releases,shared/storage}
sudo chown -R nitty:www-data /var/www/lidotelemetry
sudo chmod 2775 /var/www/lidotelemetry /var/www/lidotelemetry/releases /var/www/lidotelemetry/shared
sudo chmod -R ug+rwX /var/www/lidotelemetry/shared/storage
```

Create `/var/www/lidotelemetry/shared/.env` as `nitty`, then set mode 600:

```dotenv
APP_NAME="Lido Telemetry"
APP_ENV=production
APP_KEY=<GENERATE_UNIQUE_KEY>
APP_DEBUG=false
APP_URL=https://telemetry.stoxla.in

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lidotelemetry
DB_USERNAME=lidotelemetry_app
DB_PASSWORD=<UNIQUE_SECRET>

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=telemetry.stoxla.in

SANCTUM_STATEFUL_DOMAINS=telemetry.stoxla.in
CACHE_STORE=database
QUEUE_CONNECTION=database

TELEMETRY_BRAND_NAME="Lido Telemetry"
TELEMETRY_RAW_RETENTION_DAYS=90
TELEMETRY_AGGREGATE_RETENTION_DAYS=730
```

Generate `APP_KEY` without exposing it:

```bash
cd /tmp
APP_KEY_VALUE="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY_VALUE}|" /var/www/lidotelemetry/shared/.env
unset APP_KEY_VALUE
chmod 600 /var/www/lidotelemetry/shared/.env
```

## One-time Nginx and TLS

```bash
sudo install -o root -g root -m 0644 deploy/nginx/lidotelemetry.conf /etc/nginx/sites-available/lidotelemetry
sudo ln -s /etc/nginx/sites-available/lidotelemetry /etc/nginx/sites-enabled/lidotelemetry
sudo nginx -t
sudo systemctl reload nginx
sudo certbot --nginx -d telemetry.stoxla.in
sudo certbot renew --dry-run
```

Keep `telemetry.stoxla.in` as a separate virtual host. Do not add a
`/telemetry` location to the StoX server block.

## Scheduler

Add to `nitty`'s crontab after the first successful deployment:

```cron
* * * * * cd /var/www/lidotelemetry/current && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

The application schedules `telemetry:apply-retention` daily at 03:30 server
time.

## Restricted service permission

The existing StoX rule may already authorize the exact PHP-FPM reload command.
If not, a root administrator can add only:

```sudoers
nitty ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm
```

Do not grant unrestricted passwordless sudo.

## GitHub repository secrets

Copy the existing deployment connection values into this repository's
production environment:

- `STOXLA_HOST`
- `STOXLA_SSH_USER`
- `STOXLA_SSH_PRIVATE_KEY`

Secrets are repository/environment scoped; the workflow cannot read StoX
repository secrets automatically.

## Validation

```bash
curl -I https://telemetry.stoxla.in/up
curl -I https://telemetry.stoxla.in/
```

Expected: HTTP 200, valid TLS, production SPA assets under `/build/`, and no
changes to `https://stoxla.in`.

Rollback code by running:

```bash
/home/nitty/.lidotelemetry-deploy/lidotelemetry-rollback-release.sh
```

Database migrations are forward-only; take a database backup before later
schema-changing releases.
