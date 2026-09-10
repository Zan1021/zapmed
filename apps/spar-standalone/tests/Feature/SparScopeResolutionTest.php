<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;

/**
 * Phase 7.2 — scope resolution + visibleToCurrentActor scopes (spec FR-16).
 * The identity provider resolves role/group/pharmacy scope; the model scopes
 * enforce data isolation. Verified per role in the standalone host.
 */
class SparScopeResolutionTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacyGroup $groupA;
    private SparPharmacyGroup $groupB;
    private SparPharmacy $pharmA1;
    private SparPharmacy $pharmA2;
    private SparPharmacy $pharmB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->groupA = SparPharmacyGroup::create(['name' => 'Group A']);
        $this->groupB = SparPharmacyGroup::create(['name' => 'Group B']);

        $this->pharmA1 = SparPharmacy::create(['group_id' => $this->groupA->id, 'name' => 'A1', 'spar_store_id' => 'A1', 'is_active' => true]);
        $this->pharmA2 = SparPharmacy::create(['group_id' => $this->groupA->id, 'name' => 'A2', 'spar_store_id' => 'A2', 'is_active' => true]);
        $this->pharmB1 = SparPharmacy::create(['group_id' => $this->groupB->id, 'name' => 'B1', 'spar_store_id' => 'B1', 'is_active' => true]);

        // One patient per pharmacy.
        foreach ([$this->pharmA1, $this->pharmA2, $this->pharmB1] as $i => $p) {
            SparPatient::create([
                'spar_pharmacy_id' => $p->id,
                'profile_code' => 'P-' . $i,
                'is_primary_member' => true,
                'is_active' => true,
            ]);
        }
    }

    private function actAs(string $role, ?int $pharmacyId = null, ?int $groupId = null): PharmacyUser
    {
        $user = PharmacyUser::create([
            'name' => $role,
            'email' => $role . '_' . uniqid() . '@t.test',
            'password' => Hash::make('x'),
            'role' => $role,
            'spar_pharmacy_id' => $pharmacyId,
            'group_id' => $groupId,
            'is_active' => true,
        ]);
        $this->actingAs($user);

        return $user;
    }

    private function identity(): SparIdentityProvider
    {
        return app(SparIdentityProvider::class);
    }

    public function test_super_admin_sees_everything(): void
    {
        $this->actAs('super_admin');

        $this->assertTrue($this->identity()->isSuperAdmin());
        $this->assertNull($this->identity()->currentGroupId());
        $this->assertNull($this->identity()->currentPharmacyId());
        $this->assertSame(3, SparPharmacy::visibleToCurrentActor()->count());
        $this->assertSame(3, SparPatient::visibleToCurrentActor()->count());
    }

    public function test_group_admin_sees_only_their_group(): void
    {
        $this->actAs('group_admin', groupId: $this->groupA->id);

        $this->assertFalse($this->identity()->isSuperAdmin());
        $this->assertSame($this->groupA->id, $this->identity()->currentGroupId());
        // Group A has 2 pharmacies + 2 patients; Group B is invisible.
        $this->assertSame(2, SparPharmacy::visibleToCurrentActor()->count());
        $this->assertSame(2, SparPatient::visibleToCurrentActor()->count());

        // Cannot manage another group / its pharmacies.
        $this->assertTrue($this->identity()->canManageGroup($this->groupA->id));
        $this->assertFalse($this->identity()->canManageGroup($this->groupB->id));
        $this->assertTrue($this->identity()->canManagePharmacy($this->pharmA1->id));
        $this->assertFalse($this->identity()->canManagePharmacy($this->pharmB1->id));
    }

    public function test_pharmacy_staff_sees_only_their_store(): void
    {
        $this->actAs('pharmacy_staff', pharmacyId: $this->pharmA1->id);

        $this->assertSame($this->pharmA1->id, $this->identity()->currentPharmacyId());
        $this->assertSame(1, SparPharmacy::visibleToCurrentActor()->count());
        $this->assertSame(1, SparPatient::visibleToCurrentActor()->count());

        // Staff can manage nothing.
        $this->assertFalse($this->identity()->canManagePharmacy($this->pharmA1->id));
        $this->assertFalse($this->identity()->canManageGroup($this->groupA->id));
    }

    public function test_pharmacy_admin_manages_only_own_store(): void
    {
        $this->actAs('pharmacy_admin', pharmacyId: $this->pharmA1->id);

        $this->assertTrue($this->identity()->canManagePharmacy($this->pharmA1->id));
        $this->assertFalse($this->identity()->canManagePharmacy($this->pharmA2->id));
        $this->assertFalse($this->identity()->canManageGroup($this->groupA->id));
        // Sees only their store's patients.
        $this->assertSame(1, SparPatient::visibleToCurrentActor()->count());
    }

    public function test_unauthenticated_sees_nothing(): void
    {
        $this->assertNull($this->identity()->currentRole());
        $this->assertFalse($this->identity()->isSuperAdmin());
        $this->assertSame(0, SparPharmacy::visibleToCurrentActor()->count());
        $this->assertSame(0, SparPatient::visibleToCurrentActor()->count());
    }
}
