<?php

namespace Tests\Feature\Spar;

use Zapmed\SparCore\Livewire\Admin\SparConsents;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SPAR standalone Phase 1.11 — admin consent section (spec FR-10, AC-10).
 */
class SparAdminConsentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_consent_list_shows_status_counts_and_detail_trail(): void
    {
        $pharmacy = SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);

        $consented = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'cellphone' => '0821234567',
        ]);
        $consented->optIn('web', ['source' => 'patient', 'ip_address' => '1.2.3.4']);

        SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '551',
            'consent_status' => 'pending',
        ]);

        Livewire::actingAs($this->admin())
            ->test(SparConsents::class)
            ->assertSet('statusFilter', 'all')
            ->assertSee('Consented')
            ->call('view', $consented->id)
            ->assertSet('viewingId', $consented->id)
            ->assertSee('Consent granted')
            ->assertSee('web'); // audit trail channel
    }

    public function test_stats_reflect_consent_states(): void
    {
        $pharmacy = SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);

        $p = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'first_name' => 'A', 'last_name' => 'B', 'cellphone' => '0821234567',
        ]);
        $p->optIn('web');

        $stats = Livewire::actingAs($this->admin())
            ->test(SparConsents::class)
            ->instance()->stats;

        $this->assertSame(1, $stats['consented']);
    }
}
