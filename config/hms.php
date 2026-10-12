<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Protected Super Admin
    |--------------------------------------------------------------------------
    |
    | The account provisioned by `php artisan hms:provision-super-admin`. The
    | email is the stable identifier for the protected account; its password
    | is never stored in configuration or source control.
    |
    */

    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL'),
        'name' => env('SUPER_ADMIN_NAME', 'Super Administrator'),
    ],

    /*
    | Shared password for development-only demo accounts. Demo seeders never
    | run in production.
    */

    'demo_password' => env('DEMO_USER_PASSWORD', 'password'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Room rates are stored in the base currency. The base currency can be
    | changed in settings only while no financial transactions exist.
    |
    */

    'currency' => [
        'base' => env('HMS_BASE_CURRENCY', 'USD'),
        'default_display' => env('HMS_DEFAULT_DISPLAY_CURRENCY', 'USD'),
        'cookie' => 'display_currency',
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        'public_disk' => env('HMS_PUBLIC_DISK', 'public'),
        'private_disk' => env('HMS_PRIVATE_DISK', 'local'),
        'max_image_kb' => 5120,
        'max_document_kb' => 10240,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Attribute names containing any of these fragments are never written to
    | the audit log.
    |
    */

    'audit' => [
        'redact' => ['password', 'token', 'secret', 'card', 'cvv', 'cvc', 'recovery_codes', 'identity_number'],
        'retention_days' => (int) env('HMS_AUDIT_RETENTION_DAYS', 730),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Webhook endpoints are POST /webhooks/payments/{name}. A gateway is only
    | reachable when enabled and given a secret. Card details are never sent
    | to or stored by this application.
    |
    */

    'payments' => [
        'gateways' => [
            'online' => [
                'driver' => 'signed-webhook',
                'enabled' => (bool) env('HMS_PAYMENT_WEBHOOK_ENABLED', false),
                'secret' => env('HMS_PAYMENT_WEBHOOK_SECRET'),
                'tolerance' => (int) env('HMS_PAYMENT_WEBHOOK_TOLERANCE', 300),
                'method' => env('HMS_PAYMENT_WEBHOOK_METHOD', 'card'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Backups
    |--------------------------------------------------------------------------
    |
    | hms:backup-database writes a compressed dump to the private disk and
    | keeps it for the configured number of days. Copy backups off the
    | server as well; a backup on the same disk is not a disaster recovery.
    |
    */

    'backups' => [
        'disk' => env('HMS_BACKUP_DISK', 'local'),
        'path' => env('HMS_BACKUP_PATH', 'backups'),
        'keep_days' => (int) env('HMS_BACKUP_KEEP_DAYS', 14),
        'mysqldump' => env('HMS_MYSQLDUMP_PATH', 'mysqldump'),
    ],

];
