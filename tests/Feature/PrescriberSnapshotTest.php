<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\Prescription;
use App\Models\User;
use App\Livewire\Doctor\PrescriptionBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies the prescriber-identity snapshot: HPCSA number / name / qualification
 * are frozen onto the prescription at sign time, and do NOT drift when the
 * doctor's profile changes afterwards. (HPCSA + build-spec legal-provenance rule.)
 */
class PrescriberSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function doctorWithProfile(): User
    {
        $doctor = User::factory()->create([
            'role' => UserRole::Doctor,
            'first_name' => 'Sarah',
            'last_name' => 'Naidoo',
        ]);

        DoctorProfile::create([
            'user_id' => $doctor->id,
            'hpcsa_number' => 'MP1234567',
            'speciality' => 'General Practice',
            'qualification' => 'MBChB (UCT)',
            'consultation_fee' => 45000,
            'followup_fee' => 25000,
            'consultation_duration' => 15,
            'is_verified' => true,
            'accepts_new_patients' => true,
        ]);

        return $doctor;
    }

    private function consultationFor(User $doctor): Consultation
    {
        $patient = User::factory()->create(['role' => UserRole::Patient]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'general',
            'status' => 'in_progress',
            'appointment_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:15',
            'fee_amount' => 45000,
        ]);

        return Consultation::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'in_progress',
            'diagnosis' => 'Test diagnosis',
            'started_at' => now(),
        ]);
    }

    public function test_signing_snapshots_prescriber_identity(): void
    {
        $doctor = $this->doctorWithProfile();
        $consultation = $this->consultationFor($doctor);

        Livewire::actingAs($doctor)
            ->test(PrescriptionBuilder::class, ['consultation' => $consultation])
            ->set('items', [[
                'medication_id' => null,
                'medication_name' => 'Amoxicillin',
                'strength' => '500mg',
                'form' => 'capsule',
                'dosage' => '1 capsule',
                'frequency' => 'three times daily',
                'route' => 'oral',
                'duration_days' => 7,
                'quantity' => 21,
                'unit_price' => 5000,
                'line_total' => 5000,
                'instructions' => 'After food',
                'substitution_allowed' => true,
            ]])
            ->call('signPrescription')
            ->assertHasNoErrors();

        $prescription = Prescription::where('doctor_id', $doctor->id)->firstOrFail();

        $this->assertSame('Dr Sarah Naidoo', $prescription->prescriber_name);
        $this->assertSame('MP1234567', $prescription->prescriber_hpcsa_number);
        $this->assertSame('MBChB (UCT)', $prescription->prescriber_qualification);
    }

    public function test_snapshot_does_not_drift_when_profile_changes_later(): void
    {
        $doctor = $this->doctorWithProfile();
        $consultation = $this->consultationFor($doctor);

        Livewire::actingAs($doctor)
            ->test(PrescriptionBuilder::class, ['consultation' => $consultation])
            ->set('items', [[
                'medication_id' => null,
                'medication_name' => 'Amoxicillin',
                'strength' => '500mg',
                'form' => 'capsule',
                'dosage' => '1 capsule',
                'frequency' => 'three times daily',
                'route' => 'oral',
                'duration_days' => 7,
                'quantity' => 21,
                'unit_price' => 5000,
                'line_total' => 5000,
                'instructions' => null,
                'substitution_allowed' => true,
            ]])
            ->call('signPrescription')
            ->assertHasNoErrors();

        $prescription = Prescription::where('doctor_id', $doctor->id)->firstOrFail();

        // Doctor later corrects their HPCSA number and qualification.
        $doctor->doctorProfile->update([
            'hpcsa_number' => 'MP9999999',
            'qualification' => 'MBChB, FCP(SA)',
        ]);

        // The already-issued script must still carry the ORIGINAL values.
        $this->assertSame('MP1234567', $prescription->fresh()->prescriber_hpcsa_number);
        $this->assertSame('MBChB (UCT)', $prescription->fresh()->prescriber_qualification);
    }
}
