<?php

namespace Tests\Feature\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SPAR standalone Phase 1.10 — reminders send via MessagingChannel using
 * SPAR-owned identity (no ZapMed User), and renewal copy varies by host mode.
 */
class SparMessagingTest extends TestCase
{
    use RefreshDatabase;

    private function pharmacy(): SparPharmacy
    {
        return SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
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
            'due_date' => now()->addDays(3),
        ]);
    }

    public function test_standalone_patient_with_own_contact_gets_reminder(): void
    {
        config(['spar.host_mode' => 'standalone']);

        $pharmacy = $this->pharmacy();
        // No linked User — contact is SPAR-owned (the standalone reality).
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'cellphone' => '0821234567',
            'consent_status' => 'opted_in',
            'is_active' => true,
        ]);
        $this->dueDispense($patient, $pharmacy);

        $stats = (new SparReminderService())->processReminders();

        $this->assertSame(1, $stats['reminders_sent']);
        $this->assertSame(0, $stats['errors']);
    }

    public function test_renewal_message_omits_zapmed_doctor_in_standalone(): void
    {
        config(['spar.host_mode' => 'standalone']);

        $service = new SparReminderService();
        $method = new \ReflectionMethod($service, 'buildRenewalMessage');
        $method->setAccessible(true);

        $msg = $method->invoke($service, 'Thabo', 'Pharmacy at SPAR - Test');

        $this->assertStringNotContainsStringIgnoringCase('ZapMed doctor', $msg);
    }

    public function test_renewal_reminder_sends_for_renewal_due_journey(): void
    {
        config(['spar.host_mode' => 'integrated']);

        $pharmacy = $this->pharmacy();
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'cellphone' => '0821234567',
            'consent_status' => 'opted_in',
            'is_active' => true,
        ]);

        // A journey due for renewal (final dispense reached, renewal window).
        SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => 'R-1',
            'status' => 'renewal_due',
            'total_dispenses' => 6,
            'dispenses_completed' => 6,
            'start_date' => now()->subMonths(6),
            'renewal_due_date' => now()->addDays(3),
            'medications' => [['name' => 'CO-COPALIA 10MG TAB 28']],
        ]);

        $stats = (new SparReminderService())->processReminders();

        $this->assertSame(1, $stats['renewal_reminders_sent']);
    }
}
