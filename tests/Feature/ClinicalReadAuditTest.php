<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Prescription;
use App\Models\User;
use App\Services\ClinicalAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * POPIA accountability: reading a clinical document must be audited
 * (who viewed what, when). Verifies PdfController logs a read via
 * ClinicalAuditLogger, and that unauthorised users are blocked (no read).
 */
class ClinicalReadAuditTest extends TestCase
{
    use RefreshDatabase;

    private function scaffold(): array
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $patient = User::factory()->create(['role' => UserRole::Patient]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'general',
            'status' => 'completed',
            'appointment_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:15',
            'fee_amount' => 45000,
        ]);

        $consultation = Consultation::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'completed',
            'diagnosis' => 'Acute pharyngitis',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $prescription = Prescription::create([
            'consultation_id' => $consultation->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'signed',
            'diagnosis' => 'Acute pharyngitis',
            'total_amount' => 5000,
            'payment_status' => 'paid',
            'pharmacy_status' => 'pending',
            'signed_at' => now(),
        ]);

        return compact('doctor', 'patient', 'prescription');
    }

    public function test_prescription_download_is_audited(): void
    {
        ['patient' => $patient, 'prescription' => $prescription] = $this->scaffold();

        $this->mock(ClinicalAuditLogger::class, function (MockInterface $mock) use ($prescription, $patient) {
            $mock->shouldReceive('logRead')
                ->once()
                ->with('prescription', $prescription->id, $patient->id, 'download');
        });

        $this->actingAs($patient)
            ->get(route('pdf.prescription', $prescription))
            ->assertOk();
    }

    public function test_unauthorised_user_cannot_read_and_is_not_audited(): void
    {
        ['prescription' => $prescription] = $this->scaffold();
        $stranger = User::factory()->create(['role' => UserRole::Patient]);

        // logRead must NEVER be called for a blocked access.
        $this->mock(ClinicalAuditLogger::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('logRead');
        });

        $this->actingAs($stranger)
            ->get(route('pdf.prescription', $prescription))
            ->assertForbidden();
    }
}
