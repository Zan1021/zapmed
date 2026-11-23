<?php

namespace App\Console\Commands;

use App\Services\Coach\CoachCalls;
use App\Services\Coach\ReminderScheduler;
use Illuminate\Console\Command;

/**
 * Sends the Coach App reminders that are due now: member reminders (master switch COACH_REMINDERS_SEND) and the
 * reminder an hour before a booked call (COACH_CALL_NOTIFY). Runs every 5 minutes (routes/console.php).
 */
class CoachSendReminders extends Command
{
    protected $signature = 'coach:send-reminders';

    protected $description = 'Send Coach App reminders that are due (hydration, weigh-in, dose day, cycle, calls)';

    public function handle(ReminderScheduler $scheduler, CoachCalls $calls): int
    {
        $remindersOn = (bool) config('coach.reminder_sending.enabled');
        $callsOn = (bool) config('coach.calls.notify_enabled');

        if (! $remindersOn && ! $callsOn) {
            $this->info('Coach reminders are switched off (COACH_REMINDERS_SEND and COACH_CALL_NOTIFY are not true). Nothing sent.');

            return self::SUCCESS;
        }

        $n = $remindersOn ? $scheduler->run() : 0;
        $c = $callsOn ? $calls->sendDueReminders() : 0;

        $this->info("Sent {$n} reminder(s) and {$c} call reminder(s).");

        return self::SUCCESS;
    }
}