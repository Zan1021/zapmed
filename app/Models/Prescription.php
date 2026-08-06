<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Prescription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference',
        'consultation_id',
        'patient_id',
        'doctor_id',
        'status',
        'diagnosis',
        'notes',
        'is_chronic',
        'repeats',
        'repeats_used',
        'valid_until',
        'signed_at',
        'signature_hash',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'is_chronic' => 'boolean',
            'valid_until' => 'date',
            'signed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Prescription $prescription) {
            if (empty($prescription->reference)) {
                $prescription->reference = static::generateReference();
            }
        });
    }

    /**
     * Generate a unique prescription reference (RX-XXXXXXXXX).
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'RX-' . strtoupper(Str::random(9));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    /**
     * Get formatted fee accessor.
     */
    public function getFormattedFeeAttribute(): string
    {
        return 'R0.00';
    }

    /**
     * Sign the prescription.
     */
    public function sign(): void
    {
        $this->update([
            'status' => 'signed',
            'signed_at' => now(),
        ]);
    }

    /**
     * Cancel the prescription.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }
}
