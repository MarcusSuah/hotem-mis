<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('hms:expire-pending-reservations')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('hms:generate-preventive-maintenance')->dailyAt('05:00')->withoutOverlapping()->onOneServer();
Schedule::command('hms:generate-housekeeping-tasks')->dailyAt('06:00')->withoutOverlapping()->onOneServer();
Schedule::command('hms:send-arrival-reminders')->dailyAt('09:00')->withoutOverlapping()->onOneServer();
Schedule::command('hms:backup-database')->dailyAt('02:30')->withoutOverlapping()->onOneServer();
Schedule::command('model:prune')->dailyAt('03:15')->withoutOverlapping()->onOneServer();
