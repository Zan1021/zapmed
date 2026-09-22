<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\MyMedsTracker;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparPatientSession;

/**
 * SPAR Close-the-Loop Wave C1 (FR-C1) — patient "Order next meds".
 * Patient picks a fulfilment mode on the tracker; a SparOrder is created with
 * the right type + payment intent. Consent is a hard gate (NFR-1).
 */
class SparPatientOrderTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacy $pharmacy;
    private SparPatient $patient;
    private SparPrescriptionJourney $journey;

    protected function setUp(): void
    {
        parent::setUp();

        $group = SparPharmacyGroup::create(['name' => 'WC']);
        $this->pharmacy = SparPharmacy::create([
            'group_id' => $group->id, 'name' => 'Plett', 'spar_store_id' => 'A',
            'is_active' => true, 'supports_delivery' => true,
        ]);

        $this->patient = SparPatient::create([
            'spar_pharmacy_id' => $this->pharmacy->id,
            'profile_code' => '880001', 'dependent_code' => '00',
            'first_name' => 'Thabo', 'last_name' => 'Mokoena', 'cellphone' => '0729990001',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => 'opted_in', 'consent_given_at' => now(),
        ]);

        $this->journey = SparPrescriptionJourney::create([
            'spar_patient_id' => $this->patient->id, 'spar_pharmacy_id' => $this->pharmacy->id,
            'script_number' => 'ORD-1', 'status' => 'active', 'total_dispenses' => 6,
            'dispenses_completed' => 2, 'start_date' => now()->subMonths(2),
            'medications' => [['name' => 'CO-COPALIA TAB 28']],
        ]);

        SparDispenseRecord::create([
            'journey_id' => $this->journey->id, 'spar_patient_id' => $this->patient->id,
            'dispense_number' => 2, 'status' => 'dispensed', 'due_date' => now()->subDays(3),
        ]);
    }

    private function establishPatientSession(): void
    {
        app(SparPatientSession::class)->establish($this->patient);
        app(SparPatientSession::class)->markVerified();
    }

    public function test_collect_pay_store_creates_a_collection_order(): void
    {
        $this->establishPatientSession();

        Livewire::test(MyMedsTracker::class)
            ->assertSet('step', 'dashboard')
            ->call('startOrder', $this->journey->id)
            ->set('orderMode', SparOrder::MODE_COLLECT_PAY_STORE)
            ->call('placeOrder')
            ->assertSet('error', '');

        $order = SparOrder::first();
        $this->assertNotNull($order);
        $this->assertSame('collection', $order->type);
        $this->assertSame(SparOrder::MODE_COLLECT_PAY_STORE, $order->fulfilment_mode);
        $this->assertSame('pay_at_store', $order->payment_status);
        $this->assertSame($this->patient->id, $order->spar_patient_id);
        $this->assertSame($this->pharmacy->id, $order->spar_pharmacy_id);
    }

    public function test_deliver_pay_now_creates_a_paid_delivery_order(): void
    {
        $this->establishPatientSession();

        Livewire::test(MyMedsTracker::class)
            ->call('startOrder', $this->journey->id)
            ->set('orderMode', SparOrder::MODE_DELIVER_PAY_NOW)
            ->call('placeOrder');

        $order = SparOrder::first();
        $this->assertSame('delivery', $order->type);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_mode_is_required(): void
    {
        $this->establishPatientSession();

        Livewire::test(MyMedsTracker::class)
            ->call('startOrder', $this->journey->id)
            ->set('orderMode', '')
            ->call('placeOrder')
            ->assertSet('error', 'Please choose how you would like your medication.');

        $this->assertSame(0, SparOrder::count());
    }

    public function test_non_consented_patient_cannot_place_an_order(): void
    {
        // Revoke consent — the tracker would sit on the consent step, but we
        // assert the order method itself refuses even if reached.
        $this->patient->update(['consent_status' => 'opted_out', 'consent_given_at' => null]);
        $this->establishPatientSession();

        $component = Livewire::test(MyMedsTracker::class);
        // Force the dashboard step + attempt an order (defensive re-check).
        $component->set('step', 'dashboard')
            ->call('startOrder', $this->journey->id)
            ->set('orderMode', SparOrder::MODE_COLLECT_PAY_STORE)
            ->call('placeOrder')
            ->assertSet('error', 'We need your consent before placing an order.');

        $this->assertSame(0, SparOrder::count());
    }

    public function test_cannot_order_against_a_journey_outside_the_session_profile(): void
    {
        $this->establishPatientSession();

        $otherPatient = SparPatient::create([
            'spar_pharmacy_id' => $this->pharmacy->id,
            'profile_code' => '770002', 'dependent_code' => '00',
            'first_name' => 'Someone', 'last_name' => 'Else', 'cellphone' => '0721110002',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => 'opted_in', 'consent_given_at' => now(),
        ]);
        $otherJourney = SparPrescriptionJourney::create([
            'spar_patient_id' => $otherPatient->id, 'spar_pharmacy_id' => $this->pharmacy->id,
            'script_number' => 'OTHER-1', 'status' => 'active', 'total_dispenses' => 6,
            'dispenses_completed' => 1, 'start_date' => now()->subMonth(),
            'medications' => [['name' => 'OTHER TAB']],
        ]);

        Livewire::test(MyMedsTracker::class)
            ->call('startOrder', $otherJourney->id)
            ->assertStatus(403);
    }
}
