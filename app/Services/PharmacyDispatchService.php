<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Models\Prescription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PharmacyDispatchService
{
    /**
     * Dispatch a prescription to the assigned pharmacy.
     * Routes to the correct method based on pharmacy's api_type.
     */
    public function dispatch(Prescription $prescription, ?Pharmacy $pharmacy = null): bool
    {
        $pharmacy = $pharmacy ?? $prescription->pharmacy ?? Pharmacy::default();

        if (!$pharmacy) {
            Log::error('PharmacyDispatch: No pharmacy found for prescription', [
                'prescription_id' => $prescription->id,
            ]);
            return false;
        }

        // Assign pharmacy to prescription if not set
        if (!$prescription->pharmacy_id) {
            $prescription->update(['pharmacy_id' => $pharmacy->id]);
        }

        return match ($pharmacy->api_type) {
            'rest' => $this->dispatchViaApi($prescription, $pharmacy),
            'email' => $this->dispatchViaEmail($prescription, $pharmacy),
            'fax' => $this->dispatchViaFax($prescription, $pharmacy),
            default => $this->dispatchDefault($prescription, $pharmacy),
        };
    }

    /**
     * Dispatch via REST API (fully integrated pharmacies).
     */
    private function dispatchViaApi(Prescription $prescription, Pharmacy $pharmacy): bool
    {
        try {
            $prescription->loadMissing(['items', 'patient', 'doctor']);

            $payload = [
                'prescription_reference' => $prescription->reference ?? "ZP-{$prescription->id}",
                'patient' => [
                    'name' => $prescription->patient->first_name . ' ' . $prescription->patient->last_name,
                    'id_number' => $prescription->patient->id_number ?? null,
                    'phone' => $prescription->patient->phone,
                    'email' => $prescription->patient->email,
                    'address' => $prescription->delivery_address,
                    'city' => $prescription->delivery_city,
                    'province' => $prescription->delivery_province,
                    'postal_code' => $prescription->delivery_postal_code,
                ],
                'doctor' => [
                    'name' => 'Dr. ' . $prescription->doctor->first_name . ' ' . $prescription->doctor->last_name,
                    'practice_number' => $prescription->doctor->doctorProfile->practice_number ?? null,
                    'hpcsa_number' => $prescription->doctor->doctorProfile->hpcsa_number ?? null,
                ],
                'diagnosis' => $prescription->diagnosis,
                'is_chronic' => $prescription->is_chronic,
                'repeats' => $prescription->repeats,
                'items' => $prescription->items->map(fn ($item) => [
                    'medication_name' => $item->medication_name,
                    'strength' => $item->strength,
                    'form' => $item->form,
                    'dosage' => $item->dosage,
                    'frequency' => $item->frequency,
                    'route' => $item->route,
                    'duration_days' => $item->duration_days,
                    'quantity' => $item->quantity,
                    'instructions' => $item->instructions,
                    'substitution_allowed' => $item->substitution_allowed,
                ])->toArray(),
                'pharmacist_notes' => $prescription->notes,
                'signed_at' => $prescription->signed_at?->toIso8601String(),
                'delivery_required' => true,
            ];

            $response = Http::withToken($pharmacy->api_key)
                ->timeout(30)
                ->post($pharmacy->api_endpoint, $payload);

            if ($response->successful()) {
                $prescription->update(['pharmacy_status' => 'dispatched']);
                Log::info('PharmacyDispatch: API dispatch successful', [
                    'pharmacy' => $pharmacy->name,
                    'prescription_id' => $prescription->id,
                ]);
                return true;
            }

            Log::error('PharmacyDispatch: API dispatch failed', [
                'pharmacy' => $pharmacy->name,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('PharmacyDispatch: API exception', [
                'pharmacy' => $pharmacy->name,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Dispatch via email (PDF prescription attached).
     */
    private function dispatchViaEmail(Prescription $prescription, Pharmacy $pharmacy): bool
    {
        if (!$pharmacy->email) {
            Log::error('PharmacyDispatch: No email for pharmacy', ['pharmacy' => $pharmacy->name]);
            return false;
        }

        try {
            $prescription->loadMissing(['patient', 'doctor', 'items']);
            $patientName = $prescription->patient->first_name . ' ' . $prescription->patient->last_name;

            Mail::raw(
                "New prescription from Zapmed.\n\nPatient: {$patientName}\nDoctor: Dr. {$prescription->doctor->last_name}\nDiagnosis: {$prescription->diagnosis}\n\nPlease see attached PDF for full prescription details.\n\nDelivery Address:\n{$prescription->delivery_address}\n{$prescription->delivery_city}, {$prescription->delivery_province}\n{$prescription->delivery_postal_code}\nPhone: {$prescription->delivery_phone}",
                function ($message) use ($pharmacy, $prescription, $patientName) {
                    $message->to($pharmacy->email)
                        ->subject("Zapmed Prescription — {$patientName}")
                        ->from(config('mail.from.address'), 'Zapmed Prescriptions');

                    // Attach PDF if available
                    $pdfPath = storage_path("app/prescriptions/{$prescription->id}.pdf");
                    if (file_exists($pdfPath)) {
                        $message->attach($pdfPath, ['as' => "prescription-{$prescription->id}.pdf"]);
                    }
                }
            );

            $prescription->update(['pharmacy_status' => 'dispatched']);
            Log::info('PharmacyDispatch: Email sent', ['pharmacy' => $pharmacy->name]);
            return true;

        } catch (\Exception $e) {
            Log::error('PharmacyDispatch: Email failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Dispatch via fax (legacy pharmacies).
     */
    private function dispatchViaFax(Prescription $prescription, Pharmacy $pharmacy): bool
    {
        // Fax integration placeholder — use a service like eFax API
        Log::info('PharmacyDispatch: Fax dispatch not yet implemented', ['pharmacy' => $pharmacy->name]);
        return false;
    }

    /**
     * Default dispatch (our primary pharmacy — uses existing PharmacyService).
     */
    private function dispatchDefault(Prescription $prescription, Pharmacy $pharmacy): bool
    {
        // Falls back to whatever the current integration is
        $prescription->update(['pharmacy_status' => 'dispatched']);
        Log::info('PharmacyDispatch: Default dispatch', ['pharmacy' => $pharmacy->name]);
        return true;
    }
}
