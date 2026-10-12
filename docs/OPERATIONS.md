# Operations

Day-to-day running of the system: what runs automatically, and what the hotel team does.

## Scheduled jobs

These run through `php artisan schedule:run`, triggered every minute by cron (see [DEPLOYMENT.md](DEPLOYMENT.md#6-scheduler)).

| When | Command | What it does |
|---|---|---|
| Every 5 minutes | `hms:expire-pending-reservations` | Cancels pending bookings whose hold has run out, with no fee, and frees the rooms. Website requests are held for 24 hours by default (`booking.website_hold_hours`). |
| 02:30 daily | `hms:backup-database` | Writes a compressed MySQL dump and removes backups older than `HMS_BACKUP_KEEP_DAYS` |
| 03:15 daily | `model:prune` | Deletes audit-log entries older than `HMS_AUDIT_RETENTION_DAYS` (minimum 30 days) |
| 05:00 daily | `hms:generate-preventive-maintenance` | Opens maintenance requests for preventive schedules that are due |
| 06:00 daily | `hms:generate-housekeeping-tasks` | Creates stayover cleaning tasks for occupied rooms |
| 09:00 daily | `hms:send-arrival-reminders` | Emails confirmed guests arriving in `booking.arrival_reminder_days` days; each booking is emailed once |

Each job runs once even if there are several servers, and a job never overlaps with its previous run.

## Night audit

The front office closes each business day from **Front Office → Night Audit**. The audit:

1. Posts each in-house guest's room, service charge and tax for the night to their folio. Postings are idempotent, so a repeated audit cannot double-charge.
2. Optionally marks guests who did not arrive as no-shows. The no-show fee is the first night of the stay.
3. Moves the business date forward.

A business day can only be closed once, and never before the date has actually arrived.

## Backups

`hms:backup-database` runs `mysqldump --single-transaction` and passes the password through the environment, so it never appears in the process list. It gzips the dump to `<HMS_BACKUP_DISK>/<HMS_BACKUP_PATH>/hms-YYYY-MM-DD-HHMMSS.sql.gz`.

- **Copy backups off the server.** Set `HMS_BACKUP_DISK` to an S3-compatible disk, or sync the backup folder to other storage. A backup on the same machine does not survive losing that machine.
- **Back up `APP_KEY` separately.** Without it, encrypted fields such as guest identity numbers cannot be read after a restore.
- **Uploaded files are not in the database dump.** Back up the public and private disks (`storage/app`) as well.
- **Run a backup by hand** with `php artisan hms:backup-database`, or `--keep=30` to keep a different number of days.
- **Test a restore regularly.** A backup that has never been restored is untested.

### Restoring

```bash
php artisan down
gunzip -c hms-2026-11-10-023000.sql.gz | mysql -u <user> -p <database>
php artisan migrate --force      # only if the backup predates the current release
php artisan optimize:clear && php artisan up
```

Restore into a fresh, empty database first and check it before replacing production.

## Exchange rates

Finance staff record the day's rate in **Finance → Exchange Rates**. Rates are append-only. New bookings and payments use the latest rate, while existing bookings keep the rate agreed when they were made. Until a rate exists, bookings and payments that need a conversion are refused with a message asking staff to record one.

## Online booking requests

Website bookings arrive as **pending**, and the guest receives an email. The reservations team confirms each one by recording a deposit. A deposit that covers the amount due confirms the booking automatically. A request that is not confirmed in time expires on its own. Pending requests are counted on the dashboard.

## Reports and exports

**Reports** offers CSV and Excel exports for staff with `reports.export`. Each export is recorded in the audit log. All report money is in the base currency.

## Monitoring

- **Uptime:** `GET /up`.
- **Logs:** `storage/logs/laravel.log`. Watch for warnings about failed backups or rejected payment webhooks.
- **Payment webhooks that could not be applied:** listed under **Finance → Online payments**, where they can be retried after the cause is fixed. See [PAYMENT-WEBHOOKS.md](PAYMENT-WEBHOOKS.md).
- **Failed queue jobs:** `php artisan queue:failed`, then `php artisan queue:retry all` once the cause is fixed.

## Regenerating reference docs

After changing roles, permissions or routes, run:

```bash
php artisan hms:generate-docs
```

This rewrites [PERMISSIONS.md](PERMISSIONS.md) and [ROUTES.md](ROUTES.md). In CI, `php artisan hms:generate-docs --check` fails when they are out of date.
