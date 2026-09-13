<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\Stats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T7.7 — admin Stats page: renders all six metric sections, is admin-gated, period switch works.
 */
class StatsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_load_stats_page_with_all_sections(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/admin/stats')
            ->assertOk()
            ->assertSee('Business overview')
            ->assertSee('Acquisition')
            ->assertSee('Subscriptions')
            ->assertSee('Orders')
            ->assertSee('Finance')
            ->assertSee('CRM funnel');
    }

    public function test_non_admin_cannot_access_stats(): void
    {
        $patient = User::factory()->create(['role' => UserRole::Patient, 'email_verified_at' => now()]);

        $this->actingAs($patient)
            ->get('/admin/stats')
            ->assertForbidden(); // role:admin middleware 403s authenticated non-admins
    }

    public function test_period_switch_updates_component(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(Stats::class)
            ->assertSet('period', 'month')
            ->call('setPeriod', 'all')
            ->assertSet('period', 'all')
            // Invalid period is ignored.
            ->call('setPeriod', 'nonsense')
            ->assertSet('period', 'all');
    }
}
