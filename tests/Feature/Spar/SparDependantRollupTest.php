<?php

namespace Tests\Feature\Spar;

use Zapmed\SparCore\Livewire\MyMedsTracker;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparPatientSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SPAR standalone Phase 1.9 — dependants roll up to the primary member
 * (spec FR-8, AC-9). Dependants get no own link/login; the primary member's
 * view shows the whole profile (self + dependants).
 */
class SparDependantRollupTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_member_view_includes_dependant_journeys(): void
    {
        config(['spar.link.require_otp_reverify' => false]);

        $pharmacy = SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);

        $primary = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'dependent_code' => '0',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'cellphone' => '0821234567',
            'consent_status' => 'opted_in',
            'is_primary_member' => true,
            'is_active' => true,
        ]);

        $dependant = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',          // same profile
            'dependent_code' => '1',
            'first_name' => 'Lerato',
            'last_name' => 'Mokoena',
            'is_primary_member' => false,
            'is_active' => true,
        ]);

        // Each has an active journey.
        foreach ([$primary, $dependant] as $p) {
            SparPrescriptionJourney::create([
                'spar_patient_id' => $p->id,
                'spar_pharmacy_id' => $pharmacy->id,
                'script_number' => 'S-' . $p->id,
                'status' => 'active',
                'total_dispenses' => 6,
                'dispenses_completed' => 1,
                'start_date' => now(),
                'medications' => [['name' => 'Med for ' . $p->first_name]],
            ]);
        }

        // Primary member holds the session.
        app(SparPatientSession::class)->establish($primary);

        $component = Livewire::test(MyMedsTracker::class);

        // Roll-up: journeys across the whole profile (2), not just the primary's.
        $journeys = $component->instance()->journeys;
        $this->assertCount(2, $journeys);
    }

    public function test_only_primary_members_are_contactable_for_links(): void
    {
        $pharmacy = SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);

        // Dependant with no own contact — cannot be reached, never gets a link.
        $dependant = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'dependent_code' => '1',
            'first_name' => 'Lerato',
            'last_name' => 'Mokoena',
            'is_primary_member' => false,
            'is_active' => true,
        ]);

        $this->assertFalse($dependant->isContactable());
        $this->assertFalse($dependant->hasCompleteIdentity());
    }
}
