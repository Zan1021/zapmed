<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Zapmed\SparCore\Models\SparPrescriptionJourney as BaseSparPrescriptionJourney;

/**
 * Integrated (ZapMed) SparPrescriptionJourney — adds the ZapMed-only
 * `zapmedPrescription` relation (the telehealth link).
 */
class SparPrescriptionJourney extends BaseSparPrescriptionJourney
{
    public function zapmedPrescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class, 'zapmed_prescription_id');
    }
}
