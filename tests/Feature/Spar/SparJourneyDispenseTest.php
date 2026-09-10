<?php

namespace Tests\Feature\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterisation tests: pin down CURRENT behaviour of the SPAR prescription
 * journey + dispense record lifecycle so the standalone package extraction can
 * prove it changed nothing. Do NOT "fix" behaviour here — only describe it.
 */
class SparJourneyDispenseTest extends TestCase
{
    use RefreshDatabase;

    private function pharmacy(array $overrides = []): SparPharmacy
    {
        return SparPharmacy::create(array_merge([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '1000001',
            'bhf_code' => '1000001',
            'supports_delivery' => true,
            'delivery_fee' => 5000,
            'is_active' => true,
        ], $overrides));
    }

    private function patient(SparPharmacy $pharmacy, array $overrides = []): SparPatient
    {
        return SparPatient::create(array_merge([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'consent_status' => 'opted_in',
            'consent_given_at' => now(),
            'is_active' => true,
        ], $overrides));
    }

    private function journey(SparPatient $patient, array $overrides = []): SparPrescriptionJourney
    {
        return SparPrescriptionJourney::create(array_merge([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $patient->spar_pharmacy_id,
            'script_number' => '422564',
            'status' => 'active',
            'total_dispenses' => 6,
            'dispenses_completed' => 0,
            'start_date' => now(),
            'next_dispense_date' => now()->addMonth(),
        ], $overrides));
    }

    public function test_encrypted_profile_code_round_trips(): void
    {
        $patient = $this->patient($this->pharmacy());

        // Reload from DB — decryption must yield the original value.
        $this->assertSame('550', $patient->fresh()->profile_code);

        // Raw stored value must NOT be plaintext (POPIA).
        $raw = \DB::table('spar_patients')->where('id', $patient->id)->value('profile_code');
        $this->assertNotSame('550', $raw);
    }

    public function test_record_dispense_increments_and_schedules_next(): void
    {
        $journey = $this->journey($this->patient($this->pharmacy()));

        $journey->recordDispense();
        $journey->refresh();

        $this->assertSame(1, $journey->dispenses_completed);
        $this->assertSame('active', $journey->status);
        $this->assertNotNull($journey->next_dispense_date);
        $this->assertSame(5, $journey->remaining_dispenses);
    }

    public function test_final_dispense_flips_journey_to_renewal_due(): void
    {
        $journey = $this->journey($this->patient($this->pharmacy()), [
            'total_dispenses' => 2,
            'dispenses_completed' => 1,
        ]);

        $journey->recordDispense(); // now 2 of 2

        $journey->refresh();
        $this->assertTrue($journey->isFinalDispense());
        $this->assertSame('renewal_due', $journey->status);
        $this->assertNull($journey->next_dispense_date);
        $this->assertSame(0, $journey->remaining_dispenses);
        $this->assertSame(100, $journey->progress_percent);
    }

    public function test_mark_collected_records_dispense_on_journey(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->patient($pharmacy);
        $journey = $this->journey($patient);

        $dispense = SparDispenseRecord::create([
            'journey_id' => $journey->id,
            'spar_patient_id' => $patient->id,
            'dispense_number' => 1,
            'status' => 'upcoming',
            'due_date' => now()->addDays(3),
        ]);

        $dispense->markCollected();

        $this->assertSame('collected', $dispense->fresh()->status);
        $this->assertSame('collection', $dispense->fresh()->fulfillment_type);
        $this->assertNotNull($dispense->fresh()->completed_at);
        // Cascade: journey dispense count advanced.
        $this->assertSame(1, $journey->fresh()->dispenses_completed);
    }
}
