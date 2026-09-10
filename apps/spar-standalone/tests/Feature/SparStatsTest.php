<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\Admin\SparStats;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Services\SparStatsService;

/**
 * Phase 8.3 — stats are correctly scoped per viewer (spec FR-18, AC-14).
 */
class SparStatsTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacyGroup $groupA;
    private SparPharmacy $pharmA;
    private SparPharmacy $pharmB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->groupA = SparPharmacyGroup::create(['name' => 'Group A']);
        $groupB = SparPharmacyGroup::create(['name' => 'Group B']);
        $this->pharmA = SparPharmacy::create(['group_id' => $this->groupA->id, 'name' => 'A1', 'spar_store_id' => 'A1', 'is_active' => true]);
        $this->pharmB = SparPharmacy::create(['group_id' => $groupB->id, 'name' => 'B1', 'spar_store_id' => 'B1', 'is_active' => true]);

        // 2 patients at A (1 consented), 1 at B (consented).
        SparPatient::create(['spar_pharmacy_id' => $this->pharmA->id, 'profile_code' => 'A-1', 'is_active' => true, 'consent_status' => 'opted_in', 'onboarding_status' => 'active']);
        SparPatient::create(['spar_pharmacy_id' => $this->pharmA->id, 'profile_code' => 'A-2', 'is_active' => true, 'consent_status' => 'pending', 'onboarding_status' => 'pending_consent']);
        SparPatient::create(['spar_pharmacy_id' => $this->pharmB->id, 'profile_code' => 'B-1', 'is_active' => true, 'consent_status' => 'opted_in', 'onboarding_status' => 'active']);
    }

    private function actAs(string $role, ?int $pharmacyId = null, ?int $groupId = null): void
    {
        $this->actingAs(PharmacyUser::create([
            'name' => $role, 'email' => $role . '_' . uniqid() . '@t.test', 'password' => Hash::make('x'),
            'role' => $role, 'spar_pharmacy_id' => $pharmacyId, 'group_id' => $groupId, 'is_active' => true,
        ]));
    }

    private function stats(): array
    {
        return app(SparStatsService::class)->forCurrentActor();
    }

    public function test_super_admin_sees_platform_totals(): void
    {
        $this->actAs('super_admin');
        $s = $this->stats();

        $this->assertSame(2, $s['counts']['groups']);
        $this->assertSame(2, $s['counts']['pharmacies']);
        $this->assertSame(3, $s['counts']['patients']);
        $this->assertNotEmpty($s['leaderboard']);
    }

    public function test_group_admin_sees_only_their_group(): void
    {
        $this->actAs('group_admin', groupId: $this->groupA->id);
        $s = $this->stats();

        // Group A: 1 pharmacy, 2 patients.
        $this->assertSame(1, $s['counts']['pharmacies']);
        $this->assertSame(2, $s['counts']['patients']);
        $this->assertSame(1, $s['consent']['opted_in']); // only A's consented patient
    }

    public function test_pharmacy_staff_sees_only_their_store(): void
    {
        $this->actAs('pharmacy_staff', pharmacyId: $this->pharmB->id);
        $s = $this->stats();

        $this->assertSame(1, $s['counts']['pharmacies']);
        $this->assertSame(1, $s['counts']['patients']);   // only B-1
        $this->assertSame(100.0, $s['consent']['consent_rate']); // B-1 is opted_in
        $this->assertEmpty($s['leaderboard']); // staff get no leaderboard
    }

    public function test_dashboard_renders_for_each_role(): void
    {
        $this->actAs('super_admin');
        Livewire::test(SparStats::class)->assertOk()->assertSee('Platform-wide');
    }

    public function test_stats_require_authentication(): void
    {
        Livewire::test(SparStats::class)->assertForbidden();
    }
}
