<?php

namespace App\Services\Commerce;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Finance\FinanceService;
use Illuminate\Support\Facades\DB;

/**
 * Task 11 — live write-path adoption (pre-launch, direct integration; see specs 09/10 rev 2).
 *
 * Bridges the live checkout flow into the CRM `Order` aggregate + finance ledger. Called from the three
 * live write points (appointment booking, subscription start, PayFast ITN completion). Creates a
 * first-class Order alongside the existing Payment/Appointment/Subscription — it does NOT remove or
 * alter any existing write or side-effect.
 *
 * Idempotent: every Order is keyed to its origin via (source_type, source_ref), so a duplicate PayFast
 * ITN or a re-render can never double-create. NEVER initiates a gateway charge — it only records the
 * outcome of charges PayFast already made (same discipline as FinanceService / SubscriptionLifecycle).
 */
class LiveOrderBridge
{
    public function __construct(private readonly FinanceService $finance)
    {
    }

    /**
     * Mirror a booked consultation into a consult Order (status PendingPayment until the ITN confirms).
     * Idempotent per appointment.
     */
    public function onAppointmentBooked(Appointment $appointment): Order
    {
        return $this->upsertOrder('appointment', (string) $appointment->id, [
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $appointment->doctor_id,
            'status' => 'PendingPayment',
            'service_fee_minor' => (int) ($appointment->fee_amount ?? 0),
            'total_minor' => (int) ($appointment->fee_amount ?? 0),
            'service_category' => $appointment->type,
            'is_subscription' => false,
            'ordered_at' => now(),
            'metadata' => ['origin' => 'appointment', 'appointment_reference' => $appointment->reference],
        ]);
    }

    /**
     * Mirror a started subscription into a subscription Order. Idempotent per subscription.
     */
    public function onSubscriptionStarted(Subscription $subscription): Order
    {
        $plan = $subscription->plan;

        return $this->upsertOrder('subscription', (string) $subscription->id, [
            'patient_id' => $subscription->user_id,
            'status' => 'PendingPayment',
            'total_minor' => (int) ($plan->price ?? 0),
            'service_category' => $plan->name ?? 'subscription',
            'is_subscription' => true,
            'ordered_at' => now(),
            'metadata' => ['origin' => 'subscription', 'plan' => $plan->name ?? null],
        ]);
    }

    /**
     * On a COMPLETED PayFast payment: ensure the linked Order is marked paid and record the cash in the
     * finance ledger. Idempotent — safe on ITN replays (recordCashFromPayment is firstOrCreate; the Order
     * update is a no-op if already advanced). Records the outcome only; never charges.
     */
    public function onPaymentCompleted(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            // Resolve the originating Order: prefer the appointment link, else the payment itself.
            $order = null;
            if ($payment->appointment_id) {
                $order = Order::where('source_type', 'appointment')->where('source_ref', (string) $payment->appointment_id)->first();
            }
            $order ??= Order::where('source_type', 'payment')->where('source_ref', (string) $payment->id)->first();

            // No pre-existing mirror (e.g. a medication payment with no appointment) → create one now.
            $order ??= $this->upsertOrder('payment', (string) $payment->id, [
                'patient_id' => $payment->patient_id,
                'status' => 'PendingPayment',
                'total_minor' => (int) $payment->amount,
                'service_category' => $payment->payment_type,
                'ordered_at' => now(),
                'metadata' => ['origin' => 'payment', 'payment_reference' => $payment->reference],
            ]);

            // Advance to PaymentReceived on payment (guarded; ignore if the machine disallows from current state).
            if ($order->status === 'PendingPayment') {
                try {
                    $order->transitionTo('PaymentReceived', 'payment', ['notes' => 'PayFast payment completed', 'triggered_by' => 'system:payfast']);
                } catch (\Throwable $e) {
                    // Non-fatal: the finance record below is what matters; status stays as-is.
                }
            }

            // Record cash in the finance ledger (idempotent per payment).
            $this->finance->recordCashFromPayment($payment, serviceLine: $order->service_category);
        });
    }

    /**
     * Upsert an Order by its origin key. Only fills provided attributes; never downgrades an existing
     * Order's status (status is only set on first create).
     *
     * @param  array<string,mixed>  $attributes
     */
    private function upsertOrder(string $sourceType, string $sourceRef, array $attributes): Order
    {
        $existing = Order::where('source_type', $sourceType)->where('source_ref', $sourceRef)->first();
        if ($existing) {
            // Update mutable snapshot fields, but do NOT rewind status.
            unset($attributes['status']);
            $existing->fill($attributes)->save();

            return $existing;
        }

        return Order::create(array_merge($attributes, [
            'source_type' => $sourceType,
            'source_ref' => $sourceRef,
            'upstream_source' => 'live',
        ]));
    }
}
