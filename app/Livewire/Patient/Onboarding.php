<?php

namespace App\Livewire\Patient;

use App\Models\ConsentRecord;
use App\Models\PatientAllergy;
use App\Models\PatientChronicCondition;
use App\Models\PatientProfile;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Onboarding extends Component
{
    public int $currentStep = 1;
    public int $totalSteps = 4;

    // Step 1: Personal Information
    public string $phone = '';
    public string $date_of_birth = '';
    public string $gender = '';
    public string $id_number = '';
    public string $address = '';
    public string $city = '';
    public string $province = '';
    public string $postal_code = '';

    // Step 2: Medical History
    public string $blood_type = '';
    public ?string $height_cm = '';
    public ?string $weight_kg = '';
    public bool $is_smoker = false;
    public bool $consumes_alcohol = false;
    public string $surgical_history = '';
    public string $family_history = '';
    public string $medical_aid_name = '';
    public string $medical_aid_number = '';
    public string $medical_aid_plan = '';
    public string $emergency_contact_name = '';
    public string $emergency_contact_phone = '';
    public string $emergency_contact_relationship = '';

    // Step 3: Allergies & Conditions
    public array $allergies = [];
    public string $new_allergen = '';
    public string $new_allergy_severity = 'moderate';
    public string $new_allergy_reaction = '';

    public array $conditions = [];
    public string $new_condition_name = '';
    public string $new_condition_date = '';
    public string $new_condition_notes = '';

    // Step 4: Consent
    public bool $consent_terms = false;
    public bool $consent_privacy = false;
    public bool $consent_data_processing = false;
    public bool $consent_medical_records = false;

    public function mount(): void
    {
        $user = Auth::user();

        // Pre-fill from user record
        $this->phone = $user->phone ?? '';
        $this->date_of_birth = $user->date_of_birth?->format('Y-m-d') ?? '';
        $this->gender = $user->gender ?? '';
        $this->id_number = $user->id_number ?? '';
        $this->address = $user->address ?? '';
        $this->city = $user->city ?? '';
        $this->province = $user->province ?? '';
        $this->postal_code = $user->postal_code ?? '';

        // Pre-fill from existing profile if resuming
        $profile = $user->patientProfile;
        if ($profile) {
            $this->blood_type = $profile->blood_type ?? '';
            $this->height_cm = $profile->height_cm ? (string) $profile->height_cm : '';
            $this->weight_kg = $profile->weight_kg ? (string) $profile->weight_kg : '';
            $this->is_smoker = $profile->is_smoker;
            $this->consumes_alcohol = $profile->consumes_alcohol;
            $this->surgical_history = $profile->surgical_history ?? '';
            $this->family_history = $profile->family_history ?? '';
            $this->medical_aid_name = $profile->medical_aid_name ?? '';
            $this->medical_aid_number = $profile->medical_aid_number ?? '';
            $this->medical_aid_plan = $profile->medical_aid_plan ?? '';
            $this->emergency_contact_name = $profile->emergency_contact_name ?? '';
            $this->emergency_contact_phone = $profile->emergency_contact_phone ?? '';
            $this->emergency_contact_relationship = $profile->emergency_contact_relationship ?? '';

            // Load existing allergies
            foreach ($profile->allergies as $allergy) {
                $this->allergies[] = [
                    'id' => $allergy->id,
                    'allergen' => $allergy->allergen,
                    'severity' => $allergy->severity,
                    'reaction' => $allergy->reaction ?? '',
                ];
            }

            // Load existing conditions
            foreach ($profile->chronicConditions as $condition) {
                $this->conditions[] = [
                    'id' => $condition->id,
                    'condition_name' => $condition->condition_name,
                    'diagnosed_date' => $condition->diagnosed_date?->format('Y-m-d') ?? '',
                    'notes' => $condition->notes ?? '',
                ];
            }
        }
    }

    /**
     * Validate and move to next step.
     */
    public function nextStep(): void
    {
        $this->validateCurrentStep();
        $this->saveCurrentStep();

        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    /**
     * Go back to previous step.
     */
    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    /**
     * Go to a specific step (only if already passed).
     */
    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= $this->currentStep) {
            $this->currentStep = $step;
        }
    }

    /**
     * Add an allergy to the list.
     */
    public function addAllergy(): void
    {
        $this->validate([
            'new_allergen' => 'required|string|max:255',
            'new_allergy_severity' => 'required|in:mild,moderate,severe',
        ]);

        $this->allergies[] = [
            'id' => null,
            'allergen' => $this->new_allergen,
            'severity' => $this->new_allergy_severity,
            'reaction' => $this->new_allergy_reaction,
        ];

        $this->new_allergen = '';
        $this->new_allergy_severity = 'moderate';
        $this->new_allergy_reaction = '';
    }

    /**
     * Remove an allergy from the list.
     */
    public function removeAllergy(int $index): void
    {
        unset($this->allergies[$index]);
        $this->allergies = array_values($this->allergies);
    }

    /**
     * Add a chronic condition to the list.
     */
    public function addCondition(): void
    {
        $this->validate([
            'new_condition_name' => 'required|string|max:255',
        ]);

        $this->conditions[] = [
            'id' => null,
            'condition_name' => $this->new_condition_name,
            'diagnosed_date' => $this->new_condition_date,
            'notes' => $this->new_condition_notes,
        ];

        $this->new_condition_name = '';
        $this->new_condition_date = '';
        $this->new_condition_notes = '';
    }

    /**
     * Remove a condition from the list.
     */
    public function removeCondition(int $index): void
    {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);
    }

    /**
     * Complete the onboarding process.
     */
    public function complete(): void
    {
        $this->validateCurrentStep();
        $this->saveCurrentStep();

        // Mark onboarding as complete
        $profile = Auth::user()->patientProfile;
        $profile->update([
            'onboarding_complete' => true,
            'consent_given' => true,
            'consent_given_at' => now(),
        ]);

        // Record consents
        $consents = [
            'terms_of_service' => $this->consent_terms,
            'privacy_policy' => $this->consent_privacy,
            'data_processing' => $this->consent_data_processing,
            'medical_records_access' => $this->consent_medical_records,
        ];

        foreach ($consents as $type => $granted) {
            ConsentRecord::create([
                'user_id' => Auth::id(),
                'consent_type' => $type,
                'version' => '1.0',
                'granted' => $granted,
                'ip_address' => request()->ip(),
                'granted_at' => now(),
            ]);
        }

        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * Validate the current step.
     */
    private function validateCurrentStep(): void
    {
        match ($this->currentStep) {
            1 => $this->validate([
                'phone' => 'required|string|max:20',
                'date_of_birth' => 'required|date|before:today',
                'gender' => 'required|in:male,female,other',
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:100',
                'province' => 'required|string|max:50',
                'postal_code' => 'required|string|max:10',
            ]),
            2 => $this->validate([
                'emergency_contact_name' => 'required|string|max:255',
                'emergency_contact_phone' => 'required|string|max:20',
                'emergency_contact_relationship' => 'required|string|max:50',
            ]),
            3 => null, // allergies/conditions are optional — no validation needed
            4 => $this->validate([
                'consent_terms' => 'accepted',
                'consent_privacy' => 'accepted',
                'consent_data_processing' => 'accepted',
                'consent_medical_records' => 'accepted',
            ]),
            default => null,
        };
    }

    /**
     * Save the current step's data.
     */
    private function saveCurrentStep(): void
    {
        $user = Auth::user();

        match ($this->currentStep) {
            1 => $this->savePersonalInfo($user),
            2 => $this->saveMedicalHistory($user),
            3 => $this->saveAllergiesAndConditions($user),
            default => null,
        };
    }

    private function savePersonalInfo($user): void
    {
        $user->update([
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'id_number' => $this->id_number ?: null,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
        ]);

        // Create or update patient profile
        PatientProfile::updateOrCreate(
            ['user_id' => $user->id],
            []
        );
    }

    private function saveMedicalHistory($user): void
    {
        $user->refresh();
        $user->patientProfile->update([
            'blood_type' => $this->blood_type ?: null,
            'height_cm' => $this->height_cm ?: null,
            'weight_kg' => $this->weight_kg ?: null,
            'is_smoker' => $this->is_smoker,
            'consumes_alcohol' => $this->consumes_alcohol,
            'surgical_history' => $this->surgical_history ?: null,
            'family_history' => $this->family_history ?: null,
            'medical_aid_name' => $this->medical_aid_name ?: null,
            'medical_aid_number' => $this->medical_aid_number ?: null,
            'medical_aid_plan' => $this->medical_aid_plan ?: null,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relationship' => $this->emergency_contact_relationship,
        ]);
    }

    private function saveAllergiesAndConditions($user): void
    {
        $profile = $user->patientProfile;

        // Sync allergies
        $profile->allergies()->delete();
        foreach ($this->allergies as $allergy) {
            PatientAllergy::create([
                'patient_profile_id' => $profile->id,
                'allergen' => $allergy['allergen'],
                'severity' => $allergy['severity'],
                'reaction' => $allergy['reaction'] ?: null,
            ]);
        }

        // Sync conditions
        $profile->chronicConditions()->delete();
        foreach ($this->conditions as $condition) {
            PatientChronicCondition::create([
                'patient_profile_id' => $profile->id,
                'condition_name' => $condition['condition_name'],
                'diagnosed_date' => $condition['diagnosed_date'] ?: null,
                'notes' => $condition['notes'] ?: null,
                'is_active' => true,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.patient.onboarding')
            ->layout('layouts.app');
    }
}
