<?php

namespace Tests\Feature\Spar;

use App\Models\SparPatient;
use App\Models\SparPharmacy;
use Zapmed\SparCore\Services\SparImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SPAR standalone Phase 1 — dual-mode onboarding + SPAR-owned identity.
 * Covers spec FR-6 (import vs pharmacist_capture), FR-6.4 (identity rule),
 * and the onboarding lifecycle on SparPatient.
 */
class SparOnboardingIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function writeCsv(string $contents): string
    {
        $dir = storage_path('app/spar-test');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . '/import_' . uniqid() . '.csv';
        file_put_contents($path, $contents);

        return $path;
    }

    // ---- Identity rule (FR-6.4) -------------------------------------------

    public function test_identity_incomplete_without_name_or_contact(): void
    {
        $pharmacy = SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);

        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'consent_status' => 'pending',
        ]);

        $this->assertFalse($patient->hasCompleteIdentity());
        $this->assertSame('awaiting_contact', $patient->refreshOnboardingStatus());
    }

    public function test_identity_complete_with_name_and_one_channel(): void
    {
        $pharmacy = SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);

        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'cellphone' => '0821234567',
            'consent_status' => 'pending',
        ]);

        $this->assertTrue($patient->hasCompleteIdentity());
        // Complete identity but not yet consented => pending_consent.
        $this->assertSame('pending_consent', $patient->refreshOnboardingStatus());

        // Encrypted round-trip: cellphone decrypts to the plaintext.
        $this->assertSame('0821234567', $patient->fresh()->cellphone);
    }

    public function test_consent_moves_patient_to_active(): void
    {
        $pharmacy = SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);

        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'email' => 'thabo@example.co.za',
        ]);

        $patient->optIn('web');

        $this->assertSame('active', $patient->onboarding_status);
        $this->assertTrue($patient->isOnboarded());
    }

    // ---- Mode B: pharmacist_capture ---------------------------------------

    public function test_import_mode_b_seeds_history_but_leaves_awaiting_contact(): void
    {
        config(['spar.onboarding_mode' => 'pharmacist_capture']);

        // Sales-extract shape: no contact columns.
        $csv = "Store Name|Profile Code|Dependent Code|Client Name|Item Description|Script Number|Date|Sales Value Excl|Repeats\n"
            . "Pharmacy at SPAR - Mega City|550|0|NGCOBO SR|CO-COPALIA 10MG TAB 28|422564|2026-07-29|242.38|6\n";

        $batch = (new SparImportService())->importFile($this->writeCsv($csv));

        // profile_code is encrypted at rest, so don't query by the raw code.
        $patient = SparPatient::first();
        $this->assertNotNull($patient);
        $this->assertSame('550', $patient->profile_code);
        // History seeded...
        $this->assertSame(1, $patient->journeys()->count());
        // ...but no contact => not contactable => awaiting_contact.
        $this->assertFalse($patient->isContactable());
        $this->assertSame('awaiting_contact', $patient->onboarding_status);
    }

    // ---- Mode A: import ----------------------------------------------------

    public function test_import_mode_a_populates_identity_from_file(): void
    {
        config(['spar.onboarding_mode' => 'import']);

        $csv = "Store Name|Profile Code|Dependent Code|First Name|Surname|Cellphone|Email|Item Description|Script Number|Date|Sales Value Excl|Repeats\n"
            . "Pharmacy at SPAR - Mega City|550|0|Thabo|Mokoena|0821234567|thabo@example.co.za|CO-COPALIA 10MG TAB 28|422564|2026-07-29|242.38|6\n";

        $batch = (new SparImportService())->importFile($this->writeCsv($csv));

        $patient = SparPatient::first();
        $this->assertNotNull($patient);
        $this->assertSame('Thabo', $patient->first_name);
        $this->assertSame('Mokoena', $patient->last_name);
        $this->assertSame('0821234567', $patient->cellphone);
        $this->assertTrue($patient->isContactable());
        // Complete identity, not yet consented => pending_consent.
        $this->assertSame('pending_consent', $patient->onboarding_status);
    }
}
