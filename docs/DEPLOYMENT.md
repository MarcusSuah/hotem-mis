# Deployment

This guide covers a single Linux server running Nginx, PHP-FPM 8.4 and MySQL 8. The same steps apply to managed platforms such as Laravel Cloud or Forge, which handle most of the server setup for you.

## 1. Server requirements

- PHP 8.4 with the `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml` and `zip` extensions.
- MySQL 8 (or MariaDB 10.11+) and the `mysqldump` client, which backups need.
- Node 22+, used to build the frontend. You can also build in CI and deploy `public/build`.
- A process supervisor (systemd or Supervisor) for the queue worker.
- HTTPS. Cookies, sessions and HSTS assume a secure site.

## 2. Environment

Copy `.env.example` to `.env` on the server and set at least the following.

| Setting | Production value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (never `true` on a public server) |
| `APP_URL` | The public `https://` address. It is used in emails, sitemaps and signed links. |
| `APP_KEY` | Generated once with `php artisan key:generate`. Back it up: encrypted guest identity numbers cannot be read without it. |
| `DB_*` | A database user with rights to this schema only |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_ENCRYPT` | `true` |
| `QUEUE_CONNECTION` | `database` (or `redis`) |
| `CACHE_STORE` | `database` or `redis`. Scheduled jobs use cache locks, so `array` is not suitable. |
| `MAIL_*` | A real mail provider, used for booking confirmations, reminders and alerts |
| `SUPER_ADMIN_EMAIL` | Email address of the protected administrator |
| `HMS_PUBLIC_DISK` / `HMS_PRIVATE_DISK` | `public` and `local`, or S3-compatible disks |
| `HMS_PAYMENT_WEBHOOK_*` | Leave disabled until a provider is connected (see [PAYMENT-WEBHOOKS.md](PAYMENT-WEBHOOKS.md)) |

Never commit `.env`, and never put passwords in source control. Demo accounts are only created outside production.

## 3. First deployment

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate              # first deployment only
php artisan migrate --force
php artisan db:seed --force           # currencies, settings, roles and permissions; demo data is skipped in production
php artisan storage:link
php artisan hms:provision-super-admin # prompts for the administrator password
php artisan optimize
```

Point the web server's document root at `public/`. Grant the PHP user write access to `storage/` and `bootstrap/cache/` only.

## 4. Each later deployment

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # grants new permissions to built-in roles
php artisan optimize
php artisan queue:restart
php artisan up
```

## 5. Queue worker

Emails, notifications and payment webhooks are processed by the queue. Keep a worker running under a supervisor, for example as this systemd unit:

```ini
[Unit]
Description=HMS queue worker
After=network.target mysql.service

[Service]
User=www-data
Restart=always
WorkingDirectory=/var/www/hms
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

## 6. Scheduler

Add one cron entry for the web user:

```cron
* * * * * cd /var/www/hms && php artisan schedule:run >> /dev/null 2>&1
```

[OPERATIONS.md](OPERATIONS.md) lists what runs and when. Check the schedule with `php artisan schedule:list`.

## 7. Health checks

`GET /up` returns 200 when the application boots and can render. Point your uptime monitor at it.

## 8. Security checklist

- HTTPS only, with `SESSION_SECURE_COOKIE=true`. HSTS is added automatically on HTTPS requests.
- `APP_DEBUG=false`. Error pages are branded and reveal no stack traces.
- The Super Admin was created with `hms:provision-super-admin`, and the password is stored in a password manager.
- The database user has no rights outside the HMS schema.
- Backups are copied off the server (see [OPERATIONS.md](OPERATIONS.md)).
- Private files, such as maintenance photos, live on the private disk and are served only through authorised routes. Invoice PDFs are generated on request, only for staff with access and for the guest the invoice belongs to.
- Login and public forms are rate limited. Review the `auth.login` entries in the audit log for unusual activity.
