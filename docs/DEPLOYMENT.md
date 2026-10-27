# Deployment

## Requirements

- PHP 8.3+ (developed on 8.4) with `pdo_pgsql`, `gd` (with WebP + FreeType), `intl`,
  `mbstring`, `bcmath`, `fileinfo`, `openssl`
- PostgreSQL 15+ (uses `NULLS NOT DISTINCT`; developed on 17)
- Node 20+ for building assets
- A queue worker, a scheduler (cron) and HTTPS (required for MTN callbacks)
- Hosting location: Rwandan data-protection law requires personal data to be stored in
  Rwanda unless NCSA authorises otherwise – choose infrastructure accordingly.

## First deploy

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env          # then edit (see below)
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=ReferenceDataSeeder --force   # currencies, countries, districts, roles, catalogue, default pricing
npm ci && npm run build
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```

Create the first super administrator:

```bash
php artisan tinker --execute '$u = App\Models\User::create(["name" => "Owner", "email" => "owner@example.rw", "password" => "change-me-now"]); $u->forceFill(["email_verified_at" => now()])->save(); $u->assignRole(App\Enums\RoleName::SuperAdmin);'
```

`DemoSeeder` never runs in production (`DatabaseSeeder` skips it and the seeder refuses).

## Environment

| Key | Production value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://…` |
| `DB_*` | PostgreSQL credentials |
| `QUEUE_CONNECTION` | `database` (or `redis`) |
| `CACHE_STORE`, `SESSION_DRIVER` | `database` or `redis` |
| `MAIL_*` | transactional mail provider |
| `ARTWORK_DISK` | `s3_private` with `AWS_*` / `AWS_PRIVATE_BUCKET` (S3-compatible) or `artwork` for local disk |
| `PAYMENT_SIMULATOR_ENABLED` | `false` (the simulator is also force-disabled in production) |
| `MTN_MOMO_*`, `AIRTEL_MONEY_*` | provider credentials (see `docs/PAYMENTS.md`) |
| `TRUSTED_PROXIES` | load balancer addresses |

## Processes

```
# Queue worker (supervisor/systemd)
php artisan queue:work --tries=3 --max-time=3600

# Scheduler (cron)
* * * * * cd /var/www/inganzo && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs: `payments:reconcile` (5 min), `orders:expire-unpaid` (hourly),
`earnings:release` (02:00 daily), failed-job pruning.

## Backups and monitoring

- Nightly `pg_dump -Fc` with point-in-time recovery (WAL archiving) and an off-site copy;
  test restores monthly. The ledger and audit log are append-only but still need backups.
- Back up the private artwork bucket (versioning enabled).
- Monitor: `/up` health endpoint, Admin › System health (DB, queue size, failed jobs, stale
  pending payments, storage), application logs (`LOG_CHANNEL=stack` → ship to a log service),
  and alerts on `critical` log entries (payment amount mismatches).

## Rollback

Every migration has a working `down()`. Prefer forward-fixing in production; to roll back a
release: restore the previous code, then `php artisan migrate:rollback --step=N` only if the
new migration has not yet stored data you need.

## Local development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # set DB_* for your PostgreSQL
createdb inganzo && createdb inganzo_test
php artisan migrate:fresh --seed                   # reference data + demo data (~30 s)
npm run build                                      # or: npm run dev
php artisan serve
php artisan test                                   # uses the inganzo_test PostgreSQL database
```

Demo accounts (password `password`): `admin@`, `finance@`, `moderator@`, `support@`,
`partner@`, `partner2@`, `artist1@`…`artist4@`, `newartist@`, `customer@` – all `@inganzo.test`.
