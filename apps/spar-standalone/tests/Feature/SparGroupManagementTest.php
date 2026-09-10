<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\Admin\SparGroups;
use Zapmed\SparCore\Models\SparPharmacyGroup;

/**
 * Phase 7.3 — group management UI (super-admin only, spec FR-14.3).
 */
class SparGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): PharmacyUser
    {
        return PharmacyUser::create([
            'name' => $role, 'email' => $role . '@t.test', 'password' => Hash::make('x'),
            'role' => $role, 'is_active' => true,
        ]);
    }

    public function test_super_admin_can_create_a_group(): void
    {
        $this->actingAs($this->user('super_admin'));

        Livewire::test(SparGroups::class)
            ->call('create')
            ->set('name', 'SPAR Gauteng')
            ->set('region', 'GP')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('spar_pharmacy_groups', ['name' => 'SPAR Gauteng', 'region' => 'GP']);
    }

    public function test_group_admin_cannot_access_group_management(): void
    {
        $this->actingAs($this->user('group_admin'));

        Livewire::test(SparGroups::class)->assertForbidden();
    }

    public function test_pharmacy_staff_cannot_access_group_management(): void
    {
        $this->actingAs($this->user('pharmacy_staff'));

        Livewire::test(SparGroups::class)->assertForbidden();
    }

    public function test_super_admin_can_toggle_group_active(): void
    {
        $this->actingAs($this->user('super_admin'));
        $group = SparPharmacyGroup::create(['name' => 'Toggle Me']);

        Livewire::test(SparGroups::class)->call('toggleActive', $group->id);

        $this->assertFalse($group->fresh()->is_active);
    }
}
