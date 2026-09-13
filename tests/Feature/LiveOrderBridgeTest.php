<?php

namespace Tests\Feature;

use App\Enums\RevenueKind;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\FinanceRevenueEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Commerce\LiveOrderBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 11 — live write-path adoption (pre-launch, PayFast sandbox). Verifies the CRM Order aggregate +
 * finance ledger are populated from the live flow, idempotently, without ever initiating a charge.
 */
class LiveOrderBridgeTest extends TestCase
{
    use RefreshDatabase;

    private function bridge(): LiveOrderBridge
    {
        return app(LiveOrderBridge::class);
    }

    private function patient(): User
    {
        return User::factory()->create(['role' => UserRole::Patient]);
    }

    private function doctor(): User
    {
        return User::factory()->create(['role' => UserRole::Doctor]);
    }

    private function appointment(User $patient): Appointment
    {
        return Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor()->id,
            'type' => 'consultation',
            'status' => 'pending',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:15',
            'duration_minutes' => 15,
            'fee_amount' => 25000,
            'is_paid' => false,
        ]);
    }

    // ---- appointment mirror ---------------------------------------------------------------------

    public function test_booking_creates_a_consult_order(): void
    {
        $patient = $this->patient();
        $appt = $this->appointment($patient);

        $order = $this->bridge()->onAppointmentBooked($appt);

        $this->assertSame('appointment', $order->source_type);
        $this->assertSame((string) $appt->id, $order->source_ref);
        $this->assertSame('PendingPayment', $order->status);
        $this->assertSame(25000, $order->total_minor);
        $this->assertFalse($order->is_subscription);
    }

    public function test_booking_mirror_is_idempotent(): void
    {
        $appt = $this->appointment($this->patient());

        $a = $this->bridge()->onAppointmentBooked($appt);
        $b = $this->bridge()->onAppointmentBooked($appt);

        $this->assertTrue($a->is($b));
        $this->assertSame(1, Order::where('source_type', 'appointment')->where('source_ref', (string) $appt->id)->count());
    }

    // ---- subscription mirror --------------------------------------------------------------------

    public function test_subscribe_creates_a_subscription_order(): void
    {
        $patient = $this->patient();
        $plan = SubscriptionPlan::create(['name' => 'Monthly', 'slug' => 'monthly-' . uniqid(), 'price' => 49900, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $sub = Subscription::create(['user_id' => $patient->id, 'subscription_plan_id' => $plan->id, 'status' => 'pending', 'total_paid' => 0, 'payment_count' => 0]);

        $order = $this->bridge()->onSubscriptionStarted($sub);

        $this->assertSame('subscription', $order->source_type);
        $this->assertTrue($order->is_subscription);
        $this->assertSame(49900, $order->total_minor);
    }

    // ---- payment completion ---------------------------------------------------------------------

    public function test_completed_payment_advances_order_and_records_cash(): void
    {
        $patient = $this->patient();
        $appt = $this->appointment($patient);
        $order = $this->bridge()->onAppointmentBooked($appt);

        $payment = Payment::create([
            'patient_id' => $patient->id,
            'appointment_id' => $appt->id,
            'amount' => 25000,
            'currency' => 'ZAR',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => 'consultation',
        ]);

        $this->bridge()->onPaymentCompleted($payment);

        $this->assertSame('PaymentReceived', $order->fresh()->status);
        $this->assertSame(1, FinanceRevenueEntry::where('payment_id', $payment->id)->where('kind', RevenueKind::CashCollected->value)->count());
    }

    public function test_completed_payment_is_idempotent_on_itn_replay(): void
    {
        $patient = $this->patient();
        $appt = $this->appointment($patient);
        $this->bridge()->onAppointmentBooked($appt);

        $payment = Payment::create([
            'patient_id' => $patient->id,
            'appointment_id' => $appt->id,
            'amount' => 25000,
            'currency' => 'ZAR',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => 'consultation',
        ]);

        // Simulate PayFast sending the ITN twice.
        $this->bridge()->onPaymentCompleted($payment);
        $this->bridge()->onPaymentCompleted($payment);

        $this->assertSame(1, Order::where('source_type', 'appointment')->where('source_ref', (string) $appt->id)->count());
        $this->assertSame(1, FinanceRevenueEntry::where('payment_id', $payment->id)->count());
    }

    public function test_medication_payment_without_appointment_creates_a_payment_order(): void
    {
        $patient = $this->patient();
        $payment = Payment::create([
            'patient_id' => $patient->id,
            'amount' => 80000,
            'currency' => 'ZAR',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => 'medication',
            'description' => 'Medication - RX-123',
        ]);

        $this->bridge()->onPaymentCompleted($payment);

        $order = Order::where('source_type', 'payment')->where('source_ref', (string) $payment->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(1, FinanceRevenueEntry::where('payment_id', $payment->id)->count());
    }

    // ---- safety ---------------------------------------------------------------------------------

    public function test_bridge_never_initiates_a_charge(): void
    {
        // The bridge has no PayFastService dependency and makes no HTTP calls. Guard against regressions
        // by asserting the completed-payment path touches only Order + finance, never a gateway.
        \Illuminate\Support\Facades\Http::fake(); // any outbound HTTP would be recorded
        $patient = $this->patient();
        $payment = Payment::create([
            'patient_id' => $patient->id, 'amount' => 10000, 'currency' => 'ZAR',
            'status' => 'completed', 'paid_at' => now(), 'payment_type' => 'consultation',
        ]);

        $this->bridge()->onPaymentCompleted($payment);

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
}
