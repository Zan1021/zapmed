<?php

namespace Tests\Feature\Spar;

use Zapmed\SparCore\Contracts\TelehealthBridge;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\Telehealth\NullTelehealthBridge;
use App\Services\Spar\Telehealth\ZapmedTelehealthBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SPAR standalone Phase 2.3 — TelehealthBridge (spec FR-3, FR-13, AC-4).
 */
class SparTelehealthBridgeTest extends TestCase
{
    use RefreshDatabase;

    private function journey(): SparPrescriptionJourney
    {
        $pharmacy = SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'cellphone' => '0821234567',
            'consent_status' => 'opted_in',
            'is_active' => true,
        ]);

        return SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => 'R-1',
            'status' => 'renewal_due',
            'total_dispenses' => 6,
            'dispenses_completed' => 6,
            'start_date' => now()->subMonths(6),
            'renewal_due_date' => now(),
            'medications' => [['name' => 'CO-COPALIA 10MG TAB 28']],
        ]);
    }

    public function test_container_binds_zapmed_bridge_when_integrated(): void
    {
        config(['spar.host_mode' => 'integrated']);
        $this->assertInstanceOf(ZapmedTelehealthBridge::class, app(TelehealthBridge::class));
    }

    public function test_container_binds_null_bridge_when_standalone(): void
    {
        config(['spar.host_mode' => 'standalone']);
        $this->assertInstanceOf(NullTelehealthBridge::class, app(TelehealthBridge::class));
    }

    public function test_zapmed_bridge_offers_online_consult_and_handoff(): void
    {
        config(['spar.host_mode' => 'integrated']);
        $journey = $this->journey();
        $bridge = app(TelehealthBridge::class);

        $this->assertTrue($bridge->offersOnlineConsult());

        $options = $bridge->renewalOptions($journey);
        $keys = array_column($options, 'key');
        $this->assertContains('zapmed_online', $keys);
        $this->assertContains('own_doctor', $keys);

        $handoff = $bridge->handoffContext($journey);
        $this->assertNotNull($handoff);
        $this->assertArrayHasKey('url', $handoff);
        $this->assertSame('Thabo', $handoff['patient']['first_name']);
        $this->assertNotEmpty($handoff['medications']);
    }

    public function test_null_bridge_offers_only_own_doctor(): void
    {
        config(['spar.host_mode' => 'standalone']);
        $journey = $this->journey();
        $bridge = app(TelehealthBridge::class);

        $this->assertFalse($bridge->offersOnlineConsult());
        $this->assertSame(['own_doctor'], array_column($bridge->renewalOptions($journey), 'key'));
        $this->assertNull($bridge->handoffContext($journey));
        $this->assertNull($bridge->returnPrescription($journey, [['name' => 'X']]));
    }

    public function test_return_prescription_closes_the_loop_when_integrated(): void
    {
        config(['spar.host_mode' => 'integrated']);
        $journey = $this->journey();
        $bridge = app(TelehealthBridge::class);

        $new = $bridge->returnPrescription($journey, [['name' => 'CO-COPALIA 10MG TAB 28']], [
            'doctor_name' => 'Dr ZapMed',
            'repeats' => 6,
        ]);

        $this->assertNotNull($new);
        $this->assertSame('active', $new->status);
        $this->assertSame(0, $new->dispenses_completed);
        $this->assertSame($journey->spar_patient_id, $new->spar_patient_id);

        // Old journey marked renewed.
        $this->assertSame('renewed', $journey->fresh()->status);
    }
}
