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

];
