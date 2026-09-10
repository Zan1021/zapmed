<?php

namespace App\Services\Spar\Telehealth;

use Zapmed\SparCore\Contracts\AuditLogger;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Contracts\TelehealthBridge;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Illuminate\Support\Facades\URL;

/**
 * Integrated (ZapMed) telehealth bridge (spec FR-3.2, FR-13).
 *
 * Offers the online-consult renewal option, builds the deep-link handoff into
 * ZapMed (identity + medications + pharmacy + signed token), and closes the
 * loop by writing a new SPAR journey when a renewal script is issued.
 */
class ZapmedTelehealthBridge implements TelehealthBridge
{
    public function __construct(
        private SparIdentityProvider $identity,
        private AuditLogger $audit,
    ) {
    }

    public function offersOnlineConsult(): bool
    {
        return true;
    }

    public function renewalOptions(SparPrescriptionJourney $journey): array
    {
        $options = [
            ['key' => 'own_doctor', 'label' => 'Renew with your own doctor'],
        ];

        $handoff = $this->handoffContext($journey);
        $options[] = [
            'key' => 'zapmed_online',
            'label' => 'Consult a ZapMed doctor online',
            'url' => $handoff['url'] ?? config('app.url'),
        ];

        return $options;
    }

    public function handoffContext(SparPrescriptionJourney $journey): ?array
    {
        $patient = $journey->patient;
        $identity = $patient ? $this->identity->forPatient($patient) : null;

        // A signed token ZapMed can trust to pre-fill the renewal consultation.
        $url = URL::temporarySignedRoute(
            'spar.renewal-handoff',
            now()->addHours(24),
            ['journey' => $journey->id]
        );

        return [
            'url' => $url,
            'journey_id' => $journey->id,
            'patient' => $identity ? [
                'first_name' => $identity->firstName,
                'last_name' => $identity->lastName,
                'phone' => $identity->phone,
                'email' => $identity->email,
                'user_id' => $identity->userId,
            ] : null,
            'medications' => $journey->medications ?? [],
            'pharmacy' => $journey->pharmacy?->name,
            'reason' => 'chronic_medication_renewal',
        ];
    }

    public function returnPrescription(
        SparPrescriptionJourney $previous,
        array $medications,
        array $meta = []
    ): ?SparPrescriptionJourney {
        // Close the loop: mark the old journey renewed and start a fresh cycle.
        $previous->markRenewed('zapmed_online');

        $repeats = (int) ($meta['repeats'] ?? $previous->total_dispenses ?: 6);
        $start = now();

        $new = SparPrescriptionJourney::create([
            'spar_patient_id' => $previous->spar_patient_id,
            'spar_pharmacy_id' => $previous->spar_pharmacy_id,
            'script_number' => $meta['script_number'] ?? ('RENEW-' . $previous->id . '-' . $start->format('ymd')),
            'status' => 'active',
            'total_dispenses' => $repeats,
            'dispenses_completed' => 0,
            'start_date' => $start,
            'next_dispense_date' => $start->copy()->addMonth(),
            'renewal_due_date' => $start->copy()->addMonths($repeats),
            'zapmed_prescription_id' => $meta['zapmed_prescription_id'] ?? null,
            'doctor_name' => $meta['doctor_name'] ?? 'ZapMed doctor',
            'medications' => $medications,
            'metadata' => ['renewed_from' => $previous->id, 'source' => 'zapmed_telehealth'],
        ]);

        $this->audit->log('renewal_returned', 'ZapMed renewal script returned to SPAR', [
            'previous_journey_id' => $previous->id,
            'new_journey_id' => $new->id,
            'spar_patient_id' => $previous->spar_patient_id,
        ]);

        return $new;
    }
}
