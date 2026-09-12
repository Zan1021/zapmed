<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\ImportQuarantine;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PatientProfile;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\UpstreamIngestedRow;
use App\Models\User;
use App\Services\Contro\ControReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Task 7 — Contro reconciler (staging -> canonical). Blueprint §3.
 * Asserts idempotency, userHash crosswalk + stub principals, money conversion, quarantine, and
 * ZERO side-effects.
 */
class ControReconcilerTest extends TestCase
{
    use RefreshDatabase;

    private function stage(string $entitySet, string $upstreamId, array $payload): void
    {
        UpstreamIngestedRow::create([
            'entity_set' => $entitySet,
            'upstream_id' => $upstreamId,
            'payload' => $payload,
        ]);
    }

    public function test_patient_reconcile_creates_user_profile_and_address(): void
    {
        $this->stage('patients', 'hash_a', [
            'userHash' => 'hash_a', 'firstName' => 'Priya', 'lastName' => 'Naidoo',
            'email' => 'priya@example.com', 'paymentType' => 'medical_aid',
            'medicalAidProvider' => 'Discovery', 'phone' => '+27821112222',
            'deliveryAddress' => ['addressLine1' => '1 Main Rd', 'city' => 'Cape Town', 'province' => 'WC', 'postalCode' => '8001'],
        ]);

        (new ControReconciler())->reconcilePatients();

        $user = User::where('upstream_id', 'hash_a')->first();
        $this->assertNotNull($user);
        $this->assertSame('Priya', $user->first_name);
        $profile = PatientProfile::where('upstream_id', 'hash_a')->first();
        $this->assertSame('Discovery', $profile->medical_aid_name);
        $this->assertSame('medical_aid', $profile->payment_type);
        $this->assertSame(1, $profile->addresses()->count());
        $this->assertSame('Cape Town', $profile->addresses()->first()->city);
    }

    public function test_reconcile_is_idempotent(): void
    {
        $this->stage('products', 'prod_1', ['id' => 'prod_1', 'productName' => 'Metformin', 'price' => 121.00, 'isEnabled' => true]);

        (new ControReconciler())->reconcileProducts();
        (new ControReconciler())->reconcileProducts(); // re-run

        $this->assertSame(1, CatalogItem::where('upstream_id', 'prod_1')->count());
        $this->assertSame(12100, CatalogItem::where('upstream_id', 'prod_1')->first()->price_minor); // rands->cents
    }

    public function test_order_stubs_principals_when_patient_not_yet_synced(): void
    {
        // Order references a patient/doctor whose own entity hasn't been reconciled — must stub them.
        $this->stage('orders', 'ord_1', [
            'id' => 'ord_1', 'orderNumber' => 'CTRL-1', 'patientUserHash' => 'pat_x',
            'assignedDoctorUserHash' => 'doc_y', 'status' => 'Processing',
            'serviceFee' => 50.00, 'medicationFee' => 450.00, 'orderDate' => '2026-01-01T00:00:00Z',
        ]);

        (new ControReconciler())->reconcileOrders();

        $order = Order::where('upstream_id', 'ord_1')->first();
        $this->assertNotNull($order);
        $this->assertSame('Processing', $order->status);
        $this->assertSame(5000, $order->service_fee_minor);
        // Stub principals created and linked.
        $this->assertNotNull($order->patient_id);
        $this->assertNotNull($order->doctor_id);
        $this->assertSame('pat_x', User::find($order->patient_id)->upstream_id);
        $this->assertFalse((bool) User::find($order->patient_id)->is_active); // stub, not login-capable
    }

    public function test_unknown_status_is_quarantined_but_order_still_lands(): void
    {
        $this->stage('orders', 'ord_bad', ['id' => 'ord_bad', 'orderNumber' => 'CTRL-2', 'status' => 'TotallyMadeUp']);

        (new ControReconciler())->reconcileOrders();

        $this->assertNotNull(Order::where('upstream_id', 'ord_bad')->first()); // landed verbatim
        $q = ImportQuarantine::where('entity_set', 'orders')->where('upstream_id', 'ord_bad')->first();
        $this->assertNotNull($q);
        $this->assertSame('unknown_status', $q->reason);
    }

    public function test_status_history_without_matching_order_is_quarantined(): void
    {
        $this->stage('order_status_history', 'osh_1', ['id' => 'osh_1', 'orderNumber' => 'NO-SUCH-ORDER', 'status' => 'Delivered', 'changedAt' => '2026-01-02T00:00:00Z']);

        (new ControReconciler())->reconcileOrderStatusHistory();

        $this->assertSame(0, OrderStatusHistory::count());
        $q = ImportQuarantine::where('entity_set', 'order_status_history')->first();
        $this->assertSame('unmatched_order', $q->reason);
    }

    public function test_zero_amount_payment_reconciles_and_maps_provider_to_payfast(): void
    {
        // Dependency chain: patient + order first, so the payment resolves its patient via the order.
        $this->stage('patients', 'pat_p', ['userHash' => 'pat_p', 'firstName' => 'P', 'email' => 'p@example.com']);
        $this->stage('orders', 'ord_9', ['id' => 'ord_9', 'orderNumber' => 'CTRL-9', 'patientUserHash' => 'pat_p', 'status' => 'Processing']);
        $this->stage('payments', 'pay_1', ['id' => 'pay_1', 'orderNumber' => 'CTRL-9', 'amount' => 0, 'status' => 'pending', 'type' => 'card']);

        $r = new ControReconciler();
        $r->reconcilePatients();
        $r->reconcileOrders();
        $r->reconcilePayments();

        $p = Payment::where('upstream_id', 'pay_1')->first();
        $this->assertNotNull($p);
        $this->assertSame(0, $p->amount);
        $this->assertSame('payfast', $p->provider);
        $this->assertNotNull($p->patient_id); // resolved via the order
    }

    public function test_payment_with_no_resolvable_patient_is_quarantined(): void
    {
        // No patientUserHash, no matching order -> cannot satisfy NOT NULL patient_id -> quarantine.
        $this->stage('payments', 'pay_orphan', ['id' => 'pay_orphan', 'amount' => 500, 'status' => 'pending']);

        (new ControReconciler())->reconcilePayments();

        $this->assertSame(0, Payment::where('upstream_id', 'pay_orphan')->count());
        $q = ImportQuarantine::where('entity_set', 'payments')->where('upstream_id', 'pay_orphan')->first();
        $this->assertSame('unresolved_patient', $q->reason);
    }

    public function test_prescription_with_medication_lines(): void
    {
        $this->stage('prescriptions', 'rx_1', [
            'id' => 'rx_1', 'patientUserHash' => 'pat_z', 'doctorUserHash' => 'doc_z',
            'totalMedicationCost' => 450.00, 'serviceFee' => 50.00, 'repeatCycleDays' => 30,
            'medications' => [
                ['name' => 'Metformin', 'strength' => '500mg', 'form' => 'tablet', 'quantity' => 30, 'unitPrice' => 15.00, 'totalPrice' => 450.00, 'instructions' => 'After food'],
            ],
        ]);

        (new ControReconciler())->reconcilePrescriptions();

        $rx = Prescription::where('upstream_id', 'rx_1')->first();
        $this->assertNotNull($rx);
        $this->assertSame(45000, $rx->total_medication_cost_minor);
        $this->assertSame(30, $rx->repeat_cycle_days);
        $this->assertSame(1, $rx->items()->count());
        $this->assertSame(1500, $rx->items()->first()->unit_price); // 15.00 rands -> 1500 cents
    }

    public function test_reconcile_fires_no_side_effects(): void
    {
        Mail::fake();
        Bus::fake();
        Notification::fake();

        $this->stage('patients', 'hash_s', ['userHash' => 'hash_s', 'firstName' => 'A', 'email' => 'a@example.com']);
        $this->stage('orders', 'ord_s', ['id' => 'ord_s', 'orderNumber' => 'CTRL-S', 'patientUserHash' => 'hash_s', 'status' => 'Processing']);
        $this->stage('payments', 'pay_s', ['id' => 'pay_s', 'orderNumber' => 'CTRL-S', 'amount' => 10000, 'status' => 'completed']);

        (new ControReconciler())->reconcileAll();

        // Import must NOT send mail, dispatch jobs, or fire notifications.
        Mail::assertNothingSent();
        Bus::assertNothingDispatched();
        Notification::assertNothingSent();
    }
}
