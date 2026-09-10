<?php

namespace Tests\Feature\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparOrder;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterisation tests for the SPAR order lifecycle + SparOrderService.
 * Describes CURRENT behaviour (order state machine + dispense cascade) so the
 * package extraction can be proven behaviour-preserving.
 */
class SparOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private SparOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SparOrderService();
    }

    private function scaffold(bool $supportsDelivery = true): array
    {
        $pharmacy = SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '2000001',
            'bhf_code' => '2000001',
            'supports_delivery' => $supportsDelivery,
            'delivery_fee' => 5000,
            'is_active' => true,
        ]);

        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '777',
            'consent_status' => 'opted_in',
            'is_active' => true,
        ]);

        $journey = SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => '999999',
            'status' => 'active',
            'total_dispenses' => 6,
            'dispenses_completed' => 0,
            'start_date' => now(),
        ]);

        $dispense = SparDispenseRecord::create([
            'journey_id' => $journey->id,
            'spar_patient_id' => $patient->id,
            'dispense_number' => 1,
            'status' => 'upcoming',
            'due_date' => now()->addDays(3),
        ]);

        return compact('pharmacy', 'patient', 'journey', 'dispense');
    }

    public function test_order_reference_is_auto_generated_with_prefix(): void
    {
        ['dispense' => $dispense] = $this->scaffold();

        $order = $this->service->requestCollection($dispense);

        $this->assertStringStartsWith('SP-', $order->reference);
        $this->assertSame('requested', $order->status);
        $this->assertSame('collection', $order->type);
        $this->assertNotNull($dispense->fresh()->collection_requested_at);
    }

    public function test_request_delivery_requires_pharmacy_delivery_support(): void
    {
        ['dispense' => $dispense] = $this->scaffold(supportsDelivery: false);

        $this->expectException(\Exception::class);
        $this->service->requestDelivery($dispense, '1 Test St', 'Testville', '0001', '0821234567');
    }

    public function test_full_collection_lifecycle_cascades_to_dispense_and_journey(): void
    {
        ['dispense' => $dispense, 'journey' => $journey] = $this->scaffold();

        $order = $this->service->requestCollection($dispense);

        $this->service->startPreparing($order);
        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->prepared_at);

        $this->service->markReady($order);
        $this->assertSame('ready', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->ready_at);

        $this->service->completeOrder($order);
        $this->assertSame('completed', $order->fresh()->status);

        // Completing a collection order marks the dispense collected + advances the journey.
        $this->assertSame('collected', $dispense->fresh()->status);
        $this->assertSame(1, $journey->fresh()->dispenses_completed);
    }

    public function test_pending_scope_covers_requested_and_preparing(): void
    {
        ['dispense' => $dispense] = $this->scaffold();
        $order = $this->service->requestCollection($dispense);

        $this->assertTrue(SparOrder::pending()->whereKey($order->id)->exists());

        $this->service->markReady($order);
        $this->assertFalse(SparOrder::pending()->whereKey($order->id)->exists());
    }

    public function test_cancel_sets_status_and_reason(): void
    {
        ['dispense' => $dispense] = $this->scaffold();
        $order = $this->service->requestCollection($dispense);

        $this->service->cancelOrder($order, 'patient no longer needs it');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('patient no longer needs it', $order->fresh()->cancelled_reason);
        $this->assertNotNull($order->fresh()->cancelled_at);
    }
}
