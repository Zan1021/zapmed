<?php

namespace Tests\Feature;

use App\Livewire\Admin\StaffManagement;
use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;

/**
 * Phase 7.5 — staff/admin management, tiered (spec FR-15.2–15.5, AC-13).
 */
class SparStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacyGroup $groupA;
    private SparPharmacy $pharmA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->groupA = SparPharmacyGroup::create(['name' => 'Group A']);
        $this->pharmA = SparPharmacy::create(['group_id' => $this->groupA->id, 'name' => 'A1', 'spar_store_id' => 'A1', 'is_active' => true]);
    }

    private function user(string $role, ?int $groupId = null, ?int $pharmacyId = null): PharmacyUser
    {
        return PharmacyUser::create([
            'name' => $role, 'email' => $role . '_' . uniqid() . '@t.test', 'password' => Hash::make('secret1234'),
            'role' => $role, 'group_id' => $groupId, 'spar_pharmacy_id' => $pharmacyId, 'is_active' => true,
        ]);
    }

    public function test_super_admin_can_create_any_role(): void
    {
        $this->actingAs($this->user('super_admin'));

        Livewire::test(StaffManagement::class)
            ->call('create')
            ->set('name', 'New Group Admin')
            ->set('email', 'ga@t.test')
            ->set('password', 'secret1234')
            ->set('role', 'group_admin')
            ->set('group_id', $this->groupA->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pharmacy_users', ['email' => 'ga@t.test', 'role' => 'group_admin']);
    }

    public function test_group_admin_can_create_pharmacy_staff_in_group(): void
    {
        $this->actingAs($this->user('group_admin', $this->groupA->id));

        Livewire::test(StaffManagement::class)
            ->call('create')
            ->set('name', 'Store Clerk')
            ->set('email', 'clerk@t.test')
            ->set('password', 'secret1234')
            ->set('role', 'pharmacy_staff')
            ->set('spar_pharmacy_id', $this->pharmA->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pharmacy_users', ['email' => 'clerk@t.test', 'role' => 'pharmacy_staff']);
    }

    public function test_group_admin_cannot_create_super_admin(): void
    {
        $this->actingAs($this->user('group_admin', $this->groupA->id));

        // 'super_admin' is not in the group-admin's assignable roles → validation error.
        Livewire::test(StaffManagement::class)
            ->call('create')
            ->set('name', 'Escalate')
            ->set('email', 'escalate@t.test')
            ->set('password', 'secret1234')
            ->set('role', 'super_admin')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertDatabaseMissing('pharmacy_users', ['email' => 'escalate@t.test']);
    }

    public function test_pharmacy_admin_can_only_create_staff_in_own_store(): void
    {
        $this->actingAs($this->user('pharmacy_admin', pharmacyId: $this->pharmA->id));

        Livewire::test(StaffManagement::class)
            ->call('create')
            ->set('name', 'My Clerk')
            ->set('email', 'myclerk@t.test')
            ->set('password', 'secret1234')
            ->set('role', 'pharmacy_staff')
            ->set('spar_pharmacy_id', $this->pharmA->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pharmacy_users', ['email' => 'myclerk@t.test']);
    }

    public function test_pharmacy_staff_cannot_access_staff_management(): void
    {
        $this->actingAs($this->user('pharmacy_staff', pharmacyId: $this->pharmA->id));

        Livewire::test(StaffManagement::class)->assertForbidden();
    }
}
