<?php

namespace Tests\Feature\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use App\Models\User;
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
}
