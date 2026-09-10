<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\MyMedsTracker;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparPatientSession;

/**
 * National identity, Phase 5 — a patient who fills at two SPAR stores sees each
 * medication labelled with the pharmacy it came from on the mobi tracker.
 */
class SparMultiStoreViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracker_labels_each_prescription_with_its_pharmacy(): void
    {
        config(['spar.link.require_otp_reverify' => false]);

        $plett = SparPharmacy::create(['name' => 'SPAR Plettenberg Bay', 'spar_store_id' => 'PLT', 'is_active' => true]);
        $knysna = SparPharmacy::create(['name' => 'SPAR Knysna', 'spar_store_id' => 'KNY', 'is_active' => true]);

        $patient = SparPatient::create([
            'spar_pharmacy_id' => $plett->id,
            'profile_code' => '990001', 'dependent_code' => '00',
            'first_name' => 'Priya', 'last_name' => 'Naidoo',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => 'opted_in', 'consent_given_at' => now(),
        ]);

        foreach ([$plett, $knysna] as $ph) {
            SparPrescriptionJourney::create([
                'spar_patient_id' => $patient->id, 'spar_pharmacy_id' => $ph->id,
                'script_number' => 'S-' . $ph->id, 'status' => 'active',
                'total_dispenses' => 6, 'dispenses_completed' => 1, 'start_date' => now(), 'medications' => [],
            ]);
        }

        // Establish the no-login patient session (as the signed link does).
        app(SparPatientSession::class)->establish($patient);

        Livewire::test(MyMedsTracker::class)
            ->assertSee('Collected at: SPAR Plettenberg Bay')
            ->assertSee('Collected at: SPAR Knysna');
    }
}
