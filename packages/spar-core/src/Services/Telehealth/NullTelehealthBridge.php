<?php

namespace Zapmed\SparCore\Services\Telehealth;

use Zapmed\SparCore\Contracts\TelehealthBridge;
use Zapmed\SparCore\Models\SparPrescriptionJourney;

/**
 * Standalone bridge (spec FR-3.3): renewal offers ONLY "see your own doctor".
 * No online consult, no handoff, no return path. Guarantees a standalone SPAR
 * deploy has zero dependency on telehealth.
 */
class NullTelehealthBridge implements TelehealthBridge
{
    public function offersOnlineConsult(): bool
    {
        return false;
    }

    public function renewalOptions(SparPrescriptionJourney $journey): array
    {
        return [
            ['key' => 'own_doctor', 'label' => 'Renew with your own doctor'],
        ];
    }

    public function handoffContext(SparPrescriptionJourney $journey): ?array
    {
        return null;
    }

    public function returnPrescription(
        SparPrescriptionJourney $previous,
        array $medications,
        array $meta = []
    ): ?SparPrescriptionJourney {
        return null;
    }
}
