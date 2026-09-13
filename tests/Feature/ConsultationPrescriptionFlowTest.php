<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Doctor\ConsultationScreen;
use App\Livewire\Doctor\PrescriptionBuilder;
use App\Mail\PrescriptionReady;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 5 — consultation -> clinical notes -> prescription -> PDF-ready + patient mail.
 *
 * Verifies the server-side behaviour behind the Chrome-verified flow: completing a
 * consultation requires the mandatory clinical fields and finalises both consultation
 * and appointment; signing a prescription snapshots the prescriber, creates line items
 * + a pending medication payment, and notifies the patient.
 */
class ConsultationPrescriptionFlowTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor, 'first_name' => 'Naz', 'last_name' => 'ConsultDoc']);
        DoctorProfile::create([
            'user_id' => $doctor->id,
            'hpcsa_number' => 'MP0999111',
            'speciality' => 'General Practitioner',
            'qualification' => 'MBChB',
            'doctor_type' => 'full_time',
            'is_verified' => true,
        ]);

        $patient = User::factory()->create([
            'role' => UserRole::Patient,
            'address' => '10 Clinic Rd', 'city' => 'Durban', 'province' => 'KwaZulu-Natal', 'postal_code' => '4001',
        ]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id,
            'type' => 'general-health', 'status' => 'confirmed',
            'appointment_date' => now()->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '10:30',
            'communication_preference' => 'video', 'fee_amount' => 45000, 'is_paid' => true,
        ]);

        return [$doctor, $patient, $appointment];
    }

    public function test_complete_consultation_requires_mandatory_fields(): void
    {
        [$doctor, , $appointment] = $this->scenario();

        Livewire::actingAs($doctor)
            ->test(ConsultationScreen::class, ['appointment' => $appointment])
            ->call('completeConsultation')
            ->assertHasErrors(['presenting_complaint', 'diagnosis', 'treatment_plan']);
    }

    public function test_complete_consultation_finalises_records(): void
    {
        [$doctor, , $appointment] = $this->scenario();

        Livewire::actingAs($doctor)
            ->test(ConsultationScreen::class, ['appointment' => $appointment])
            ->set('presenting_complaint', 'Tension headaches for 3 weeks')
            ->set('diagnosis', 'Tension-type headache')
            ->set('treatment_plan', 'Regular analgesia and review in 2 weeks')
            ->call('completeConsultation');

        $this->assertSame('completed', $appointment->fresh()->status);
        $this->assertDatabaseHas('consultations', [
            'appointment_id' => $appointment->id,
            'status' => 'completed',
        ]);
    }

    public function test_sign_prescription_creates_items_payment_and_notifies_patient(): void
    {
        Mail::fake();

        [$doctor, $patient, $appointment] = $this->scenario();

        $consultation = Consultation::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'diagnosis' => 'Tension-type headache',
        ]);

        Livewire::actingAs($doctor)
            ->test(PrescriptionBuilder::class, ['consultation' => $consultation])
            ->set('medicationName', 'Omeprazole')
            ->set('medicationForm', 'capsule')
            ->set('medicationStrength', '20mg')
            ->set('dosage', '1 capsule')
            ->set('frequency', 'once daily')
            ->set('route', 'oral')
            ->set('quantity', 7)
            ->call('addItem')
            ->call('signPrescription')
            ->assertSet('prescriptionSigned', true);

        $prescription = Prescription::where('consultation_id', $consultation->id)->first();
        $this->assertNotNull($prescription);
        $this->assertSame('signed', $prescription->status);
        // Prescriber legal identity snapshotted at issue time.
        $this->assertSame('MP0999111', $prescription->prescriber_hpcsa_number);
        $this->assertSame(1, $prescription->items()->count());

        // Pending medication payment created.
        $this->assertDatabaseHas('payments', [
            'patient_id' => $patient->id,
            'status' => 'pending',
        ]);

        // Patient notified.
        Mail::assertQueued(PrescriptionReady::class);
    }
}
