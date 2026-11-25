<?php

namespace App\Services\Subscriptions;

use App\Enums\CycleStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPlan;
use App\Services\Commerce\LiveOrderBridge;
use App\Services\PayFastService;
use App\Services\PharmacyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Joins a signed prescription that has repeats to the repeat engine (Craig's guide 3.2).
 *
 *  1. The patient's FIRST medication payment saves their card (PayFast tokenisation, only when
 *     subscriptions.repeat_orders.tokenise_first_payment is on). When that payment is confirmed, start() makes the repeat order:
 *     a subscription on the hidden "medication-repeat" plan, charged by us (charge_mode adhoc) on the saved token, with the first
 *     repeat (pack days - 5) days after the prescription at 09:30.
 *  2. When a repeat charge is confirmed, onRepeatPaid() records the payment, uses up one repeat, makes the refill (a copy of the
 *     signed prescription, so the pharmacy log and its "never send twice" rules apply) and sends it to the pharmacy. After the
 *     last repeat the order finishes: no further repeat is scheduled.
 *
 * Nothing here moves money. The charge itself is RepeatChargeService, which stays OFF until SUBSCRIPTIONS_CHARGE_ENABLED=true.
 */
class PrescriptionRepeats
{
    public const PLAN_SLUG = 'medication-repeat';

    public function __construct(
        private readonly SubscriptionLifecycle $lifecycle,
        private readonly LiveOrderBridge $bridge,
    ) {
    }

    /** The hidden plan every medication repeat order sits on. Not offered to patients (inactive). */
    public function plan(): SubscriptionPlan
    {
        return SubscriptionPlan::firstOrCreate(
            ['slug' => self::PLAN_SLUG],
            [
                'name' => 'Medication repeat', 'description' => 'Repeats of a signed prescription, charged on the card saved at the first payment.',
                'price' => 0, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1, 'consultations_per_month' => 0,
                'includes_messaging' => false, 'is_active' => false, 'sort_order' => 999,
            ],
        );
    }

    public function tokeniseFirstPayment(): bool
    {
        return (bool) config('subscriptions.repeat_orders.tokenise_first_payment', false);
    }

    /** The prescription a medication payment is for ("Medication - RX-..."), first fill only. */
    public function prescriptionFor(Payment $payment): ?Prescription
    {
        $description = (string) $payment->description;
        if (! str_starts_with($description, 'Medication - ')) {
            return null;
        }

        return Prescription::where('reference', substr($description, strlen('Medication - ')))->first();
    }

    /** A signed prescription with repeats left that has no repeat order yet. */
    public function wantsRepeats(Prescription $rx): bool
    {
        return $rx->status !== 'cancelled'
            && $rx->signed_at !== null
            && $rx->refill_of_id === null
            && $rx->hasRefillsRemaining()
            && ! $rx->isExpired()
            && $this->subscriptionFor($rx) === null;
    }

    /** True when the payment form for this payment should save the card. */
    public function shouldTokenise(Payment $payment): bool
    {
        if (! $this->tokeniseFirstPayment()) {
            return false;
        }
        $rx = $this->prescriptionFor($payment);

        return $rx !== null && $this->wantsRepeats($rx);
    }

    public function subscriptionFor(Prescription $rx): ?Subscription
    {
        return Subscription::where('user_id', $rx->patient_id)
            ->where('metadata->prescription_id', $rx->id)
            ->latest('id')
            ->first();
    }

    /** How long one fill lasts: the longest medication line, else the configured default. */
    public function packDays(Prescription $rx): int
    {
        $longest = (int) $rx->items()->max('duration_days');

        return $longest > 0 ? $longest : (int) config('subscriptions.repeat_orders.default_pack_days', 28);
    }

    /**
     * The first medication payment is confirmed and PayFast sent a card token: make the repeat order. Safe to call twice.
     * Returns null when this prescription does not qualify or there is no token.
     */
    public function start(Prescription $rx, ?string $token, ?Payment $firstPayment = null): ?Subscription
    {
        if (! $token || ! $this->wantsRepeats($rx)) {
            return null;
        }

        $sub = DB::transaction(function () use ($rx, $token, $firstPayment) {
            $pack = $this->packDays($rx);
            $sub = Subscription::create([
                'user_id' => $rx->patient_id,
                'subscription_plan_id' => $this->plan()->id,
                'status' => 'active',
                'payment_reference' => PayFastService::generateSubscriptionReference(),
                'payfast_token' => $token,
                'charge_mode' => 'adhoc',
                'starts_at' => now(),
                'repeat_cycle_days' => $pack,
                'metadata' => ['prescription_id' => $rx->id, 'origin' => 'prescription', 'first_payment_id' => $firstPayment?->id],
            ]);
            if ($firstPayment) {
                $sub->recordPayment((int) $firstPayment->amount);
            }

            $cycle = $this->lifecycle->scheduleFirstRepeat($sub, $rx->signed_at ?? now(), $pack);
            // Each repeat is charged the medication price of the signed prescription.
            $cycle->update(['metadata' => array_merge((array) $cycle->metadata, ['amount_cents' => (int) $rx->total_amount])]);

            return $sub;
        });

        $this->linkOrder($sub, $rx);

        return $sub;
    }

    /** The order the patient sees (and Operations control) for this repeat series. */
    private function linkOrder(Subscription $sub, Prescription $rx): void
    {
        try {
            $order = $this->bridge->onSubscriptionStarted($sub);
            $origin = Order::where('patient_id', $rx->patient_id)->where('pharmacy_script_ref', $rx->pharmacy_script_ref)->whereNotNull('pharmacy_script_ref')->first();

            $order->forceFill([
                'status' => 'PharmacyProcessing',
                'total_minor' => (int) $rx->total_amount,
                'service_category' => $origin?->service_category ?: $order->service_category,
                'pharmacy_script_ref' => $rx->pharmacy_script_ref,
                'metadata' => array_merge((array) $order->metadata, ['prescription_id' => $rx->id, 'origin' => 'prescription']),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('repeat order: could not link the order', ['subscription' => $sub->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * PayFast confirmed a repeat charge (the cycle is already marked fulfilled). Records the money, uses up one repeat, makes the
     * refill and sends it to the pharmacy. Never throws into the payment notice. Safe to call twice for one cycle.
     */
    public function onRepeatPaid(SubscriptionCycle $cycle, int $amountCents): ?Prescription
    {
        $sub = $cycle->subscription;
        $rxId = (int) data_get($sub?->metadata, 'prescription_id');
        if (! $sub || ! $rxId) {
            return null;     // not a prescription repeat (for example a coach add-on)
        }
        if (! empty($cycle->metadata['refill_prescription_id'])) {
            return Prescription::find($cycle->metadata['refill_prescription_id']);
        }

        try {
            $refill = DB::transaction(function () use ($cycle, $sub, $rxId, $amountCents) {
                $rx = Prescription::whereKey($rxId)->lockForUpdate()->firstOrFail();
                if (! $rx->hasRefillsRemaining()) {
                    return null;
                }

                $number = $rx->repeats_used + 1;
                $order = Order::where('source_type', 'subscription')->where('source_ref', (string) $sub->id)->first();
                $reference = (string) ($cycle->metadata['charge_reference'] ?? 'RPT-'.$cycle->id);

                Payment::create([
                    'patient_id' => $rx->patient_id, 'order_id' => $order?->id, 'provider' => 'payfast', 'amount' => $amountCents ?: (int) $rx->total_amount,
                    'currency' => 'ZAR', 'status' => 'completed', 'paid_at' => now(), 'provider_reference' => $reference, 'is_repeat_charge' => true,
                    'description' => "Refill ({$number}/{$rx->repeats}) - {$rx->reference}",
                ]);

                $refill = $rx->replicate(['reference', 'upstream_id', 'upstream_synced_at', 'pharmacy_reference', 'dispatched_at', 'pharmacy_response', 'payment_reference']);
                $refill->forceFill([
                    'reference' => Prescription::generateReference(), 'refill_of_id' => $rx->id, 'refill_no' => $number,
                    'status' => 'signed', 'payment_status' => 'paid', 'payment_reference' => $reference, 'paid_at' => now(),
                    'pharmacy_status' => 'pending', 'is_chronic' => false, 'repeats' => 0, 'repeats_used' => 0, 'next_repeat_date' => null,
                ])->save();
                foreach ($rx->items as $item) {
                    $refill->items()->create($item->only([
                        'medication_id', 'medication_name', 'strength', 'form', 'dosage', 'frequency', 'route', 'duration_days',
                        'quantity', 'unit_price', 'line_total', 'instructions', 'substitution_allowed',
                    ]));
                }

                $rx->markRefillDispensed();
                $cycle->update(['metadata' => array_merge((array) $cycle->fresh()->metadata, ['refill_prescription_id' => $refill->id, 'refill_no' => $number])]);

                if ($rx->fresh()->hasRefillsRemaining() === false) {
                    $this->finish($sub);
                }

                return $refill;
            });
        } catch (\Throwable $e) {
            Log::error('repeat order: making the refill failed', ['cycle' => $cycle->id, 'error' => $e->getMessage()]);

            return null;
        }

        if ($refill) {
            try {
                // resolved only now: the payment page uses this class too and must never depend on the pharmacy being set up
                app(PharmacyService::class)->dispatch($refill);
            } catch (\Throwable $e) {
                Log::error('repeat order: sending the refill to the pharmacy failed', ['refill' => $refill->id, 'error' => $e->getMessage()]);
            }
            $this->moveOrder($sub, 'PharmacyProcessing');
        }

        return $refill;
    }

    /** The last repeat was paid: nothing more to schedule or charge. */
    private function finish(Subscription $sub): void
    {
        $sub->cycles()->where('status', CycleStatus::Scheduled->value)->update(['status' => CycleStatus::Cancelled->value]);
        $sub->forceFill(['status' => 'completed', 'ends_at' => now(), 'next_run_at' => null])->save();
    }

    private function moveOrder(Subscription $sub, string $status): void
    {
        try {
            if (! in_array(Order::where('source_type', 'subscription')->where('source_ref', (string) $sub->id)->value('status'), ['Paused', 'Cancelled'], true)) {
                $this->lifecycle->moveOrderTo($sub, $status, 'payment', 'Repeat paid, refill sent to the pharmacy');
            }
        } catch (\Throwable $e) {
            Log::warning('repeat order: could not move the order', ['subscription' => $sub->id, 'error' => $e->getMessage()]);
        }
    }
}
