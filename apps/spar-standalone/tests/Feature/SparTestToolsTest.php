<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\Admin\SparImports;
use Zapmed\SparCore\Livewire\PatientDetail;
use Zapmed\SparCore\Models\SparConsent;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparImportBatch;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\Channels\WhatsAppChannel;

/**
 * Test-mode UI affordances built for demo/UAT (2026-09-15):
 *   1. Staff "Send opt-in via WhatsApp" on PatientDetail — sends the
 *      onboarding_consent template + signed mobi link, even to a NOT-yet
 *      consented patient (the opt-in is what solicits consent).
 *   2. Admin/test-only "Delete all patients" reset on SparImports — hard-guarded
 *      (super-admin + non-production + typed DELETE), wipes patient data so the
 *      two import files can be re-run.
 *
 * NOTE: the opt-in delivery is verified at the WhatsAppChannel level (the
 * component's sendOptIn is a thin wrapper over it) plus a Livewire render smoke;
 * the reset is verified by its DB side-effects and its canReset guard, which is
 * what actually matters for safety.
 */
class SparTestToolsTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacy $pharmacy;

    protected function setUp(): void
    {
        parent::setUp();
        $group = SparPharmacyGroup::create(['name' => 'G']);
        $this->pharmacy = SparPharmacy::create([
            'group_id' => $group->id, 'name' => 'Plett', 'spar_store_id' => 'A', 'is_active' => true,
        ]);
    }

    private function admin(): PharmacyUser
    {
        return PharmacyUser::create([
            'name' => 'Admin', 'email' => 'admin' . uniqid() . '@t.test',
            'password' => Hash::make('x'), 'role' => 'admin',
            'spar_pharmacy_id' => null, 'is_active' => true,
        ]);
    }

    private function staff(): PharmacyUser
    {
        return PharmacyUser::create([
            'name' => 'Clerk', 'email' => 'clerk' . uniqid() . '@t.test',
            'password' => Hash::make('x'), 'role' => 'pharmacy_staff',
            'spar_pharmacy_id' => $this->pharmacy->id, 'is_active' => true,
        ]);
    }

    private function patient(array $overrides = []): SparPatient
    {
        return SparPatient::create(array_merge([
            'spar_pharmacy_id' => $this->pharmacy->id,
            'onboarding_pharmacy_id' => $this->pharmacy->id,
            'profile_code' => '99' . random_int(1000, 9999), 'dependent_code' => '00',
            'first_name' => 'Priya', 'last_name' => 'Naidoo',
            'cellphone' => '0832810909',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => 'pending_consent',
        ], $overrides));
    }

    // --- Opt-in send (channel-level: the sendOptIn wrapper calls exactly this) ---

    public function test_optin_template_send_succeeds_on_log_driver_for_non_consented_patient(): void
    {
        Config::set('spar.whatsapp.enabled', true);
        Config::set('spar.whatsapp.driver', 'log');
        Config::set('spar.whatsapp.templates.onboarding_consent', 'onboarding_consent');

        $patient = $this->patient(); // pending_consent + has cellphone
        $channel = new WhatsAppChannel();

        // Proactive template send (outside 24h window) — how the opt-in goes out.
        $this->assertTrue($channel->send($patient, [
            'template' => 'onboarding_consent',
            'vars' => [$patient->first_name],
            'link' => 'https://spar.test/track/1?sig=x',
        ]));
    }

    public function test_optin_channel_not_reachable_without_cellphone(): void
    {
        Config::set('spar.whatsapp.enabled', true);
        Config::set('spar.whatsapp.driver', 'log');

        $patient = $this->patient(['cellphone' => null]);
        $this->assertFalse((new WhatsAppChannel())->canReach($patient));
    }

    public function test_optin_channel_not_reachable_when_disabled(): void
    {
        Config::set('spar.whatsapp.enabled', false);
        $patient = $this->patient();
        $this->assertFalse((new WhatsAppChannel())->canReach($patient));
    }

    public function test_patient_detail_renders_send_optin_button(): void
    {
        Config::set('spar.whatsapp.enabled', true);
        Config::set('spar.whatsapp.driver', 'log');

        $patient = $this->patient();
        // Give the patient activity at the staff's pharmacy so the national-scope
        // visibility check (journey/dispense based) resolves them.
        SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id, 'spar_pharmacy_id' => $this->pharmacy->id,
            'script_number' => 'V-1', 'status' => 'active', 'total_dispenses' => 6,
            'dispenses_completed' => 0, 'start_date' => now(), 'medications' => [['name' => 'MED']],
        ]);
        $this->actingAs($this->staff());

        Livewire::test(PatientDetail::class, ['patient' => $patient])
            ->assertSee('Send opt-in via WhatsApp');
    }

    // --- Reset all patients (verified by side-effects + guard) ----------------

    public function test_reset_wipes_patient_data_for_admin(): void
    {
        $this->seedFullPatientGraph();
        $this->assertGreaterThan(0, SparPatient::count());

        $this->actingAs($this->admin());

        Livewire::test(SparImports::class)
            ->assertSet('canReset', true)
            ->call('openResetModal')
            ->set('resetConfirm', 'DELETE')
            ->call('resetAllPatients');

        $this->assertSame(0, SparPatient::count());
        $this->assertSame(0, SparPrescriptionJourney::count());
        $this->assertSame(0, SparDispenseRecord::count());
        $this->assertSame(0, SparOrder::count());
        $this->assertSame(0, SparConsent::count());
        $this->assertSame(0, SparImportBatch::count());

        // Pharmacy + group are preserved.
        $this->assertSame(1, SparPharmacy::count());
    }

    public function test_reset_does_nothing_without_typed_confirmation(): void
    {
        $this->seedFullPatientGraph();
        $before = SparPatient::count();
        $this->actingAs($this->admin());

        Livewire::test(SparImports::class)
            ->call('openResetModal')
            ->set('resetConfirm', 'nope')
            ->call('resetAllPatients');

        $this->assertSame($before, SparPatient::count()); // nothing deleted
    }

    public function test_reset_guard_false_and_noop_for_non_admin_staff(): void
    {
        $this->seedFullPatientGraph();
        $before = SparPatient::count();
        $this->actingAs($this->staff());

        // SparImports is gated at mount() to super-admin/admin (commit 7f11283).
        // A pharmacy_staff actor is 403'd outright — the strongest guard, so the
        // reset UI is never even reachable for them.
        Livewire::test(SparImports::class)->assertForbidden();

        // And nothing was deleted.
        $this->assertSame($before, SparPatient::count());
    }

    public function test_reset_guard_false_in_production_even_for_admin(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $this->seedFullPatientGraph();
        $before = SparPatient::count();
        $this->actingAs($this->admin());

        Livewire::test(SparImports::class)
            ->assertSet('canReset', false)
            ->set('resetConfirm', 'DELETE')
            ->call('resetAllPatients');

        $this->assertSame($before, SparPatient::count());
    }

    private function seedFullPatientGraph(): void
    {
        $p = $this->patient(['consent_status' => 'opted_in', 'consent_given_at' => now()]);
        $journey = SparPrescriptionJourney::create([
            'spar_patient_id' => $p->id, 'spar_pharmacy_id' => $this->pharmacy->id,
            'script_number' => 'S-1', 'status' => 'active', 'total_dispenses' => 6,
            'dispenses_completed' => 1, 'start_date' => now(), 'medications' => [['name' => 'MED']],
        ]);
        SparDispenseRecord::create([
            'spar_patient_id' => $p->id, 'journey_id' => $journey->id,
            'dispense_number' => 1, 'status' => 'completed',
            'due_date' => now(), 'completed_at' => now(),
        ]);
        SparOrder::create([
            'spar_patient_id' => $p->id, 'spar_pharmacy_id' => $this->pharmacy->id,
            'reference' => 'ORD-1', 'type' => 'collection', 'status' => 'new',
        ]);
        SparConsent::create([
            'spar_patient_id' => $p->id, 'consent_type' => 'chronic_medication',
            'version' => '1.0', 'granted' => true, 'channel' => 'whatsapp', 'source' => 'patient',
            'granted_at' => now(),
        ]);
        SparImportBatch::create([
            'filename' => 'SalesExtract.csv', 'source' => 'manual', 'status' => 'completed',
            'records_total' => 1, 'records_processed' => 1, 'records_created' => 1,
            'imported_by' => 1, 'started_at' => now(), 'completed_at' => now(),
        ]);
    }
}
