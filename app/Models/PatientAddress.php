<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A patient address (labelled + geocoded). Receives Contro PatientDto.deliveryAddress (blueprint §2.1).
 * Exactly one primary per label is the intended invariant (enforced in application logic on write).
 */
class PatientAddress extends Model
{
    protected $fillable = [
        'patient_profile_id', 'label',
        'address_line1', 'address_line2', 'suburb', 'city', 'province', 'postal_code', 'country',
        'delivery_notes', 'latitude', 'longitude', 'is_primary',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'label' => 'delivery',
        'country' => 'ZA',
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function patientProfile(): BelongsTo
    {
        return $this->belongsTo(PatientProfile::class);
    }
}
