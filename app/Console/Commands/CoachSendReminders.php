<?php

namespace App\Console\Commands;

use App\Services\Coach\ReminderScheduler;
use Illuminate\Console\Command;

/** Sends the Coach App reminders that are due now. Runs every 5 minutes (routes/console.php). */
class CoachSendReminders extends Command
{
    protected $signature = 'coach:send-reminders';

    protected $description = 'Send Coach App reminders that are due (hydration, weigh-in, dose day, cycle)';

    public function handle(ReminderScheduler $scheduler): int
    {
        if (! config('coach.reminder_sending.enabled')) {
            $this->info('Coach reminders are switched off (COACH_REMINDERS_SEND is not true). Nothing sent.');

            return self::SUCCESS;
        }

        $this->info('Sent '.$scheduler->run().' reminder(s).');

        return self::SUCCESS;
    }
}