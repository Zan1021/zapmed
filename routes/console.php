<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('appointments:send-reminders')->everyFiveMinutes();
Schedule::command('coach:send-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('prescriptions:remind')->dailyAt('09:00');
Schedule::command('alerts:scan')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('subscriptions:run-due')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('analytics:snapshot')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('compliance:scan')->dailyAt('01:00')->withoutOverlapping();

// Charge-at-booking: free up slots whose payment hold expired (patient didn't complete PayFast).
Schedule::command('onboarding:release-expired-reservations')->everyFiveMinutes()->withoutOverlapping();
