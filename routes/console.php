<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('appointments:send-reminders')->everyFiveMinutes();
Schedule::command('prescriptions:remind')->dailyAt('09:00');
Schedule::command('alerts:scan')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('subscriptions:run-due')->dailyAt('03:00')->withoutOverlapping();
