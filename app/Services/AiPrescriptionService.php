<?php

namespace App\Services;

use App\Models\Consultation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiPrescriptionService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', '');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
        $this->baseUrl = 'https://api.openai.com/v1';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && $this->apiKey !== 'your-key-here';
    }

    /**
     * Auto-fill dosage/frequency/duration for a specific medication based on diagnosis and patient context.
     *
     * @param string $medicationName e.g. "Amoxicillin 500mg Capsules"
     * @param string $form e.g. "capsule"
     * @param string $strength e.g. "500mg"
     * @param Consultation $consultation (for diagnosis, patient profile)
     * @return array{dosage: string, frequency: string, route: string, duration_days: int, quantity: int, instructions: string}
     */
    public function suggestDosage(string $medicationName, string $form, string $strength, Consultation $consultation): array
    {
        if (!$this->isConfigured()) {
            return $this->getDefaultDosage($form);
        }

        $patient = $consultation->patient;
        $profile = $patient->patientProfile;

        $patientContext = $this->buildPatientContext($patient, $profile);
        $allergies = $this->buildAllergyContext($profile);

        $prompt = <<<EOT
You are a medical prescription assistant for South African doctors. Given the medication and patient context, suggest appropriate dosage details.

MEDICATION: {$medicationName} ({$form}, {$strength})
DIAGNOSIS: {$consultation->diagnosis}
TREATMENT PLAN: {$consultation->treatment_plan}
PATIENT: {$patientContext}
ALLERGIES: {$allergies}

Respond in JSON with these exact fields:
{
  "dosage": "e.g. 1 capsule, 2 tablets, 5ml",
  "frequency": "once daily|twice daily|three times daily|at bedtime|as needed|every 4 hours|every 8 hours",
  "route": "oral|topical|sublingual|IM|IV|per rectum",
  "duration_days": 7,
  "quantity": 21,
  "instructions": "e.g. Take with food, avoid alcohol",
  "warning": "any allergy/interaction concern or empty string"
}

Rules:
- frequency MUST be one of: once daily, twice daily, three times daily, at bedtime, as needed, every 4 hours, every 8 hours
- route MUST be one of: oral, topical, sublingual, IM, IV, per rectum
- quantity should equal dosage_units_per_day * duration_days (e.g. 1 tab twice daily for 7 days = 14)
- If medication form is cream/topical, route should be topical
- Flag allergies/interactions in "warning" field
- Use standard South African prescribing guidelines
EOT;

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(15)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a prescription assistant. Only respond with valid JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 300,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->failed()) {
                Log::error('AI Prescription API error', ['status' => $response->status()]);
                return $this->getDefaultDosage($form);
            }

            $content = $response->json('choices.0.message.content');
            $data = json_decode($content, true);

            if (!$data || !isset($data['dosage'])) {
                return $this->getDefaultDosage($form);
            }

            return [
                'dosage' => $data['dosage'] ?? '1 tablet',
                'frequency' => $data['frequency'] ?? 'once daily',
                'route' => $data['route'] ?? 'oral',
                'duration_days' => (int) ($data['duration_days'] ?? 7),
                'quantity' => (int) ($data['quantity'] ?? 7),
                'instructions' => $data['instructions'] ?? '',
                'warning' => $data['warning'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('AI Prescription exception', ['error' => $e->getMessage()]);
            return $this->getDefaultDosage($form);
        }
    }

    /**
     * Generate a full prescription based on consultation notes (all medications at once).
     *
     * @param Consultation $consultation
     * @return array{items: array, pharmacist_notes: string, is_chronic: bool}
     */
    public function generateFullPrescription(Consultation $consultation): array
    {
        if (!$this->isConfigured()) {
            return ['items' => [], 'pharmacist_notes' => '', 'is_chronic' => false, 'error' => 'AI not configured'];
        }

        $patient = $consultation->patient;
        $profile = $patient->patientProfile;

        $patientContext = $this->buildPatientContext($patient, $profile);
        $allergies = $this->buildAllergyContext($profile);
        $chronicMeds = $this->buildChronicMedsContext($profile);

        $prompt = <<<EOT
You are a medical prescription assistant for South African telehealth doctors. Based on the consultation notes below, generate a complete prescription.

PATIENT: {$patientContext}
ALLERGIES: {$allergies}
CURRENT CHRONIC MEDICATIONS: {$chronicMeds}

CONSULTATION NOTES:
- Presenting Complaint: {$consultation->presenting_complaint}
- History: {$consultation->history_of_presenting_illness}
- Examination: {$consultation->examination_findings}
- Diagnosis: {$consultation->diagnosis}
- ICD-10: {$consultation->icd10_code}
- Treatment Plan: {$consultation->treatment_plan}

Respond in JSON with this structure:
{
  "items": [
    {
      "medication_name": "Full medication name with strength",
      "form": "tablet|capsule|cream|syrup|injection|drops|topical",
      "strength": "e.g. 500mg, 10mg/5ml",
      "dosage": "e.g. 1 tablet, 5ml, apply thin layer",
      "frequency": "once daily|twice daily|three times daily|at bedtime|as needed|every 4 hours|every 8 hours",
      "route": "oral|topical|sublingual|IM|IV|per rectum",
      "duration_days": 7,
      "quantity": 21,
      "instructions": "special instructions",
      "substitution_allowed": true
    }
  ],
  "pharmacist_notes": "any notes for pharmacist",
  "is_chronic": false,
  "warnings": ["any allergy/interaction concerns"]
}

Rules:
- Use generic medication names where appropriate (South African market)
- Check against patient's allergies — DO NOT prescribe anything they're allergic to
- Typical consultation generates 1-4 items
- For chronic medications, set is_chronic: true
- frequency MUST be one of the exact values listed
- route MUST be one of the exact values listed
- form MUST be one of the exact values listed
- quantity = units per dose × doses per day × duration_days
- Be conservative with opioids and controlled substances
- Use standard South African prescribing guidelines (SAMF)
EOT;

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a prescription assistant for South African doctors. Only respond with valid JSON. Be conservative and evidence-based.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 1000,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->failed()) {
                Log::error('AI Full Prescription API error', ['status' => $response->status()]);
                return ['items' => [], 'pharmacist_notes' => '', 'is_chronic' => false, 'error' => 'API request failed'];
            }

            $content = $response->json('choices.0.message.content');
            $data = json_decode($content, true);

            if (!$data || !isset($data['items'])) {
                return ['items' => [], 'pharmacist_notes' => '', 'is_chronic' => false, 'error' => 'Invalid AI response'];
            }

            return [
                'items' => $data['items'] ?? [],
                'pharmacist_notes' => $data['pharmacist_notes'] ?? '',
                'is_chronic' => $data['is_chronic'] ?? false,
                'warnings' => $data['warnings'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('AI Full Prescription exception', ['error' => $e->getMessage()]);
            return ['items' => [], 'pharmacist_notes' => '', 'is_chronic' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildPatientContext($patient, $profile): string
    {
        $parts = [];
        if ($patient->date_of_birth) {
            $parts[] = $patient->date_of_birth->age . ' years old';
        }
        if ($patient->gender) {
            $parts[] = ucfirst($patient->gender);
        }
        if ($profile && $profile->weight_kg) {
            $parts[] = $profile->weight_kg . 'kg';
        }
        if ($profile && $profile->height_cm) {
            $parts[] = $profile->height_cm . 'cm';
        }

        return implode(', ', $parts) ?: 'No profile data available';
    }

    private function buildAllergyContext($profile): string
    {
        if (!$profile || !$profile->allergies || $profile->allergies->isEmpty()) {
            return 'None known';
        }

        return $profile->allergies->map(fn ($a) => "{$a->allergen} ({$a->severity})")->implode(', ');
    }

    private function buildChronicMedsContext($profile): string
    {
        if (!$profile || !$profile->chronic_medications) {
            return 'None';
        }

        return $profile->chronic_medications ?: 'None';
    }

    private function getDefaultDosage(string $form): array
    {
        $route = match ($form) {
            'cream', 'topical' => 'topical',
            'injection' => 'IM',
            'drops' => 'topical',
            default => 'oral',
        };

        return [
            'dosage' => match ($form) {
                'tablet', 'capsule' => '1 ' . $form,
                'syrup' => '5ml',
                'cream', 'topical' => 'Apply thin layer',
                'drops' => '2 drops',
                default => '1 dose',
            },
            'frequency' => 'once daily',
            'route' => $route,
            'duration_days' => 7,
            'quantity' => 7,
            'instructions' => '',
            'warning' => '',
        ];
    }
}
