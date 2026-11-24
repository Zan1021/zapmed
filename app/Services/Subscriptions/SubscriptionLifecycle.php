<?php

namespace App\Services\Subscriptions;

use App\Enums\CycleStatus;
use App\Models\FollowUpRequest;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionFollowup;
use App\Models\SubscriptionPauseEvent;
use App\Services\Orders\OrderStatusMachine;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Subscription repeat-lifecycle service — behaviour parity with Mark's subscriptions module.
 *
 * Owns: scheduling renewal cycles, recording cycle OUTCOMES (placed/fulfilled/failed), the 3-strike
 * auto-cancel, follow-up (+180d) scheduling, and pause/resume with an audit trail.
 *
 * ⚠️ IMPORT-SAFETY (standing non-negotiable): this service NEVER calls a payment gateway. It records
 * the result of a charge that happened elsewhere (live checkout / PayFast webhook). Therefore importing
 * Contro subscription history — which calls recordImportedCycle() / seeds terminal rows — can NEVER
 * trigger a real charge. Live automation (runDueCycles placing NEW cycles) only ever creates 'scheduled'
 * cycles for POST-CUTOVER live subscriptions; it does not itself move money either.
 */
class SubscriptionLifecycle
{
    private function maxFailures(): int
    {
        return (int) config('subscriptions.max_consecutive_failures', 3);
    }

    private function intervalDays(Subscription $sub): int
    {
        // A repeat order remembers its pack length; a membership uses its plan's cadence.
        if ($sub->repeat_cycle_days) {
            return (int) $sub->repeat_cycle_days;
        }

        // Prefer the plan's cadence (months → days); fall back to the configured default.
        $months = $sub->plan?->cycle_frequency;

        return $months ? ($months * 30) : (int) config('subscriptions.default_interval_days', 30);
    }

    /** The given moment's DATE at the charge time (09:30 South African time), as UTC. */
    private function atChargeTime(CarbonInterface $when): Carbon
    {
        [$h, $m] = array_map('intval', explode(':', (string) config('subscriptions.charge_time', '09:30')));

        return Carbon::instance($when)->setTimezone(config('subscriptions.charge_timezone', 'Africa/Johannesburg'))->setTime($h, $m, 0)->utc();
    }

    /**
     * Create the next scheduled cycle for a subscription (idempotent: won't duplicate an open cycle).
     * This is a bookkeeping row only — no charge happens here.
     */
    public function scheduleNextCycle(Subscription $sub, ?\DateTimeInterface $when = null): SubscriptionCycle
    {
        return DB::transaction(function () use ($sub, $when) {
            $openCycle = $sub->cycles()
                ->whereIn('status', [CycleStatus::Scheduled->value, CycleStatus::Attempted->value, CycleStatus::Placed->value])
                ->first();
            if ($openCycle) {
                return $openCycle;
            }

            $nextSeq = ((int) $sub->cycles()->max('sequence_no')) + 1;
            $scheduledFor = $when ?? $this->atChargeTime(now()->addDays($this->intervalDays($sub)));

            $cycle = $sub->cycles()->create([
                'sequence_no' => $nextSeq,
                'status' => CycleStatus::Scheduled->value,
                'scheduled_for' => $scheduledFor,
            ]);

            $sub->forceFill(['next_run_at' => $scheduledFor])->save();

            return $cycle;
        });
    }

    /**
     * The FIRST repeat after a prescription: (pack days - buffer) days later at 09:30, so a 28 day pack repeats on day 23
     * and the patient has a 5 day delivery buffer. Later repeats follow every pack days. Remembers the pack length.
     */
    public function scheduleFirstRepeat(Subscription $sub, \DateTimeInterface $prescribedAt, int $packDays): SubscriptionCycle
    {
        if ($packDays < 1) {
            throw new RuntimeException('A pack must last at least one day.');
        }

        $buffer = (int) config('subscriptions.first_repeat_buffer_days', 5);
        $days = max(1, $packDays - $buffer);

        $sub->forceFill(['repeat_cycle_days' => $packDays])->save();

        return $this->scheduleNextCycle($sub->fresh(), $this->atChargeTime(Carbon::instance($prescribedAt)->addDays($days)));
    }

    /**
     * Mark a scheduled cycle as attempted then placed against an order. Represents the point where the
     * live flow created a renewal order (the charge itself is handled by the payments/checkout flow —
     * NOT here).
     */
    public function markPlaced(SubscriptionCycle $cycle, Order $order): SubscriptionCycle
    {
        $this->guardOpen($cycle);
        $cycle->update([
            'status' => CycleStatus::Placed->value,
            'order_id' => $order->id,
            'started_at' => $cycle->started_at ?? now(),
            'placed_at' => now(),
        ]);

        return $cycle;
    }

    /**
     * Record a SUCCESSFUL cycle (payment captured + fulfilled elsewhere). Resets the strike counter,
     * bumps completed_cycles, and schedules the next cycle. No money moves here.
     */
    public function recordSuccess(SubscriptionCycle $cycle): SubscriptionCycle
    {
        return DB::transaction(function () use ($cycle) {
            $cycle->update([
                'status' => CycleStatus::Fulfilled->value,
                'fulfilled_at' => now(),
                'placed_at' => $cycle->placed_at ?? now(),
            ]);

            $sub = $cycle->subscription;
            $sub->forceFill([
                'consecutive_failures' => 0,
                'completed_cycles' => ($sub->completed_cycles ?? 0) + 1,
                'status' => 'active',
            ])->save();

            $this->scheduleNextCycle($sub->fresh());

            return $cycle->fresh();
        });
    }

    /**
     * Record a FAILED cycle. Craig's rule: retry at 09:30 on three successive days; the THIRD failure PAUSES the order
     * (reason recorded, initiator "system") and blocks charges until the patient responds. It no longer cancels
     * (config subscriptions.after_max_failures = 'cancel' brings back the old rule). Failures 1 and 2 schedule the next
     * attempt as a bookkeeping row for tomorrow 09:30. No charge is issued here.
     */
    public function recordFailure(SubscriptionCycle $cycle, ?string $reason = null): SubscriptionCycle
    {
        return DB::transaction(function () use ($cycle, $reason) {
            $cycle->update([
                'status' => CycleStatus::PaymentFailed->value,
                'failed_at' => now(),
                'failure_reason' => $reason,
            ]);

            $sub = $cycle->subscription;
            $failures = ($sub->consecutive_failures ?? 0) + 1;
            $limitReached = $failures >= $this->maxFailures();
            $cancel = config('subscriptions.after_max_failures', 'pause') === 'cancel';

            if (! $limitReached) {
                $sub->forceFill(['consecutive_failures' => $failures, 'last_failure_at' => now(), 'status' => 'payment_failed'])->save();

                $retryAt = $this->atChargeTime(now()->addDays((int) config('subscriptions.retry_after_days', 1)));
                $retry = $this->scheduleNextCycle($sub->fresh(), $retryAt);
                // A retry charges the same amount as the repeat that failed.
                $keep = isset($cycle->metadata['amount_cents']) ? ['amount_cents' => $cycle->metadata['amount_cents']] : [];
                $retry->update(['metadata' => ['retry_of' => $cycle->id, 'attempt' => $failures + 1] + $keep]);

                return $cycle->fresh();
            }

            if ($cancel) {
                $sub->forceFill([
                    'consecutive_failures' => $failures,
                    'last_failure_at' => now(),
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_initiator' => 'system',
                    'cancellation_reason' => 'auto-cancelled after '.$this->maxFailures().' consecutive payment failures',
                ])->save();

                return $cycle->fresh();
            }

            $sub->forceFill(['consecutive_failures' => $failures, 'last_failure_at' => now(), 'status' => 'paused'])->save();
            SubscriptionPauseEvent::create([
                'subscription_id' => $sub->id,
                'paused_at' => now(),
                'paused_reason' => config('subscriptions.pause_reason_three_failures'),
                'paused_by' => null,
                'initiator' => 'system',
            ]);

            return $cycle->fresh();
        });
    }
    /** Cycles that are due to run now (scheduled + scheduled_for in the past). */
    public function dueCycles()
    {
        return SubscriptionCycle::due()->with('subscription')->get();
    }

    /**
     * Runner: for each due cycle, mark it attempted so the live checkout flow can pick it up. This does NOT charge, it
     * hands off to the live payment flow. Returns count moved.
     *
     * Only live subscriptions run: 'active', or 'payment_failed' while the 09:30 retries are still going. Paused,
     * cancelled and imported subscriptions are skipped, and so is any repeat blocked by its order (a required
     * follow-up that the doctor has not submitted yet). A blocked cycle stays scheduled and is looked at again next run.
     */
    public function runDueCycles(): int
    {
        $moved = 0;
        foreach ($this->dueCycles() as $cycle) {
            $sub = $cycle->subscription;
            if (! $sub || ! in_array($sub->status, ['active', 'payment_failed'], true)) {
                continue; // only live subscriptions renew
            }
            if ($this->repeatBlockedBy($sub) !== null) {
                continue;
            }
            $cycle->update(['status' => CycleStatus::Attempted->value, 'started_at' => now()]);
            $moved++;
        }

        return $moved;
    }

    /** The order status that blocks this subscription's next repeat (paused, cancelled, follow-up pending), or null. */
    public function repeatBlockedBy(Subscription $sub): ?string
    {
        $order = Order::where('source_type', 'subscription')->where('source_ref', (string) $sub->id)->first();
        if (! $order) {
            return null;
        }

        if (OrderStatusMachine::blocksRepeat((string) $order->status)) {
            return (string) $order->status;
        }

        // A doctor's follow-up request blocks the next repeat even when the order itself is still mid-delivery
        // and so was not moved to Pending follow-up request (Craig's guide 4.2: block until review is complete).
        return FollowUpRequest::open()->where('order_id', $order->id)->exists() ? 'PendingFollowUpRequest' : null;
    }
    // ---- follow-up (+180d) ----------------------------------------------------------------------

    /**
     * Schedule a follow-up for a Completed origin order (idempotent per order via unique index).
     * due_at = completion + configured days (default 180).
     */
    public function scheduleFollowup(Order $originOrder): SubscriptionFollowup
    {
        $existing = SubscriptionFollowup::where('origin_order_id', $originOrder->id)->whereNull('cancelled_at')->first();
        if ($existing) {
            return $existing;
        }

        $days = (int) config('subscriptions.followup_after_days', 180);

        return SubscriptionFollowup::create([
            'origin_order_id' => $originOrder->id,
            'patient_id' => $originOrder->patient_id,
            'due_at' => now()->addDays($days),
        ]);
    }

    /** Mark due follow-ups as notified (a scheduled sweep). Returns count. Sends nothing here itself. */
    public function markDueFollowupsNotified(): int
    {
        $due = SubscriptionFollowup::due()->get();
        foreach ($due as $f) {
            $f->update(['notified_at' => now()]);
        }

        return $due->count();
    }

    // ---- pause / resume -------------------------------------------------------------------------

    /**
     * Pause. A patient must give a reason. The initiator (patient, operations or system) is recorded, and any cycle that
     * has not been charged yet is cancelled so it stays in history but can never run. Paused orders do not charge or repeat.
     */
    public function pause(Subscription $sub, ?\DateTimeInterface $until = null, ?string $reason = null, ?int $actorId = null, ?string $initiator = null): void
    {
        if (! in_array($sub->status, ['active', 'payment_failed'], true)) {
            throw new RuntimeException("Cannot pause a subscription in status '{$sub->status}'.");
        }

        $initiator = $this->initiator($initiator, $actorId);
        if ($initiator === 'patient' && trim((string) $reason) === '') {
            throw new RuntimeException('Please tell us why you are pausing.');
        }

        DB::transaction(function () use ($sub, $until, $reason, $actorId, $initiator) {
            $this->cancelUnchargedCycles($sub, 'paused');
            $sub->forceFill(['status' => 'paused'])->save();
            SubscriptionPauseEvent::create([
                'subscription_id' => $sub->id,
                'paused_at' => now(),
                'paused_until' => $until,
                'paused_reason' => $reason,
                'paused_by' => $actorId,
                'initiator' => $initiator,
            ]);
        });
    }

    /**
     * Continue a paused order. Allowed within 90 days of the pause (Operations can override). The repeat cycle starts
     * again like a first repeat; the old cycles stay in history. Clears the failed-payment strikes: the patient has responded.
     */
    public function resume(Subscription $sub, ?int $actorId = null, bool $override = false): void
    {
        if ($sub->status !== 'paused') {
            throw new RuntimeException("Cannot resume a subscription in status '{$sub->status}'.");
        }

        $open = SubscriptionPauseEvent::where('subscription_id', $sub->id)->whereNull('resumed_at')->latest('paused_at')->first();
        $window = (int) config('subscriptions.pause_resume_window_days', 90);
        if (! $override && $open && $open->paused_at->copy()->addDays($window)->isPast()) {
            throw new RuntimeException("This order has been paused for more than {$window} days and can no longer be continued. Please contact us.");
        }

        DB::transaction(function () use ($sub, $actorId, $open) {
            $sub->forceFill(['status' => 'active', 'consecutive_failures' => 0])->save();
            $open?->update(['resumed_at' => now(), 'resumed_by' => $actorId]);

            $fresh = $sub->fresh();
            if ($fresh->repeat_cycle_days) {
                $this->scheduleFirstRepeat($fresh, now(), (int) $fresh->repeat_cycle_days);
            } else {
                $this->scheduleNextCycle($fresh);
            }
        });
    }

    /**
     * Cancel. A reason is always required and the initiator (patient, operations or system) is recorded. Cycles that have
     * not been charged yet are cancelled. Cancelled orders never charge or repeat.
     */
    public function cancel(Subscription $sub, string $reason, string $initiator, ?int $actorId = null): void
    {
        if ($sub->status === 'cancelled') {
            throw new RuntimeException('This subscription is already cancelled.');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('Please tell us why you are cancelling.');
        }
        $initiator = $this->initiator($initiator, $actorId);

        DB::transaction(function () use ($sub, $reason, $initiator, $actorId) {
            $this->cancelUnchargedCycles($sub, 'cancelled');
            $sub->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'ends_at' => $sub->current_period_end,
                'cancellation_reason' => $reason,
                'cancelled_initiator' => $initiator,
                'cancelled_by' => $actorId,
            ])->save();

            // A cancelled order that was paused is no longer waiting to be continued.
            SubscriptionPauseEvent::where('subscription_id', $sub->id)->whereNull('resumed_at')->update(['resumed_at' => now(), 'resumed_by' => $actorId]);
        });
    }

    /** Cycles not yet charged (scheduled or handed to the payment flow) become cancelled history rows. */
    private function cancelUnchargedCycles(Subscription $sub, string $why): void
    {
        $sub->cycles()->whereIn('status', [CycleStatus::Scheduled->value, CycleStatus::Attempted->value])->get()->each(
            fn (SubscriptionCycle $c) => $c->update([
                'status' => CycleStatus::Cancelled->value,
                'cancelled_at' => now(),
                'metadata' => array_merge((array) $c->metadata, ['cancelled_because' => $why]),
            ])
        );
    }

    private function initiator(?string $initiator, ?int $actorId): string
    {
        $initiator ??= $actorId ? 'operations' : 'system';

        if (! in_array($initiator, config('subscriptions.initiators', ['patient', 'operations', 'system']), true)) {
            throw new RuntimeException("Unknown initiator '{$initiator}'.");
        }

        return $initiator;
    }
    // ---- import (history reconstruction — NO charges) -------------------------------------------

    /**
     * Record an imported historical cycle in a TERMINAL state. Used by the Contro reconciler to
     * reconstruct past renewals faithfully WITHOUT ever touching a gateway. Never schedules a next
     * cycle and never mutates live billing counters beyond the historical fact.
     */
    public function recordImportedCycle(Subscription $sub, int $sequenceNo, CycleStatus $status, array $context = []): SubscriptionCycle
    {
        if (! $status->isTerminal()) {
            throw new RuntimeException('Imported cycles must be terminal (fulfilled/payment_failed/cancelled) — never live.');
        }

        return $sub->cycles()->create([
            'sequence_no' => $sequenceNo,
            'status' => $status->value,
            'order_id' => $context['order_id'] ?? null,
            'placed_at' => $context['placed_at'] ?? null,
            'fulfilled_at' => $status === CycleStatus::Fulfilled ? ($context['fulfilled_at'] ?? null) : null,
            'failed_at' => $status === CycleStatus::PaymentFailed ? ($context['failed_at'] ?? null) : null,
            'cancelled_at' => $status === CycleStatus::Cancelled ? ($context['cancelled_at'] ?? null) : null,
            'scheduled_for' => $context['scheduled_for'] ?? now(),
            'failure_reason' => $context['failure_reason'] ?? null,
            'upstream_id' => $context['upstream_id'] ?? null,
        ]);
    }

    private function guardOpen(SubscriptionCycle $cycle): void
    {
        if ($cycle->status->isTerminal()) {
            throw new RuntimeException("Cycle #{$cycle->sequence_no} is already {$cycle->status->value}.");
        }
    }
}
