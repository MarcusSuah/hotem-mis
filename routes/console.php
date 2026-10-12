<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('hms:expire-pending-reservations')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('hms:generate-preventive-maintenance')->dailyAt('05:00')->withoutOverlapping();
Schedule::command('hms:generate-housekeeping-tasks')->dailyAt('06:00')->withoutOverlapping();
