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
        'unit_price',
        'line_total',
        'instructions',
        'substitution_allowed',
    ];

    protected function casts(): array
    {
        return [
            'substitution_allowed' => 'boolean',
            'unit_price' => 'integer',
            'line_total' => 'integer',
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

    /**
     * Get formatted unit price.
     */
    public function getFormattedUnitPriceAttribute(): string
    {
        return 'R' . number_format($this->unit_price / 100, 2);
    }

    /**
     * Get formatted line total.
     */
    public function getFormattedLineTotalAttribute(): string
    {
        return 'R' . number_format($this->line_total / 100, 2);
    }
}
