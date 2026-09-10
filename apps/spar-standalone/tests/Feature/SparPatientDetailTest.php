<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\PatientDetail;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;

/**
 * Staff patient detail (spec AC-1..AC-5). Read-only mobi mirror + provenance
 * panel, scope-gated, audit-logged.
 */
class SparPatientDetailTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacy $a;
    private SparPharmacy $b; // different group / store
    private SparPatient $primary;

    protected function setUp(): void
    {
        parent::setUp();
        $groupA = SparPharmacyGroup::create(['name' => 'GA']);
        $groupB = SparPharmacyGroup::create(['name' => 'GB']);
        $this->a = SparPharmacy::create(['group_id' => $groupA->id, 'name' => 'Plett', 'spar_store_id' => 'A', 'is_active' => true]);
        $this->b = SparPharmacy::create(['group_id' => $groupB->id, 'name' => 'Faraway', 'spar_store_id' => 'B', 'is_active' => true]);

        $pharmacist = PharmacyUser::create([
            'name' => 'Nurse Joy', 'email' => 'joy@t.test', 'password' => Hash::make('x'),
            'role' => 'pharmacy_staff', 'spar_pharmacy_id' => $this->a->id, 'is_active' => true,
        ]);

        $this->primary = SparPatient::create([
            'spar_pharmacy_id' => $this->a->id, 'onboarding_pharmacy_id' => $this->a->id,
            'captured_by_id' => $pharmacist->id, 'captured_at' => now(),
            'profile_code' => '990001', 'dependent_code' => '00',
            'first_name' => 'Priya', 'last_name' => 'Naidoo',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => 'opted_in', 'consent_given_at' => now(),
        ]);
        // A dependant + an active journey + a past journey.
        SparPatient::create([
            'spar_pharmacy_id' => $this->a->id, 'profile_code' => '990001', 'dependent_code' => '01',
            'first_name' => 'Raj', 'last_name' => 'Naidoo', 'dependent_relation' => 'spouse',
            'is_primary_member' => false, 'is_active' => true,
        ]);
        SparPrescriptionJourney::create([
            'spar_patient_id' => $this->primary->id, 'spar_pharmacy_id' => $this->a->id,
            'script_number' => 'ACTIVE-1', 'status' => 'active', 'total_dispenses' => 6,
            'dispenses_completed' => 2, 'start_date' => now()->subMonths(2),
            'medications' => [['name' => 'CO-COPALIA TAB 28']],
        ]);
        SparPrescriptionJourney::create([
            'spar_patient_id' => $this->primary->id, 'spar_pharmacy_id' => $this->a->id,
            'script_number' => 'OLD-1', 'status' => 'renewed', 'total_dispenses' => 6,
            'dispenses_completed' => 6, 'start_date' => now()->subMonths(9),
            'medications' => [['name' => 'OLD MED 30']],
        ]);
    }

    private function staff(): PharmacyUser
    {
        return PharmacyUser::create([
            'name' => 'Clerk', 'email' => 'clerk' . uniqid() . '@t.test', 'password' => Hash::make('x'),
            'role' => 'pharmacy_staff', 'spar_pharmacy_id' => $this->a->id, 'is_active' => true,
        ]);
    }

    public function test_shows_mirror_and_provenance(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(PatientDetail::class, ['patient' => $this->primary])
            ->assertSee('What the patient sees')     // AC-1 mirror
            ->assertSee('CO-COPALIA TAB 28')          // active journey med
            ->assertSee('Collected at: Plett')        // pharmacy label
            ->assertSee('Previous prescriptions')     // steer: previous section
            ->assertSee('OLD MED 30')                 // past journey
            ->assertSee('Onboarded by')               // AC-2 provenance
            ->assertSee('Nurse Joy')                  // resolved pharmacist name
            ->assertSee('Patient mobi link');         // steer: mobi URL
    }

    public function test_dependants_tab_lists_dependants(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(PatientDetail::class, ['patient' => $this->primary])
            ->call('setTab', 'dependants')
            ->assertSee('Raj Naidoo')
            ->assertSee('spouse');
    }

    public function test_open_is_audit_logged(): void
    {
        $this->actingAs($this->staff());

        // LogsSparActivity writes to the 'spar_audit' LOG channel (not a table).
        \Illuminate\Support\Facades\Log::shouldReceive('channel')->with('spar_audit')->andReturnSelf();
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->atLeast()->once()
            ->withArgs(fn ($msg, $ctx = []) => str_contains($msg, 'patient_access'));

        Livewire::test(PatientDetail::class, ['patient' => $this->primary]);
    }

    public function test_out_of_scope_patient_is_blocked(): void
    {
        // A patient only at store B; staff scoped to store A must not open them.
        $other = SparPatient::create([
            'spar_pharmacy_id' => $this->b->id, 'profile_code' => 'OTHER', 'dependent_code' => '00',
            'is_primary_member' => true, 'is_active' => true,
        ]);
        SparPrescriptionJourney::create([
            'spar_patient_id' => $other->id, 'spar_pharmacy_id' => $this->b->id,
            'script_number' => 'B-1', 'status' => 'active', 'total_dispenses' => 6,
            'dispenses_completed' => 0, 'start_date' => now(), 'medications' => [],
        ]);

        $this->actingAs($this->staff());

        // Hitting the real route for an out-of-scope patient returns 404.
        $this->get(route('spar.patients.show', $other->id))->assertNotFound();
    }
}
