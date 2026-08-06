<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id',
        'medication_id',
        'medication_name',
        'strength',
        'form',
        'dosage',
        'frequency',
        'route',
        'duration_days',
        'quantity',
        'instructions',
        'substitution_allowed',
    ];

    protected function casts(): array
    {
        return [
            'substitution_allowed' => 'boolean',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }
}
