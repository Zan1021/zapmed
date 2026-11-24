<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('appointments:send-reminders')->everyFiveMinutes();
Schedule::command('coach:send-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('coach:assign-pending')->hourly()->withoutOverlapping();
Schedule::command('prescriptions:remind')->dailyAt('09:00');
// Doctor follow-ups: prompt the patient to book a few days before it is due (messages OFF until DOCTOR_FOLLOWUP_NOTIFY).
Schedule::command('followups:prompt')->dailyAt('09:00')->timezone('Africa/Johannesburg')->withoutOverlapping();
// Doctor authorisations that have ended are marked expired before the 09:30 repeat run.
Schedule::command('authorisations:expire')->dailyAt('08:00')->timezone('Africa/Johannesburg')->withoutOverlapping();
Schedule::command('alerts:scan')->everyFiveMinutes()->withoutOverlapping();
// Repeats and payment retries run at 09:30 South African time (Craig's guide), not 03:00 server time.
Schedule::command('subscriptions:run-due')->dailyAt('09:30')->timezone('Africa/Johannesburg')->withoutOverlapping();
Schedule::command('analytics:snapshot')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('compliance:scan')->dailyAt('01:00')->withoutOverlapping();

// Charge-at-booking: free up slots whose payment hold expired (patient didn't complete PayFast).
Schedule::command('onboarding:release-expired-reservations')->everyFiveMinutes()->withoutOverlapping();
