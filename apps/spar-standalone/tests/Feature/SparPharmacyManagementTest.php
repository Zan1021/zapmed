<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\Admin\SparPharmacies;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;

/**
 * Phase 7.4 — pharmacy management, scope-enforced (spec FR-15.2/15.3, AC-12).
 */
class SparPharmacyManagementTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacyGroup $groupA;
    private SparPharmacyGroup $groupB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->groupA = SparPharmacyGroup::create(['name' => 'Group A']);
        $this->groupB = SparPharmacyGroup::create(['name' => 'Group B']);
    }

    private function user(string $role, ?int $groupId = null): PharmacyUser
    {
        return PharmacyUser::create([
            'name' => $role, 'email' => $role . '_' . uniqid() . '@t.test', 'password' => Hash::make('x'),
            'role' => $role, 'group_id' => $groupId, 'is_active' => true,
        ]);
    }

    public function test_super_admin_creates_pharmacy_in_any_group(): void
    {
        $this->actingAs($this->user('super_admin'));

        Livewire::test(SparPharmacies::class)
            ->call('create')
            ->set('group_id', $this->groupB->id)
            ->set('name', 'Super Store')
            ->set('spar_store_id', 'SS-1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('spar_pharmacies', ['spar_store_id' => 'SS-1', 'group_id' => $this->groupB->id]);
    }

    public function test_group_admin_creates_pharmacy_in_own_group(): void
    {
        $this->actingAs($this->user('group_admin', $this->groupA->id));

        Livewire::test(SparPharmacies::class)
            ->call('create')
            ->set('group_id', $this->groupA->id)
            ->set('name', 'Group A Store')
            ->set('spar_store_id', 'GA-1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('spar_pharmacies', ['spar_store_id' => 'GA-1', 'group_id' => $this->groupA->id]);
    }

    public function test_group_admin_cannot_create_pharmacy_in_another_group(): void
    {
        $this->actingAs($this->user('group_admin', $this->groupA->id));

        // Attempt to smuggle another group's id → 403 abort on save.
        Livewire::test(SparPharmacies::class)
            ->call('create')
            ->set('group_id', $this->groupB->id)
            ->set('name', 'Sneaky')
            ->set('spar_store_id', 'SNEAK-1')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseMissing('spar_pharmacies', ['spar_store_id' => 'SNEAK-1']);
    }

    public function test_group_admin_list_is_scoped_to_their_group(): void
    {
        SparPharmacy::create(['group_id' => $this->groupA->id, 'name' => 'A1', 'spar_store_id' => 'A1', 'is_active' => true]);
        SparPharmacy::create(['group_id' => $this->groupB->id, 'name' => 'B1', 'spar_store_id' => 'B1', 'is_active' => true]);

        $this->actingAs($this->user('group_admin', $this->groupA->id));

        Livewire::test(SparPharmacies::class)
            ->assertSee('A1')
            ->assertDontSee('B1');
    }

    public function test_pharmacy_staff_cannot_access_pharmacy_management(): void
    {
        $this->actingAs($this->user('pharmacy_staff'));

        Livewire::test(SparPharmacies::class)->assertForbidden();
    }
}
