<?php

namespace App\Console\Commands;

use App\Services\Subscriptions\SubscriptionLifecycle;
use Illuminate\Console\Command;

/**
 * Run due subscription cycles + follow-ups (Task 6, subscriptions module parity).
 *
 * The reference had two daily jobs (cycle-runner 03:00, followup-runner 04:00); we run both sweeps
 * here on the Laravel scheduler (see routes/console.php). NEITHER moves money: the cycle runner hands
 * due cycles to the live checkout flow (marks them attempted), and the follow-up sweep marks reminders
 * as notified. Imported/cancelled subscriptions are skipped, so this is safe to run after a backfill.
 *
 *   php artisan subscriptions:run-due
 */
class SubscriptionsRunDue extends Command
{
    protected $signature = 'subscriptions:run-due';

    protected $description = 'Advance due subscription renewal cycles and follow-ups (no charges)';

    public function handle(SubscriptionLifecycle $lifecycle): int
    {
        $cycles = $lifecycle->runDueCycles();
        $followups = $lifecycle->markDueFollowupsNotified();

        $this->info("Subscriptions run complete — {$cycles} cycle(s) advanced, {$followups} follow-up(s) notified.");

        return self::SUCCESS;
    }
}
