<?php

namespace Tests\Feature\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use App\Models\User;
use Zapmed\SparCore\Enums\SparPatientSignalType;
use Zapmed\SparCore\Services\SparActionService;
use Zapmed\SparCore\Services\SparReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterisation tests for SPAR reminder gating.
 * Pins CURRENT rules: reminders only for consented patients WITH a contact
 * (linked user + phone). No contact => skipped. This is the exact behaviour the
 * standalone data-shape work (Phase 6) will later have to preserve/replace.
 */
class SparReminderTest extends TestCase
{
    use RefreshDatabase;

    private SparReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SparReminderService();
    }

    private function pharmacy(): SparPharmacy
    {
        return SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'bhf_code' => '3000001',
            'supports_delivery' => true,
            'is_active' => true,
        ]);
    }

    private function dueDispense(SparPatient $patient, SparPharmacy $pharmacy): SparDispenseRecord
    {
        $journey = SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => '111222',
            'status' => 'active',
            'total_dispenses' => 6,
            'dispenses_completed' => 0,
            'start_date' => now(),
            'medications' => [['name' => 'CO-COPALIA 10MG TAB 28']],
        ]);

        return SparDispenseRecord::create([
            'journey_id' => $journey->id,
            'spar_patient_id' => $patient->id,
            'dispense_number' => 1,
            'status' => 'upcoming',
            'due_date' => now()->addDays(3), // within the 7-day reminder window
        ]);
    }

    public function test_due_for_reminder_scope_selects_upcoming_within_window(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'consent_status' => 'opted_in',
            'is_active' => true,
        ]);
        $this->dueDispense($patient, $pharmacy);

        $this->assertSame(1, SparDispenseRecord::dueForReminder()->count());
    }

    public function test_consented_patient_with_contact_gets_reminder(): void
    {
        $pharmacy = $this->pharmacy();
        $user = User::factory()->create([
            'role' => 'patient',
            'phone' => '0821234567',
            'first_name' => 'Thabo',
        ]);
        $patient = SparPatient::create([
            'user_id' => $user->id,
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'consent_status' => 'opted_in',
            'is_active' => true,
        ]);
        $this->dueDispense($patient, $pharmacy);

        $stats = $this->service->processReminders();

        $this->assertSame(1, $stats['reminders_sent']);
        $this->assertSame(0, $stats['errors']);
    }

    public function test_patient_without_contact_is_skipped(): void
    {
        $pharmacy = $this->pharmacy();
        // No linked user => no phone => cannot be reached (the real SPAR-extract situation).
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'consent_status' => 'opted_in',
            'is_active' => true,
        ]);
        $this->dueDispense($patient, $pharmacy);

        $stats = $this->service->processReminders();

        $this->assertSame(0, $stats['reminders_sent']);
    }

    public function test_unconsented_patient_is_skipped(): void
    {
        $pharmacy = $this->pharmacy();
        $user = User::factory()->create(['role' => 'patient', 'phone' => '0821234567']);
        $patient = SparPatient::create([
            'user_id' => $user->id,
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'consent_status' => 'opted_out',
            'is_active' => true,
        ]);
        $this->dueDispense($patient, $pharmacy);

        $stats = $this->service->processReminders();

        $this->assertSame(0, $stats['reminders_sent']);
    }

    /* --------------------------------------------------------------------- */
    /* FR-B4 — close-the-loop reminder honouring (snooze + patient opt-out)  */
    /* --------------------------------------------------------------------- */

    private function contactablePatient(SparPharmacy $pharmacy): SparPatient
    {
        $user = User::factory()->create([
            'role' => 'patient',
            'phone' => '0821234567',
            'first_name' => 'Thabo',
        ]);

        return SparPatient::create([
            'user_id' => $user->id,
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'consent_status' => 'opted_in',
            'is_active' => true,
        ]);
    }

    public function test_snoozed_dispense_is_not_reminded(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->contactablePatient($pharmacy);
        $dispense = $this->dueDispense($patient, $pharmacy);

        // Patient (or staff) deferred this item — snooze into the future.
        $dispense->snoozeActionable(30);

        $stats = $this->service->processReminders();

        $this->assertSame(0, $stats['reminders_sent'], 'A snoozed item must not be reminded.');
    }

    public function test_woken_snooze_is_reminded_again(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->contactablePatient($pharmacy);
        $dispense = $this->dueDispense($patient, $pharmacy);

        // Snooze then move the wake time into the past: the reminder should fire.
        $dispense->snoozeActionable(1);
        $dispense->forceFill(['snoozed_until' => now()->subDay()])->save();

        $stats = $this->service->processReminders();

        $this->assertSame(1, $stats['reminders_sent'], 'A woken (expired) snooze must be reminded again.');
    }

    public function test_stop_reminders_signal_suppresses_reminder(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->contactablePatient($pharmacy);
        $dispense = $this->dueDispense($patient, $pharmacy);

        $this->recordSignal($dispense, SparPatientSignalType::StopReminders);

        $stats = $this->service->processReminders();

        $this->assertSame(0, $stats['reminders_sent'], 'A "stop reminders" opt-out must suppress the reminder.');
    }

    public function test_ignore_future_signal_suppresses_reminder(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->contactablePatient($pharmacy);
        $dispense = $this->dueDispense($patient, $pharmacy);

        $this->recordSignal($dispense, SparPatientSignalType::IgnoreFuture);

        $stats = $this->service->processReminders();

        $this->assertSame(0, $stats['reminders_sent'], 'A "never for this item" opt-out must suppress the reminder.');
    }

    public function test_normal_item_with_no_opt_out_still_reminds(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->contactablePatient($pharmacy);
        $dispense = $this->dueDispense($patient, $pharmacy);

        // A non-suppressing signal (patient asked to be reminded next cycle) plus
        // an already-woken snooze — the reminder must still fire (not opt-out).
        $this->recordSignal($dispense, SparPatientSignalType::RemindInDays, ['days' => 1]);
        $dispense->refresh();
        $dispense->forceFill(['snoozed_until' => now()->subDay()])->save();

        $stats = $this->service->processReminders();

        $this->assertSame(1, $stats['reminders_sent'], 'A cadence signal is not an opt-out and must still remind once due.');
    }

    private function recordSignal(SparDispenseRecord $dispense, SparPatientSignalType $signal, array $payload = []): void
    {
        app(SparActionService::class)->recordPatientResponse($dispense, $signal, $payload);
    }
}
