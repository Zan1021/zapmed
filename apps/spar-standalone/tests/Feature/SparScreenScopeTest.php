<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\Admin\SparConsents;
use Zapmed\SparCore\Livewire\PatientList;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;

/**
 * Phase 7.6 — existing operational screens respect actor scope (spec FR-16).
 * PatientList + admin SparConsents must not leak across pharmacy/group.
 */
class SparScreenScopeTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacy $pharmA;
    private SparPharmacy $pharmB;

    protected function setUp(): void
    {
        parent::setUp();
        $groupA = SparPharmacyGroup::create(['name' => 'Group A']);
        $groupB = SparPharmacyGroup::create(['name' => 'Group B']);
        $this->pharmA = SparPharmacy::create(['group_id' => $groupA->id, 'name' => 'A1', 'spar_store_id' => 'A1', 'is_active' => true]);
        $this->pharmB = SparPharmacy::create(['group_id' => $groupB->id, 'name' => 'B1', 'spar_store_id' => 'B1', 'is_active' => true]);

        $this->makePatient($this->pharmA, 'PA', 'Alice', 'Anderson');
        $this->makePatient($this->pharmB, 'PB', 'Bob', 'Baker');
    }

    private function makePatient(SparPharmacy $pharmacy, string $profile, string $first, string $last): SparPatient
    {
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id, 'profile_code' => $profile,
            'first_name' => $first, 'last_name' => $last, 'is_active' => true, 'consent_status' => 'opted_in',
        ]);
        // National model: association to a store is via a journey there.
        \Zapmed\SparCore\Models\SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id, 'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => 'S-' . $profile, 'status' => 'active',
            'total_dispenses' => 6, 'dispenses_completed' => 0, 'start_date' => now(), 'medications' => [],
        ]);

        return $patient;
    }

    private function user(string $role, ?int $pharmacyId = null, ?int $groupId = null): PharmacyUser
    {
        return PharmacyUser::create([
            'name' => $role, 'email' => $role . '_' . uniqid() . '@t.test', 'password' => Hash::make('x'),
            'role' => $role, 'spar_pharmacy_id' => $pharmacyId, 'group_id' => $groupId, 'is_active' => true,
        ]);
    }

    public function test_patient_list_super_admin_sees_all(): void
    {
        $this->actingAs($this->user('super_admin'));

        Livewire::test(PatientList::class)
            ->assertSee('Alice')
            ->assertSee('Bob');
    }

    public function test_patient_list_pharmacy_staff_sees_only_their_store(): void
    {
        $this->actingAs($this->user('pharmacy_staff', pharmacyId: $this->pharmA->id));

        Livewire::test(PatientList::class)
            ->assertSee('Alice')
            ->assertDontSee('Bob');
    }

    public function test_consent_screen_pharmacy_staff_sees_only_their_store(): void
    {
        $this->actingAs($this->user('pharmacy_staff', pharmacyId: $this->pharmB->id));

        Livewire::test(SparConsents::class)
            ->assertSee('Bob')
            ->assertDontSee('Alice');
    }
}
