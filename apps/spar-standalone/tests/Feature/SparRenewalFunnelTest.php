<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\MyMedsTracker;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparPatientSession;

/**
 * SPAR Close-the-Loop FR-E1 / decision D3 (2026-09-22): the platform's core
 * idea — on a standalone renewal the patient is pushed to ZapMed to renew.
 * The renewal card surfaces "Book a ZapMed online consult" → zapmed.co.za.
 */
class SparRenewalFunnelTest extends TestCase
{
    use RefreshDatabase;

    private SparPatient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $group = SparPharmacyGroup::create(['name' => 'G']);
        $pharmacy = SparPharmacy::create([
            'group_id' => $group->id, 'name' => 'Plett', 'spar_store_id' => 'A', 'is_active' => true,
        ]);

        $this->patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => 'REN-1', 'dependent_code' => '00',
            'first_name' => 'Thabo', 'last_name' => 'M', 'cellphone' => '0821234567',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => 'opted_in', 'consent_given_at' => now(),
        ]);

        SparPrescriptionJourney::create([
            'spar_patient_id' => $this->patient->id, 'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => 'REN-MED', 'status' => 'renewal_due', 'total_dispenses' => 6,
            'dispenses_completed' => 6, 'start_date' => now()->subMonths(6),
            'renewal_due_date' => now()->subDay(),
            'medications' => [['name' => 'CHRONIC-MED']],
        ]);
    }

    private function establishSession(): void
    {
        app(SparPatientSession::class)->establish($this->patient);
        app(SparPatientSession::class)->markVerified();
    }

    public function test_standalone_renewal_card_pushes_to_zapmed(): void
    {
        // Standalone host, funnel enabled (platform default).
        Config::set('spar.host_mode', 'standalone');
        Config::set('spar.online_consult.enabled', true);
        Config::set('spar.online_consult.url', 'https://zapmed.co.za');
        Config::set('spar.online_consult.label', 'Book a ZapMed online consult');

        $this->establishSession();

        Livewire::test(MyMedsTracker::class)
            ->assertSet('step', 'dashboard')
            ->assertSee('Book a ZapMed online consult')
            ->assertSee('zapmed.co.za');
    }

    public function test_renewal_funnel_can_be_disabled_by_config(): void
    {
        Config::set('spar.host_mode', 'standalone');
        Config::set('spar.online_consult.enabled', false);

        $this->establishSession();

        Livewire::test(MyMedsTracker::class)
            ->assertDontSee('Book a ZapMed online consult');
    }
}
