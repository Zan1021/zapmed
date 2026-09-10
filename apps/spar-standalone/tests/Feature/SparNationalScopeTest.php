<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;

/**
 * National identity, Phase 3 — a patient with journeys at TWO stores is visible
 * to staff at BOTH stores, and invisible to a third store. Scope is by activity
 * (journeys/dispenses), not a flat home-pharmacy column.
 */
class SparNationalScopeTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacyGroup $group;
    private SparPharmacy $a;
    private SparPharmacy $b;
    private SparPharmacy $c;
    private SparPatient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = SparPharmacyGroup::create(['name' => 'G']);
        $this->a = SparPharmacy::create(['group_id' => $this->group->id, 'name' => 'A', 'spar_store_id' => 'A', 'is_active' => true]);
        $this->b = SparPharmacy::create(['group_id' => $this->group->id, 'name' => 'B', 'spar_store_id' => 'B', 'is_active' => true]);
        $this->c = SparPharmacy::create(['name' => 'C', 'spar_store_id' => 'C', 'is_active' => true]); // different group

        // One national patient (home = A) with a journey at A AND a journey at B.
        $this->patient = SparPatient::create([
            'spar_pharmacy_id' => $this->a->id,
            'profile_code' => '990001', 'dependent_code' => '00',
            'is_primary_member' => true, 'is_active' => true,
        ]);
        foreach ([$this->a, $this->b] as $ph) {
            SparPrescriptionJourney::create([
                'spar_patient_id' => $this->patient->id,
                'spar_pharmacy_id' => $ph->id,
                'script_number' => 'S-' . $ph->id,
                'status' => 'active', 'total_dispenses' => 6, 'dispenses_completed' => 1,
                'start_date' => now(), 'medications' => [],
            ]);
        }
    }

    private function actAs(string $role, ?int $pharmacyId = null, ?int $groupId = null): void
    {
        $this->actingAs(PharmacyUser::create([
            'name' => $role, 'email' => $role . uniqid() . '@t.test', 'password' => Hash::make('x'),
            'role' => $role, 'spar_pharmacy_id' => $pharmacyId, 'group_id' => $groupId, 'is_active' => true,
        ]));
    }

    public function test_visible_to_both_stores_the_patient_uses(): void
    {
        $this->actAs('pharmacy_staff', pharmacyId: $this->a->id);
        $this->assertSame(1, SparPatient::visibleToCurrentActor()->count(), 'store A staff sees the patient');

        $this->actAs('pharmacy_staff', pharmacyId: $this->b->id);
        $this->assertSame(1, SparPatient::visibleToCurrentActor()->count(), 'store B staff sees the same patient');
    }

    public function test_invisible_to_a_store_the_patient_never_used(): void
    {
        $this->actAs('pharmacy_staff', pharmacyId: $this->c->id);
        $this->assertSame(0, SparPatient::visibleToCurrentActor()->count(), 'store C staff sees nothing');
    }

    public function test_group_admin_sees_patient_via_group_pharmacies(): void
    {
        $this->actAs('group_admin', groupId: $this->group->id);
        $this->assertSame(1, SparPatient::visibleToCurrentActor()->count());
    }

    public function test_super_admin_sees_all(): void
    {
        $this->actAs('super_admin');
        $this->assertSame(1, SparPatient::visibleToCurrentActor()->count());
    }
}
