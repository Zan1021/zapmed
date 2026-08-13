<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\Medication;
use App\Models\PatientProfile;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PatientJourneyTest extends TestCase
{
    use RefreshDatabase;

    private User $patient;
    private User $doctor;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin
        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@zapmed.co.za',
        ]);

        // Create a verified doctor with availability
        $this->doctor = User::factory()->create([
            'role' => UserRole::Doctor,
            'first_name' => 'Sarah',
            'last_name' => 'Naidoo',
            'email' => 'doctor@zapmed.co.za',
        ]);

        DoctorProfile::create([
            'user_id' => $this->doctor->id,
            'hpcsa_number' => 'MP1234567',
            'speciality' => 'General Practice',
            'qualification' => 'MBChB (UCT)',
            'university' => 'University of Cape Town',
            'year_qualified' => 2015,
            'bio' => 'Experienced GP specialising in telehealth.',
            'consultation_fee' => 45000,
            'followup_fee' => 25000,
            'consultation_duration' => 15,
            'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'available_from' => '08:00',
            'available_to' => '17:00',
            'accepts_new_patients' => true,
            'is_verified' => true,
        ]);

        // Create patient
        $this->patient = User::factory()->create([
            'role' => UserRole::Patient,
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'email' => 'patient@test.com',
        ]);

        // Seed some medications
        Medication::create([
            'name' => 'Fluconazole',
            'generic_name' => 'fluconazole',
            'form' => 'capsule',
            'strength' => '150mg',
            'schedule' => 'S4',
            'price' => 5000,
            'is_active' => true,
        ]);

        Medication::create([
            'name' => 'Metronidazole',
            'generic_name' => 'metronidazole',
            'form' => 'tablet',
            'strength' => '400mg',
            'schedule' => 'S4',
            'price' => 3500,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Unauthenticated patient can access treatment pages.
     */
    public function test_treatment_pages_are_accessible(): void
    {
        $response = $this->get('/weight-loss');
        $response->assertStatus(200);
        $response->assertSee('Weight Loss');

        $response = $this->get('/thrush-treatment');
        $response->assertStatus(200);
        $response->assertSee('Thrush');

        $response = $this->get('/erectile-dysfunction-treatment');
        $response->assertStatus(200);
        $response->assertSee('Erectile Dysfunction');
    }

    /**
     * Test 2: Patient can access registration page.
     */
    public function test_registration_page_is_accessible(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
    }

    /**
     * Test 3: Patient is redirected to onboarding before booking.
     */
    public function test_patient_without_profile_redirected_to_onboarding(): void
    {
        $this->actingAs($this->patient);

        $response = $this->get('/book');
        $response->assertRedirect('/onboarding');
    }

    /**
     * Test 4: Patient can complete onboarding.
     */
    public function test_patient_can_complete_onboarding(): void
    {
        $this->actingAs($this->patient);

        // Step 1: Personal information
        Livewire::test(\App\Livewire\Patient\Onboarding::class)
            ->set('phone', '0821234567')
            ->set('date_of_birth', '1990-05-15')
            ->set('gender', 'female')
            ->set('id_number', '9005150012083')
            ->set('address', '123 Test Street')
            ->set('city', 'Cape Town')
            ->set('province', 'western-cape')
            ->set('postal_code', '7700')
            ->call('nextStep')
            ->assertHasNoErrors();

        // Verify patient profile was created
        $this->assertDatabaseHas('patient_profiles', [
            'user_id' => $this->patient->id,
        ]);
    }

    /**
     * Test 5: Full onboarding flow through all steps.
     */
    public function test_full_onboarding_flow(): void
    {
        $this->actingAs($this->patient);

        $component = Livewire::test(\App\Livewire\Patient\Onboarding::class);

        // Step 1: Personal info
        $component
            ->set('phone', '0821234567')
            ->set('date_of_birth', '1990-05-15')
            ->set('gender', 'female')
            ->set('address', '123 Test Street')
            ->set('city', 'Cape Town')
            ->set('province', 'western-cape')
            ->set('postal_code', '7700')
            ->call('nextStep')
            ->assertSet('currentStep', 2);

        // Step 2: Medical history
        $component
            ->set('emergency_contact_name', 'John Doe')
            ->set('emergency_contact_phone', '0829876543')
            ->set('emergency_contact_relationship', 'Spouse')
            ->set('height_cm', '165')
            ->set('weight_kg', '70')
            ->call('nextStep')
            ->assertSet('currentStep', 3);

        // Step 3: Allergies & Conditions (optional, skip)
        $component
            ->call('nextStep')
            ->assertSet('currentStep', 4);

        // Step 4: Consent
        $component
            ->set('consent_terms', true)
            ->set('consent_privacy', true)
            ->set('consent_data_processing', true)
            ->set('consent_medical_records', true)
            ->call('complete')
            ->assertRedirect(route('dashboard'));

        // Verify onboarding is complete
        $profile = PatientProfile::where('user_id', $this->patient->id)->first();
        $this->assertNotNull($profile);
        $this->assertTrue($profile->onboarding_complete);
    }

    /**
     * Test 6: Patient can access booking after onboarding.
     */
    public function test_onboarded_patient_can_access_booking(): void
    {
        $this->completeOnboarding();
        $this->actingAs($this->patient);

        $response = $this->get('/book');
        $response->assertStatus(200);
    }

    /**
     * Test 7: Booking flow — treatment selection and assessment.
     */
    public function test_booking_treatment_selection(): void
    {
        $this->completeOnboarding();
        $this->actingAs($this->patient);

        $component = Livewire::test(\App\Livewire\Patient\BookAppointment::class);

        // Step 1: Select treatment category and treatment
        $component
            ->set('appointmentType', 'general-health')
            ->set('selectedTreatment', 'thrush-treatment')
            ->call('proceedToAssessment')
            ->assertHasNoErrors()
            ->assertSet('step', 2);
    }

    /**
     * Test 8: Doctor can view appointments and start consultation.
     */
    public function test_doctor_can_access_consultation(): void
    {
        $this->completeOnboarding();

        // Create an appointment directly
        $appointment = Appointment::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'type' => 'general-health',
            'status' => 'confirmed',
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '10:15',
            'duration_minutes' => 15,
            'reason' => 'Thrush treatment',
            'communication_preference' => 'video',
            'fee_amount' => 45000,
            'is_paid' => true,
        ]);

        $this->actingAs($this->doctor);

        $component = Livewire::test(\App\Livewire\Doctor\ConsultationScreen::class, ['appointment' => $appointment]);

        // Verify consultation was created
        $this->assertDatabaseHas('consultations', [
            'appointment_id' => $appointment->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Test 9: Doctor can complete consultation with notes.
     */
    public function test_doctor_can_complete_consultation(): void
    {
        $this->completeOnboarding();

        $appointment = Appointment::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'type' => 'general-health',
            'status' => 'confirmed',
            'appointment_date' => now()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '10:15',
            'duration_minutes' => 15,
            'reason' => 'Thrush consultation',
            'communication_preference' => 'video',
            'fee_amount' => 45000,
            'is_paid' => true,
        ]);

        $this->actingAs($this->doctor);

        $component = Livewire::test(\App\Livewire\Doctor\ConsultationScreen::class, ['appointment' => $appointment])
            ->set('presenting_complaint', 'Patient presents with vaginal itching and white discharge for 3 days')
            ->set('diagnosis', 'Vaginal candidiasis (thrush)')
            ->set('icd10_code', 'B37.3')
            ->set('treatment_plan', 'Fluconazole 150mg single dose, topical clotrimazole cream')
            ->call('completeConsultation')
            ->assertRedirect(route('doctor.dashboard'));

        // Verify consultation is completed
        $this->assertDatabaseHas('consultations', [
            'appointment_id' => $appointment->id,
            'status' => 'completed',
        ]);

        // Verify appointment is completed
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);
    }

    /**
     * Test 10: Doctor can create a prescription.
     */
    public function test_doctor_can_create_prescription(): void
    {
        $this->completeOnboarding();

        $appointment = Appointment::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'type' => 'general-health',
            'status' => 'completed',
            'appointment_date' => now()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '10:15',
            'duration_minutes' => 15,
            'fee_amount' => 45000,
            'is_paid' => true,
        ]);

        $consultation = Consultation::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'completed',
            'presenting_complaint' => 'Vaginal thrush',
            'diagnosis' => 'Vaginal candidiasis',
            'treatment_plan' => 'Fluconazole 150mg',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($this->doctor);

        $medication = Medication::first();

        $component = Livewire::test(\App\Livewire\Doctor\PrescriptionBuilder::class, ['consultation' => $consultation])
            ->set('medicationName', 'Fluconazole')
            ->set('medicationForm', 'capsule')
            ->set('medicationStrength', '150mg')
            ->set('medicationPrice', 5000)
            ->set('selectedMedicationId', $medication->id)
            ->set('dosage', '150mg stat')
            ->set('frequency', 'once only')
            ->set('route', 'oral')
            ->set('quantity', 1)
            ->set('instructions', 'Take one capsule immediately')
            ->call('addItem')
            ->assertHasNoErrors();

        // Sign the prescription
        $component->call('signPrescription')
            ->assertSet('prescriptionSigned', true);

        // Verify prescription was created
        $this->assertDatabaseHas('prescriptions', [
            'consultation_id' => $consultation->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'signed',
        ]);

        // Verify payment record was created for medication
        $this->assertDatabaseHas('payments', [
            'patient_id' => $this->patient->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Test 11: Patient can view their prescriptions.
     */
    public function test_patient_can_view_prescriptions(): void
    {
        $this->completeOnboarding();
        $this->actingAs($this->patient);

        $response = $this->get('/prescriptions');
        $response->assertStatus(200);
    }

    /**
     * Test 12: Patient can view their appointments.
     */
    public function test_patient_can_view_appointments(): void
    {
        $this->completeOnboarding();
        $this->actingAs($this->patient);

        $response = $this->get('/appointments');
        $response->assertStatus(200);
    }

    /**
     * Test 13: Unauthorized access is blocked.
     */
    public function test_patient_cannot_access_doctor_dashboard(): void
    {
        $this->completeOnboarding();
        $this->actingAs($this->patient);

        $response = $this->get('/doctor/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Test 14: Doctor cannot access other doctor's consultation.
     */
    public function test_doctor_cannot_access_other_doctors_consultation(): void
    {
        $this->completeOnboarding();

        $otherDoctor = User::factory()->create(['role' => UserRole::Doctor]);
        DoctorProfile::create([
            'user_id' => $otherDoctor->id,
            'hpcsa_number' => 'MP9999999',
            'speciality' => 'GP',
            'qualification' => 'MBChB (Wits)',
            'university' => 'University of Witwatersrand',
            'year_qualified' => 2018,
            'consultation_fee' => 45000,
            'followup_fee' => 25000,
            'consultation_duration' => 15,
            'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'available_from' => '08:00',
            'available_to' => '17:00',
            'accepts_new_patients' => true,
            'is_verified' => true,
        ]);

        $appointment = Appointment::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'type' => 'general-health',
            'status' => 'confirmed',
            'appointment_date' => now()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '10:15',
            'duration_minutes' => 15,
            'fee_amount' => 45000,
            'is_paid' => true,
        ]);

        $this->actingAs($otherDoctor);

        // Should abort 403
        $response = $this->get("/doctor/consultation/{$appointment->id}");
        $response->assertStatus(403);
    }

    /**
     * Helper: Complete onboarding for the test patient.
     */
    private function completeOnboarding(): void
    {
        $this->patient->update([
            'phone' => '0821234567',
            'date_of_birth' => '1990-05-15',
            'gender' => 'female',
            'address' => '123 Test Street',
            'city' => 'Cape Town',
            'province' => 'western-cape',
            'postal_code' => '7700',
        ]);

        PatientProfile::create([
            'user_id' => $this->patient->id,
            'onboarding_complete' => true,
            'consent_given' => true,
            'consent_given_at' => now(),
            'emergency_contact_name' => 'John Doe',
            'emergency_contact_phone' => '0829876543',
            'emergency_contact_relationship' => 'Spouse',
        ]);
    }
}
