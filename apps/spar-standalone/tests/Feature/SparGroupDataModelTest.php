<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;

/**
 * Phase 7.1 — platform hierarchy DATA MODEL (spec FR-14, FR-15).
 * Groups table, pharmacy→group link, and the 4-tier role helpers.
 */
class SparGroupDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_has_group_table_and_columns(): void
    {
        $this->assertTrue(Schema::hasTable('spar_pharmacy_groups'));
        $this->assertTrue(Schema::hasColumn('spar_pharmacies', 'group_id'));
        $this->assertTrue(Schema::hasColumn('pharmacy_users', 'group_id'));
    }

    public function test_group_has_many_pharmacies(): void
    {
        $group = SparPharmacyGroup::create(['name' => 'SPAR Western Cape', 'region' => 'WC']);

        $p1 = SparPharmacy::create(['group_id' => $group->id, 'name' => 'Mega City', 'spar_store_id' => 'WC-1', 'is_active' => true]);
        $p2 = SparPharmacy::create(['group_id' => $group->id, 'name' => 'Claremont', 'spar_store_id' => 'WC-2', 'is_active' => true]);

        $this->assertCount(2, $group->pharmacies);
        $this->assertTrue($p1->group->is($group));
        $this->assertSame('spar-western-cape', $group->slug); // auto-slug
    }

    public function test_slug_is_unique_when_names_collide(): void
    {
        $a = SparPharmacyGroup::create(['name' => 'SPAR KZN']);
        $b = SparPharmacyGroup::create(['name' => 'SPAR KZN']);

        $this->assertSame('spar-kzn', $a->slug);
        $this->assertSame('spar-kzn-2', $b->slug);
    }

    public function test_pharmacy_user_role_helpers_cover_four_tiers(): void
    {
        $make = fn (string $role) => PharmacyUser::create([
            'name' => ucfirst($role), 'email' => "{$role}@t.test", 'password' => Hash::make('x'),
            'role' => $role, 'is_active' => true,
        ]);

        $this->assertTrue($make('super_admin')->isSuperAdmin());
        $this->assertTrue($make('admin')->isSuperAdmin());            // legacy alias
        $this->assertTrue($make('group_admin')->isGroupAdmin());
        $this->assertTrue($make('pharmacy_admin')->isPharmacyAdmin());
        $this->assertTrue($make('pharmacy_staff')->isPharmacyStaff());

        $this->assertTrue($make('super_admin_2')->canManageUsers() === false); // unknown role
        $this->assertTrue(PharmacyUser::where('role', 'pharmacy_staff')->first()->canManageUsers() === false);
        $this->assertTrue(PharmacyUser::where('role', 'group_admin')->first()->canManageUsers());
    }
}
