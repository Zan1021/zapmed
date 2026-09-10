<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enforces the internal-vs-patient-visible clinical note boundary (build-spec
 * is_internal requirement). patientVisibleData() must never leak internal notes.
 */
class ClinicalNoteVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function consultation(): Consultation
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

        return Consultation::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'completed',
            'presenting_complaint' => 'Sore throat',
            'history_of_presenting_illness' => 'INTERNAL: 3 days, smoker',
            'examination_findings' => 'INTERNAL: inflamed pharynx',
            'diagnosis' => 'Acute pharyngitis',
            'icd10_code' => 'J02.9',
            'treatment_plan' => 'Rest, fluids, antibiotics',
            'doctor_notes' => 'INTERNAL: query malingering, watch repeat sick-note requests',
            'follow_up_notes' => 'INTERNAL: chase bloods',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function test_patient_visible_data_excludes_internal_fields(): void
    {
        $data = $this->consultation()->patientVisibleData();

        foreach (Consultation::INTERNAL_FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $data, "Internal field {$field} leaked to patient data");
        }

        // Sanity: none of the values contain the INTERNAL marker.
        $this->assertStringNotContainsString('INTERNAL', json_encode($data));
    }

    public function test_patient_visible_data_includes_safe_fields(): void
    {
        $data = $this->consultation()->patientVisibleData();

        $this->assertSame('Acute pharyngitis', $data['diagnosis']);
        $this->assertSame('Rest, fluids, antibiotics', $data['treatment_plan']);
        $this->assertSame('J02.9', $data['icd10_code']);
    }

    public function test_is_internal_field_classifies_correctly(): void
    {
        $this->assertTrue(Consultation::isInternalField('doctor_notes'));
        $this->assertTrue(Consultation::isInternalField('examination_findings'));
        $this->assertFalse(Consultation::isInternalField('diagnosis'));
        $this->assertFalse(Consultation::isInternalField('treatment_plan'));
    }
}
