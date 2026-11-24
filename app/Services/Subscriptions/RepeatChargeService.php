<?php

namespace App\Services\Subscriptions;

use App\Enums\CycleStatus;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Services\PayFastService;
use Illuminate\Support\Facades\Log;

/**
 * Charges a due repeat on the patient's saved PayFast token (ad hoc), and records what PayFast tells us afterwards.
 *
 * Flow (all at 09:30 SA via `subscriptions:run-due`):
 *   1. SubscriptionLifecycle::runDueCycles() marks due cycles "attempted" (it skips paused / cancelled orders and any
 *      repeat blocked by a pending follow-up).
 *   2. chargeAttempted() here submits each attempted cycle to PayFast. An accepted request moves the cycle to "placed"
 *      (money is only confirmed later by the ITN); a refused request is a failed payment (retry tomorrow 09:30, third
 *      failure pauses the order, see SubscriptionLifecycle::recordFailure).
 *   3. The ITN (SubscriptionController::notify) confirms: COMPLETE records success and schedules the next repeat,
 *      FAILED records a failure.
 *
 * Safety:
 *  - Does nothing unless SUBSCRIPTIONS_CHARGE_ENABLED=true (config subscriptions.charging.enabled).
 *  - Only subscriptions with charge_mode 'adhoc'. Memberships (PayFast bills them itself) are NEVER charged here.
 *  - The charge reference is saved on the cycle BEFORE PayFast is called and a cycle with a reference is never
 *    submitted again, so a crash or a re-run cannot charge twice.
 *  - If PayFast does not answer (timeout, network, 5xx) nobody knows whether the money was taken: the cycle stays
 *    "placed", flagged charge_unknown, and the ITN decides. It is never counted as a failure and never retried.
 */
class RepeatChargeService
{
    public function __construct(
        private readonly PayFastService $payfast,
        private readonly SubscriptionLifecycle $lifecycle,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('subscriptions.charging.enabled', false);
    }

    /** @return array{submitted:int,failed:int,unknown:int,skipped:int} */
    public function chargeAttempted(): array
    {
        $out = ['submitted' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0];

        if (! $this->enabled()) {
            return $out;
        }

        foreach (SubscriptionCycle::where('status', CycleStatus::Attempted->value)->with('subscription.plan')->orderBy('id')->get() as $cycle) {
            $out[$this->charge($cycle)]++;
        }

        return $out;
    }

    /** @return 'submitted'|'failed'|'unknown'|'skipped' */
    public function charge(SubscriptionCycle $cycle): string
    {
        $sub = $cycle->subscription;

        if (! $this->enabled() || ! $sub || $sub->charge_mode !== 'adhoc' || $cycle->status !== CycleStatus::Attempted) {
            return 'skipped';
        }
        if (! in_array($sub->status, ['active', 'payment_failed'], true)) {
            return 'skipped';
        }
        if (! empty($cycle->metadata['charge_reference'])) {
            return 'skipped';   // already submitted once
        }
        if (! $sub->payfast_token) {
            Log::warning('repeat charge: subscription has no PayFast token', ['subscription' => $sub->id, 'cycle' => $cycle->id]);

            return 'skipped';
        }

        $cents = $this->amountCents($cycle, $sub);
        if ($cents <= 0) {
            Log::warning('repeat charge: nothing to charge', ['subscription' => $sub->id, 'cycle' => $cycle->id]);

            return 'skipped';
        }

        // Save the reference first: a repeat can only ever be submitted once.
        $attempt = (int) ($cycle->metadata['attempt'] ?? 1);
        $reference = "RPT-{$cycle->id}-{$attempt}";
        $cycle->update(['metadata' => array_merge((array) $cycle->metadata, ['charge_reference' => $reference, 'amount_cents' => $cents])]);

        $result = $this->payfast->adhocCharge((string) $sub->payfast_token, $cents, 'Zapmed repeat order', $reference);

        if ($result['unknown']) {
            $cycle->update([
                'status' => CycleStatus::Placed->value,
                'placed_at' => now(),
                'metadata' => array_merge((array) $cycle->fresh()->metadata, ['charge_unknown' => true, 'charge_note' => $result['message']]),
            ]);

            return 'unknown';
        }

        if (! $result['ok']) {
            $this->lifecycle->recordFailure($cycle->fresh(), $result['message']);

            return 'failed';
        }

        $cycle->update(['status' => CycleStatus::Placed->value, 'placed_at' => now(), 'started_at' => $cycle->started_at ?? now()]);

        return 'submitted';
    }

    /**
     * PayFast told us (ITN) what happened to a charge we submitted. Idempotent: a repeated ITN, or one for a cycle
     * that is already settled, changes nothing.
     */
    public function recordItn(string $reference, string $payfastStatus, int $amountCents): bool
    {
        $cycle = SubscriptionCycle::where('metadata->charge_reference', $reference)->first();
        if (! $cycle) {
            return false;
        }
        if (! in_array($cycle->status, [CycleStatus::Placed, CycleStatus::Attempted], true)) {
            return true;   // settled already
        }

        $sub = $cycle->subscription;

        if ($payfastStatus === 'COMPLETE') {
            $sub->recordPayment($amountCents);
            $this->lifecycle->recordSuccess($cycle);
        } elseif ($payfastStatus === 'FAILED') {
            $this->lifecycle->recordFailure($cycle, 'PayFast reported the payment as failed');
        }

        return true;
    }

    /**
     * Charges submitted more than N days ago that PayFast never confirmed (or flagged unknown). They are never retried
     * by the system: someone must look them up in PayFast. Returns the cycles so the caller can report them.
     *
     * @return \Illuminate\Support\Collection<int,SubscriptionCycle>
     */
    public function stalePlaced()
    {
        $days = (int) config('subscriptions.charging.stale_placed_days', 3);

        return SubscriptionCycle::where('status', CycleStatus::Placed->value)
            ->whereNotNull('metadata->charge_reference')
            ->where('placed_at', '<', now()->subDays($days))
            ->get();
    }

    private function amountCents(SubscriptionCycle $cycle, Subscription $sub): int
    {
        // A repeat that was already submitted, and every retry of one, keeps the amount it was first submitted with.
        if ((! empty($cycle->metadata['charge_reference']) || ! empty($cycle->metadata['retry_of'])) && isset($cycle->metadata['amount_cents'])) {
            return (int) $cycle->metadata['amount_cents'];
        }

        // A repeat order can carry its own amount (medication price); otherwise the plan price.
        $base = (int) ($cycle->metadata['amount_cents'] ?? $sub->plan?->price ?? 0);

        // Operations may have put a coupon on this order's monthly SERVICE fee (Craig's guide 6.2). It is capped at the
        // service fee, so the medication part is never reduced.
        return max(0, $base - app(\App\Services\Operations\OrderControls::class)->discountCentsForSubscription($sub));
    }
}
