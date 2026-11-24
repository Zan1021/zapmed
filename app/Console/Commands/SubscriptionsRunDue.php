<?php

namespace App\Console\Commands;

use App\Services\Subscriptions\RepeatChargeService;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Illuminate\Console\Command;

/**
 * Run due subscription cycles + follow-ups (Task 6, subscriptions module parity), and charge due repeats.
 *
 * Runs once a day at 09:30 South African time (routes/console.php), the repeat and retry time in Craig's guide.
 *
 *  1. The cycle runner hands due cycles to the payment flow (marks them attempted). It skips paused and cancelled
 *     orders, imported subscriptions, and any repeat blocked by a pending follow-up.
 *  2. The follow-up sweep marks reminders as notified.
 *  3. Ad hoc repeat charges (RepeatChargeService): ONLY when SUBSCRIPTIONS_CHARGE_ENABLED=true, and only for
 *     subscriptions with charge_mode 'adhoc'. With the switch off (the default) this command never moves money.
 *
 *   php artisan subscriptions:run-due
 */
class SubscriptionsRunDue extends Command
{
    protected $signature = 'subscriptions:run-due';

    protected $description = 'Advance due subscription renewal cycles and follow-ups (charges only when SUBSCRIPTIONS_CHARGE_ENABLED=true)';

    public function handle(SubscriptionLifecycle $lifecycle, RepeatChargeService $charger): int
    {
        $cycles = $lifecycle->runDueCycles();
        $followups = $lifecycle->markDueFollowupsNotified();

        $this->info("Subscriptions run complete: {$cycles} cycle(s) advanced, {$followups} follow-up(s) notified.");

        if ($charger->enabled()) {
            $r = $charger->chargeAttempted();
            $this->info("Repeat charges: {$r['submitted']} submitted, {$r['failed']} refused, {$r['unknown']} unknown (waiting for PayFast), {$r['skipped']} skipped.");

            foreach ($charger->stalePlaced() as $stale) {
                $this->warn("Charge {$stale->metadata['charge_reference']} has had no confirmation from PayFast since {$stale->placed_at}. Please check it in PayFast.");
            }
        }

        return self::SUCCESS;
    }
}
