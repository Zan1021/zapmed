<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\MyMedsTracker;
use Zapmed\SparCore\Livewire\PatientDetail;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparPatientSession;
use Zapmed\SparCore\Services\SparPatientView;

/**
 * SPAR Close-the-Loop FR-D / decision D1 (2026-09-22, Craig via Captain Zan):
 * Dependants stay LISTED under the main member, but the main member does NOT
 * see a dependant's medication — only the dependant sees their own. Display:
 * name + masked placeholder ("<Name>'s medication — private"). Blanket rule,
 * all dependants.
 *
 * Staff (PatientDetail) retain the FULL household roll-up for care — the
 * privacy rule applies to the patient-facing tracker only.
 */
class SparDependantPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacy $pharmacy;
    private SparPatient $primary;
    private SparPatient $dependant;

    protected function setUp(): void
    {
        parent::setUp();

        $group = SparPharmacyGroup::create(['name' => 'G']);
        $this->pharmacy = SparPharmacy::create([
            'group_id' => $group->id, 'name' => 'Plett', 'spar_store_id' => 'A', 'is_active' => true,
        ]);

        $this->primary = SparPatient::create([
            'spar_pharmacy_id' => $this->pharmacy->id,
            'profile_code' => 'FAM-1', 'dependent_code' => '00',
            'first_name' => 'Raj', 'last_name' => 'Naidoo', 'cellphone' => '0821112222',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => 'opted_in', 'consent_given_at' => now(),
        ]);

        $this->dependant = SparPatient::create([
            'spar_pharmacy_id' => $this->pharmacy->id,
            'profile_code' => 'FAM-1', 'dependent_code' => '01',
            'first_name' => 'Anjali', 'last_name' => 'Naidoo', 'cellphone' => null,
            'is_primary_member' => false, 'is_active' => true,
            'consent_status' => 'opted_in', 'consent_given_at' => now(),
        ]);

        // Primary's own script (should be visible to the primary).
        SparPrescriptionJourney::create([
            'spar_patient_id' => $this->primary->id, 'spar_pharmacy_id' => $this->pharmacy->id,
            'script_number' => 'RAJ-MED', 'status' => 'active', 'total_dispenses' => 6,
            'dispenses_completed' => 1, 'start_date' => now()->subMonth(),
            'medications' => [['name' => 'RAJ-STATIN-40']],
        ]);

        // Dependant's script (must NOT be visible to the primary).
        SparPrescriptionJourney::create([
            'spar_patient_id' => $this->dependant->id, 'spar_pharmacy_id' => $this->pharmacy->id,
            'script_number' => 'ANJ-MED', 'status' => 'active', 'total_dispenses' => 6,
            'dispenses_completed' => 1, 'start_date' => now()->subMonth(),
            'medications' => [['name' => 'ANJALI-INSULIN-SECRET']],
        ]);
    }

    private function establishPrimarySession(): void
    {
        app(SparPatientSession::class)->establish($this->primary);
        app(SparPatientSession::class)->markVerified();
    }

    public function test_view_self_journeys_excludes_dependant_scripts(): void
    {
        $selfJourneys = app(SparPatientView::class)->selfJourneys($this->primary);

        $scripts = $selfJourneys->pluck('script_number')->all();
        $this->assertContains('RAJ-MED', $scripts);
        $this->assertNotContains('ANJ-MED', $scripts, 'Primary must not see a dependant journey.');
    }

    public function test_dependants_are_still_listed_for_the_primary(): void
    {
        $dependants = app(SparPatientView::class)->dependants($this->primary);
        $this->assertSame(['Anjali'], $dependants->pluck('first_name')->all());
    }

    public function test_tracker_shows_own_meds_but_not_dependant_meds(): void
    {
        $this->establishPrimarySession();

        Livewire::test(MyMedsTracker::class)
            ->assertSet('step', 'dashboard')
            ->assertSee('RAJ-STATIN-40')                 // own medication visible
            ->assertDontSee('ANJALI-INSULIN-SECRET')     // dependant meds hidden
            ->assertSee('Anjali')                         // dependant still listed
            ->assertSee("medication — private");          // masked placeholder (option b)
    }

    public function test_staff_patient_detail_still_sees_full_household(): void
    {
        // Staff view is for care — the privacy rule does NOT apply here.
        $staff = PharmacyUser::create([
            'name' => 'Joy', 'email' => 'joy@t.test', 'password' => Hash::make('x'),
            'role' => 'pharmacy_staff', 'spar_pharmacy_id' => $this->pharmacy->id, 'is_active' => true,
        ]);
        Auth::login($staff);

        // The shared full roll-up still includes the dependant's journey.
        $all = app(SparPatientView::class)->journeys($this->primary)->pluck('script_number')->all();
        $this->assertContains('RAJ-MED', $all);
        $this->assertContains('ANJ-MED', $all, 'Staff roll-up must still include dependant scripts for care.');
    }
}
