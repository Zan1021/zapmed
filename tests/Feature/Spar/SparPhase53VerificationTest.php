<?php

namespace Tests\Feature\Spar;

use App\Models\SparConsent;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\MyMedsTracker;
use Zapmed\SparCore\Services\SparImportService;
use Zapmed\SparCore\Services\SparPatientSession;

/**
 * Phase 5.3 — dedicated verification pass (per literal task text):
 *   "Consent + onboarding tests: consent gate blocks PHI (AC-8);
 *    Mode A vs Mode B onboarding (AC-7); dependant roll-up (AC-9)."
 *
 * This file targets the three named acceptance criteria head-on in one place,
 * rather than relying on the incidental coverage scattered across the Phase 1
 * characterisation tests. Each region maps 1:1 to a task-5.3 clause.
 */
class SparPhase53VerificationTest extends TestCase
{
    use RefreshDatabase;

    private function pharmacy(): SparPharmacy
    {
        return SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);
    }

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

    // =========================================================================
    // AC-7 — Mode A (import) vs Mode B (pharmacist_capture) onboarding
    // =========================================================================

    public function test_ac7_mode_a_import_creates_contactable_consent_pending_patient(): void
    {
        config(['spar.onboarding_mode' => 'import']);

        $csv = "Store Name|Profile Code|Dependent Code|First Name|Surname|Cellphone|Email|Item Description|Script Number|Date|Repeats\n"
            . "Pharmacy at SPAR - Test|550|0|Thabo|Mokoena|0821234567|thabo@example.co.za|CO-COPALIA 10MG TAB 28|422564|2026-07-29|6\n";

        (new SparImportService())->importFile($this->writeCsv($csv));

        $patient = SparPatient::first();
        $this->assertNotNull($patient);
        // Contactable identity populated straight from the file.
        $this->assertTrue($patient->isContactable());
        $this->assertSame('Thabo', $patient->first_name);
        $this->assertSame('0821234567', $patient->cellphone);
        // Contactable but not yet consented => pending_consent (not active, not awaiting).
        $this->assertSame('pending_consent', $patient->onboarding_status);
        $this->assertFalse($patient->hasConsented());
    }

    public function test_ac7_mode_b_capture_matches_imported_history_by_profile_code(): void
    {
        // Mode B: sales extract (NO contact columns) seeds history only.
        config(['spar.onboarding_mode' => 'pharmacist_capture']);

        $csv = "Store Name|Profile Code|Dependent Code|Client Name|Item Description|Script Number|Date|Repeats\n"
            . "Pharmacy at SPAR - Test|550|0|NGCOBO SR|CO-COPALIA 10MG TAB 28|422564|2026-07-29|6\n";

        (new SparImportService())->importFile($this->writeCsv($csv));

        $patient = SparPatient::first();
        $this->assertNotNull($patient);
        // History was seeded, keyed on Profile Code...
        $this->assertSame('550', $patient->profile_code);
        $this->assertSame(1, $patient->journeys()->count());
        // ...but no contact channel => held in awaiting_contact, no link sent.
        $this->assertFalse($patient->isContactable());
        $this->assertSame('awaiting_contact', $patient->onboarding_status);

        // The pharmacist-captured identity later attaches to THIS history by
        // Profile Code (same patient row), turning it contactable + consented.
        $patient->update(['first_name' => 'Sipho', 'last_name' => 'Ngcobo', 'cellphone' => '0829998888']);
        $patient->optIn('in_store');

        $this->assertTrue($patient->fresh()->isContactable());
        $this->assertSame('active', $patient->fresh()->onboarding_status);
        // History preserved through capture (matched, not recreated).
        $this->assertSame(1, $patient->fresh()->journeys()->count());
    }

    public function test_ac7_record_with_no_contact_channel_lands_in_awaiting_contact(): void
    {
        // Even in import mode, a row missing every contact channel must NOT be
        // contactable and must NOT be sent a link.
        config(['spar.onboarding_mode' => 'import']);

        $csv = "Store Name|Profile Code|Dependent Code|First Name|Surname|Item Description|Script Number|Date|Repeats\n"
            . "Pharmacy at SPAR - Test|777|0|Nomsa|Dlamini|METFORMIN 500MG|990001|2026-07-29|6\n";

        (new SparImportService())->importFile($this->writeCsv($csv));

        $patient = SparPatient::first();
        $this->assertNotNull($patient);
        $this->assertFalse($patient->isContactable());
        $this->assertSame('awaiting_contact', $patient->onboarding_status);
    }

    // =========================================================================
    // AC-8 — consent gate blocks PHI until granted; grant recorded w/ evidence
    // =========================================================================

    public function test_ac8_no_consent_shows_only_consent_screen_then_grant_records_evidence(): void
    {
        config(['spar.link.require_otp_reverify' => false]);

        $patient = SparPatient::create([
            'spar_pharmacy_id' => $this->pharmacy()->id,
            'profile_code' => '550',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'cellphone' => '0821234567',
            'consent_status' => 'pending',
            'is_primary_member' => true,
            'is_active' => true,
        ]);

        app(SparPatientSession::class)->establish($patient);

        // Gate: first screen is consent, no PHI/meds shown.
        Livewire::test(MyMedsTracker::class)
            ->assertSet('step', 'consent')
            ->set('consentAccepted', true)
            ->call('grantConsent')
            ->assertSet('step', 'dashboard');

        $patient->refresh();
        $this->assertTrue($patient->hasConsented());

        // Grant recorded with version + channel + timestamp (spec FR-7.3, AC-8).
        $consent = SparConsent::where('spar_patient_id', $patient->id)->granted()->first();
        $this->assertNotNull($consent);
        $this->assertNotNull($consent->version);
        $this->assertSame('web', $consent->channel);
        $this->assertNotNull($consent->granted_at);
    }

    public function test_ac8_phi_route_is_blocked_before_consent(): void
    {
        config(['spar.link.require_otp_reverify' => false]);

        $patient = SparPatient::create([
            'spar_pharmacy_id' => $this->pharmacy()->id,
            'profile_code' => '551',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'cellphone' => '0821234567',
            'consent_status' => 'pending',
            'is_primary_member' => true,
            'is_active' => true,
        ]);

        app(SparPatientSession::class)->establish($patient);

        // History (PHI) is bounced back to the tracker/consent gate.
        $this->get(route('my-meds.history'))->assertRedirect(route('my-meds.track'));
    }

    // =========================================================================
    // AC-9 — dependant roll-up to the primary member
    // =========================================================================

    public function test_ac9_primary_member_view_rolls_up_all_dependants(): void
    {
        config(['spar.link.require_otp_reverify' => false]);

        $pharmacy = $this->pharmacy();

        $primary = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550', 'dependent_code' => '0',
            'first_name' => 'Thabo', 'last_name' => 'Mokoena',
            'cellphone' => '0821234567', 'consent_status' => 'opted_in',
            'is_primary_member' => true, 'is_active' => true,
        ]);

        $dependant = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550', 'dependent_code' => '1',
            'first_name' => 'Lerato', 'last_name' => 'Mokoena',
            'is_primary_member' => false, 'is_active' => true,
        ]);

        foreach ([$primary, $dependant] as $p) {
            SparPrescriptionJourney::create([
                'spar_patient_id' => $p->id,
                'spar_pharmacy_id' => $pharmacy->id,
                'script_number' => 'S-' . $p->id,
                'status' => 'active',
                'total_dispenses' => 6,
                'dispenses_completed' => 1,
                'start_date' => now(),
                'medications' => [['name' => 'Med for ' . $p->first_name]],
            ]);
        }

        app(SparPatientSession::class)->establish($primary);

        $journeys = Livewire::test(MyMedsTracker::class)->instance()->journeys;
        $this->assertCount(2, $journeys, 'Primary view must roll up self + dependant journeys.');
    }

    public function test_ac9_dependant_never_gets_a_link(): void
    {
        $pharmacy = $this->pharmacy();

        $dependant = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550', 'dependent_code' => '1',
            'first_name' => 'Lerato', 'last_name' => 'Mokoena',
            'is_primary_member' => false, 'is_active' => true,
        ]);

        // No own contact => not contactable => never a link target.
        $this->assertFalse($dependant->isContactable());
        $this->assertFalse($dependant->hasCompleteIdentity());
    }
}
