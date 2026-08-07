<?php

namespace App\Services;

use App\Models\Prescription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PharmacyService
{
    private string $apiUrl;
    private string $apiKey;
    private bool $enabled;

    public function __construct()
    {
        $this->apiUrl = config('services.pharmacy.api_url', '');
        $this->apiKey = config('services.pharmacy.api_key', '');
        $this->enabled = config('services.pharmacy.enabled', false);
    }

    /**
     * Dispatch a prescription to the pharmacy for fulfillment.
     *
     * @param Prescription $prescription Must be signed and paid
     * @return array{success: bool, reference: ?string, message: string}
     */
    public function dispatch(Prescription $prescription): array
    {
        if (!$prescription->isReadyForDispatch()) {
            return [
                'success' => false,
                'reference' => null,
                'message' => 'Prescription is not ready for dispatch (must be signed and paid).',
            ];
        }

        $payload = $this->buildPayload($prescription);

        // If pharmacy API is not configured, log and simulate success
        if (!$this->isConfigured()) {
            Log::info('Pharmacy dispatch [DEV MODE]', $payload);

            $reference = 'PH-' . strtoupper(\Illuminate\Support\Str::random(8));

            $prescription->markDispatched($reference, [
                'mode' => 'development',
                'dispatched_at' => now()->toISOString(),
            ]);

            return [
                'success' => true,
                'reference' => $reference,
                'message' => 'Prescription dispatched (development mode).',
            ];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->apiUrl}/prescriptions", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $reference = $data['reference'] ?? $data['order_id'] ?? null;

                $prescription->markDispatched($reference, $data);

                Log::info('Pharmacy dispatch successful', [
                    'prescription' => $prescription->reference,
                    'pharmacy_ref' => $reference,
                ]);

                return [
                    'success' => true,
                    'reference' => $reference,
                    'message' => 'Prescription sent to pharmacy successfully.',
                ];
            }

            Log::error('Pharmacy dispatch failed', [
                'prescription' => $prescription->reference,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $prescription->update([
                'pharmacy_status' => 'failed',
                'pharmacy_response' => ['error' => $response->body(), 'status' => $response->status()],
            ]);

            return [
                'success' => false,
                'reference' => null,
                'message' => 'Pharmacy API returned an error: ' . $response->status(),
            ];

        } catch (\Exception $e) {
            Log::error('Pharmacy dispatch exception', [
                'prescription' => $prescription->reference,
                'error' => $e->getMessage(),
            ]);

            $prescription->update([
                'pharmacy_status' => 'failed',
                'pharmacy_response' => ['exception' => $e->getMessage()],
            ]);

            return [
                'success' => false,
                'reference' => null,
                'message' => 'Failed to connect to pharmacy: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build the API payload for the pharmacy.
     */
    private function buildPayload(Prescription $prescription): array
    {
        $prescription->loadMissing(['items.medication', 'patient', 'doctor.doctorProfile']);

        return [
            'prescription_reference' => $prescription->reference,
            'prescribed_at' => $prescription->signed_at?->toISOString(),
            'is_chronic' => $prescription->is_chronic,
            'repeats' => $prescription->repeats,
            'diagnosis' => $prescription->diagnosis,
            'pharmacist_notes' => $prescription->notes,

            // Doctor info
            'doctor' => [
                'name' => 'Dr. ' . $prescription->doctor->first_name . ' ' . $prescription->doctor->last_name,
                'practice_number' => $prescription->doctor->doctorProfile?->practice_number,
                'hpcsa_number' => $prescription->doctor->doctorProfile?->hpcsa_number,
                'phone' => $prescription->doctor->phone,
            ],

            // Patient info
            'patient' => [
                'name' => $prescription->patient->first_name . ' ' . $prescription->patient->last_name,
                'id_number' => $prescription->patient->id_number,
                'phone' => $prescription->patient->phone,
                'email' => $prescription->patient->email,
            ],

            // Delivery address
            'delivery' => [
                'address' => $prescription->delivery_address,
                'city' => $prescription->delivery_city,
                'province' => $prescription->delivery_province,
                'postal_code' => $prescription->delivery_postal_code,
                'phone' => $prescription->delivery_phone,
                'instructions' => $prescription->delivery_instructions,
            ],

            // Medication items
            'items' => $prescription->items->map(fn ($item) => [
                'medication_name' => $item->medication_name,
                'generic_name' => $item->medication?->generic_name,
                'nappi_code' => $item->medication?->nappi_code,
                'form' => $item->form,
                'strength' => $item->strength,
                'dosage' => $item->dosage,
                'frequency' => $item->frequency,
                'route' => $item->route,
                'duration_days' => $item->duration_days,
                'quantity' => $item->quantity,
                'instructions' => $item->instructions,
                'substitution_allowed' => $item->substitution_allowed,
            ])->toArray(),
        ];
    }

    /**
     * Check if the pharmacy API is configured.
     */
    public function isConfigured(): bool
    {
        return $this->enabled && !empty($this->apiUrl) && !empty($this->apiKey);
    }
}
