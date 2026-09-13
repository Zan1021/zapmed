<?php

namespace App\Services\Subscriptions;

use App\Enums\CycleStatus;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionFollowup;
use App\Models\SubscriptionPauseEvent;
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
        // Prefer the plan's cadence (months → days); fall back to the configured default.
        $months = $sub->plan?->cycle_frequency;

        return $months ? ($months * 30) : (int) config('subscriptions.default_interval_days', 30);
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
            $scheduledFor = $when ?? now()->addDays($this->intervalDays($sub));

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
     * Record a FAILED cycle. Increments consecutive_failures; on the 3rd consecutive strike the
     * subscription is auto-cancelled (parity with Dave's 3-strike rule). Marks the subscription
     * payment_failed otherwise. No retry charge is issued here.
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

            $sub->forceFill([
                'consecutive_failures' => $failures,
                'last_failure_at' => now(),
                'status' => $failures >= $this->maxFailures() ? 'cancelled' : 'payment_failed',
                'cancelled_at' => $failures >= $this->maxFailures() ? now() : $sub->cancelled_at,
                'cancellation_reason' => $failures >= $this->maxFailures()
                    ? 'auto-cancelled after ' . $this->maxFailures() . ' consecutive payment failures'
                    : $sub->cancellation_reason,
            ])->save();

            return $cycle->fresh();
        });
    }

    /** Cycles that are due to run now (scheduled + scheduled_for in the past). */
    public function dueCycles()
    {
        return SubscriptionCycle::due()->with('subscription')->get();
    }

    /**
     * Runner: for each due cycle on an ACTIVE subscription, mark it attempted so the live checkout flow
     * can pick it up. This does NOT charge — it hands off to the live payment flow. Returns count moved.
     * Imported/cancelled subscriptions are skipped, so a backfill can never wake a charge.
     */
    public function runDueCycles(): int
    {
        $moved = 0;
        foreach ($this->dueCycles() as $cycle) {
            $sub = $cycle->subscription;
            if (! $sub || $sub->status !== 'active') {
                continue; // only live, active subscriptions renew
            }
            $cycle->update(['status' => CycleStatus::Attempted->value, 'started_at' => now()]);
            $moved++;
        }

        return $moved;
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

    public function pause(Subscription $sub, ?\DateTimeInterface $until = null, ?string $reason = null, ?int $actorId = null): void
    {
        if ($sub->status !== 'active') {
            throw new RuntimeException("Cannot pause a subscription in status '{$sub->status}'.");
        }

        DB::transaction(function () use ($sub, $until, $reason, $actorId) {
            $sub->forceFill(['status' => 'paused'])->save();
            SubscriptionPauseEvent::create([
                'subscription_id' => $sub->id,
                'paused_at' => now(),
                'paused_until' => $until,
                'paused_reason' => $reason,
                'paused_by' => $actorId,
            ]);
        });
    }

    public function resume(Subscription $sub, ?int $actorId = null): void
    {
        if ($sub->status !== 'paused') {
            throw new RuntimeException("Cannot resume a subscription in status '{$sub->status}'.");
        }

        DB::transaction(function () use ($sub, $actorId) {
            $sub->forceFill(['status' => 'active', 'next_run_at' => now()->addDays($this->intervalDays($sub))])->save();

            $open = SubscriptionPauseEvent::where('subscription_id', $sub->id)->whereNull('resumed_at')->latest('paused_at')->first();
            $open?->update(['resumed_at' => now(), 'resumed_by' => $actorId]);

            $this->scheduleNextCycle($sub->fresh());
        });
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
