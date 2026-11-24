<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderAuditEvent;
use App\Models\OrderDiscount;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Finance\FinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What Operations may do to one order (Craig's guide 6.2). Admin only. Everything leaves an audit row.
 *
 *  - Coupon on the monthly service fee: a percentage or a rand amount off, for 1 to 6 months or until switched off.
 *    It is capped at the order's service fee, so the medication fee can never be discounted. One active coupon per order.
 *  - Manual payment record: for a PayFast link made outside the site. A completed one is recorded in the finance ledger and
 *    moves a waiting order to Payment received. The same transaction can only be recorded once.
 */
class OrderControls
{
    public function __construct(private readonly FinanceService $finance)
    {
    }

    private function tz(): string
    {
        return config('doctor.time.timezone');
    }

    private function assertOps(User $user): void
    {
        abort_unless($user->role === UserRole::Admin, 403);
    }

    // ---- coupon on the service fee -------------------------------------------------------------------------------

    /**
     * @param int|null $months 1 to 6, or null for "until switched off"
     * @param int $value percent: whole percent 1 to 100 (stored as basis points); fixed: cents off per month
     */
    public function addDiscount(User $ops, Order $order, string $type, int $value, ?int $months, string $reason): OrderDiscount
    {
        $this->assertOps($ops);

        $fee = (int) $order->service_fee_minor;
        if ($fee <= 0) {
            throw ValidationException::withMessages(['order' => 'This order has no service fee to reduce.']);
        }
        if (! in_array($type, ['percent', 'fixed'], true)) {
            throw ValidationException::withMessages(['type' => 'Choose a percentage or an amount.']);
        }
        if ($type === 'percent' && ($value < 1 || $value > 100)) {
            throw ValidationException::withMessages(['value' => 'A percentage must be between 1 and 100.']);
        }
        if ($type === 'fixed' && ($value < 1 || $value > $fee)) {
            throw ValidationException::withMessages(['value' => 'The amount must be more than 0 and no more than the service fee (R'.number_format($fee / 100, 2).').']);
        }
        if ($months !== null && ($months < 1 || $months > 6)) {
            throw ValidationException::withMessages(['months' => 'Choose 1 to 6 months, or until switched off.']);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Give a reason.']);
        }
        if (OrderDiscount::where('order_id', $order->id)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['order' => 'This order already has a coupon. Switch it off first.']);
        }

        $start = CarbonImmutable::now($this->tz())->startOfDay();

        return DB::transaction(function () use ($ops, $order, $type, $value, $months, $reason, $start) {
            $discount = OrderDiscount::create([
                'order_id' => $order->id,
                'type' => $type,
                'value' => $type === 'percent' ? $value * 100 : $value,
                'months' => $months,
                'starts_on' => $start->format('Y-m-d'),
                'ends_on' => $months ? $start->addMonthsNoOverflow($months)->format('Y-m-d') : null,
                'is_active' => true,
                'reason' => mb_substr($reason, 0, 255),
                'created_by' => $ops->id,
            ]);

            $this->audit($order, $ops, 'discount_added', sprintf(
                'Service fee coupon: %s off %s. %s',
                $type === 'percent' ? $value.'%' : 'R'.number_format($value / 100, 2),
                $months ? "for {$months} month".($months > 1 ? 's' : '') : 'until switched off',
                $reason,
            ), ['discount_id' => $discount->id]);

            return $discount;
        });
    }

    public function disableDiscount(User $ops, OrderDiscount $discount, string $reason): OrderDiscount
    {
        $this->assertOps($ops);

        if (! $discount->is_active) {
            throw ValidationException::withMessages(['order' => 'This coupon is already switched off.']);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Give a reason.']);
        }

        $discount->update(['is_active' => false, 'disabled_by' => $ops->id, 'disabled_at' => now(), 'disabled_reason' => mb_substr($reason, 0, 255)]);
        $this->audit($discount->order, $ops, 'discount_disabled', 'Service fee coupon switched off. '.$reason, ['discount_id' => $discount->id]);

        return $discount->fresh();
    }

    /** The coupon that applies on a date, if any: active and inside its months. */
    public function activeDiscount(Order $order, ?CarbonImmutable $on = null): ?OrderDiscount
    {
        $day = ($on ?? CarbonImmutable::now($this->tz()))->format('Y-m-d');

        return OrderDiscount::where('order_id', $order->id)->where('is_active', true)
            ->whereDate('starts_on', '<=', $day)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>', $day))
            ->latest('id')->first();
    }

    /** Cents off the SERVICE fee on a date. Never more than the service fee, so the medication fee is never touched. */
    public function discountCents(Order $order, ?CarbonImmutable $on = null): int
    {
        $d = $this->activeDiscount($order, $on);
        $fee = (int) $order->service_fee_minor;
        if (! $d || $fee <= 0) {
            return 0;
        }

        $off = $d->type === 'percent' ? intdiv($fee * $d->value, 10000) : $d->value;

        return max(0, min($off, $fee));
    }

    /** The same, found from a repeat subscription. */
    public function discountCentsForSubscription(Subscription $sub, ?CarbonImmutable $on = null): int
    {
        $order = Order::where('source_type', 'subscription')->where('source_ref', (string) $sub->id)->first();

        return $order ? $this->discountCents($order, $on) : 0;
    }

    // ---- manual payment record -----------------------------------------------------------------------------------

    /**
     * Record a payment made outside the site (for example a PayFast link made by hand).
     *
     * @param string $result completed | failed
     */
    public function recordManualPayment(User $ops, Order $order, string $transactionId, int $amountCents, string $paidAt, string $reference, string $result): Payment
    {
        $this->assertOps($ops);

        $transactionId = trim($transactionId);
        $reference = trim($reference);
        if ($transactionId === '') {
            throw ValidationException::withMessages(['transaction' => 'Enter the transaction ID.']);
        }
        if ($amountCents < 1 || $amountCents > 10_000_000) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount in rand, more than 0.']);
        }
        if (! in_array($result, ['completed', 'failed'], true)) {
            throw ValidationException::withMessages(['result' => 'Choose completed or failed.']);
        }
        $when = $this->when($paidAt);
        if ($when->isFuture()) {
            throw ValidationException::withMessages(['paid_at' => 'The payment time cannot be in the future.']);
        }

        $key = 'manual:payfast:'.$transactionId;
        if (Payment::where('idempotency_key', $key)->exists()) {
            throw ValidationException::withMessages(['transaction' => 'A payment with that transaction ID is already recorded.']);
        }

        return DB::transaction(function () use ($ops, $order, $transactionId, $amountCents, $when, $reference, $result, $key) {
            $payment = Payment::create([
                'patient_id' => $order->patient_id,
                'order_id' => $order->id,
                'provider' => 'payfast',
                'provider_reference' => $transactionId,
                'idempotency_key' => $key,
                'amount' => $amountCents,
                'currency' => 'ZAR',
                'status' => $result,
                'payment_type' => 'manual',
                'description' => 'Recorded by hand. Reference: '.($reference !== '' ? mb_substr($reference, 0, 120) : 'none'),
                'failure_reason' => $result === 'failed' ? 'Recorded as failed by Operations' : null,
                'paid_at' => $result === 'completed' ? $when->utc() : null,   // stored as UTC; Laravel does not convert a Carbon on its way in
            ]);

            if ($result === 'completed') {
                $this->finance->recordCashFromPayment($payment, $order->service_category, $ops->id);

                if ($order->status === 'PendingPayment' && \App\Services\Orders\OrderStatusMachine::isTransitionAllowed('PendingPayment', 'PaymentReceived', 'payment')) {
                    $order->transitionTo('PaymentReceived', 'payment', ['notes' => 'Payment recorded by hand', 'triggered_by' => $ops->id]);
                }
            }

            $this->audit($order, $ops, 'manual_payment', sprintf('Payment recorded by hand: R%s, %s, transaction %s.', number_format($amountCents / 100, 2), $result, $transactionId), ['payment_id' => $payment->id]);

            return $payment;
        });
    }

    // ---- reset the repeat cycle ----------------------------------------------------------------------------------

    /**
     * Reset a running repeat order's cycle and counters (Craig's guide 6.4): uncharged repeats are cancelled but kept in history,
     * failed-payment strikes are cleared and the first repeat is worked out again from today. A reason is required and the old and
     * new repeat dates are written to the audit trail.
     */
    public function resetRepeat(User $ops, Order $order, string $reason, ?int $packDays = null): \App\Models\SubscriptionCycle
    {
        $this->assertOps($ops);

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Give a reason.']);
        }

        $sub = Subscription::where('id', (int) $order->source_ref)->where('user_id', $order->patient_id)->first();
        if ($order->source_type !== 'subscription' || ! $sub) {
            throw ValidationException::withMessages(['order' => 'This is not a repeat order.']);
        }

        $old = $sub->cycles()->where('status', \App\Enums\CycleStatus::Scheduled->value)->orderBy('scheduled_for')->first();

        try {
            $new = app(\App\Services\Subscriptions\SubscriptionLifecycle::class)->restartRepeatCycle($sub, $packDays);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['order' => $e->getMessage()]);
        }

        $fmt = fn ($c) => $c?->scheduled_for?->setTimezone($this->tz())->format('Y-m-d') ?? 'none';
        $this->audit($order, $ops, 'repeat_reset', sprintf('Repeat reset: next repeat %s, was %s. %s', $fmt($new), $fmt($old), $reason), [
            'subscription_id' => $sub->id, 'old_cycle_id' => $old?->id, 'new_cycle_id' => $new->id, 'pack_days' => $sub->fresh()->repeat_cycle_days,
        ]);

        return $new;
    }
    // ---- audit ---------------------------------------------------------------------------------------------------

    public function audit(Order $order, ?User $user, string $event, string $summary, array $data = []): OrderAuditEvent
    {
        return OrderAuditEvent::create([
            'order_id' => $order->id,
            'user_id' => $user?->id,
            'event' => $event,
            'summary' => mb_substr($summary, 0, 255),
            'data' => $data ?: null,
            'created_at' => now(),
        ]);
    }

    private function when(string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value, $this->tz());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['paid_at' => 'Enter the date and time of the payment.']);
        }
    }
}
