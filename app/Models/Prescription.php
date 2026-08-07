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
        'total_amount',
        'payment_status',
        'payment_reference',
        'paid_at',
        'delivery_address',
        'delivery_city',
        'delivery_province',
        'delivery_postal_code',
        'delivery_phone',
        'delivery_instructions',
        'pharmacy_status',
        'pharmacy_reference',
        'dispatched_at',
        'pharmacy_response',
        'is_chronic',
        'repeats',
        'repeats_used',
        'valid_until',
        'signed_at',
        'signature_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_chronic' => 'boolean',
            'valid_until' => 'date',
            'signed_at' => 'datetime',
            'paid_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'total_amount' => 'integer',
            'pharmacy_response' => 'array',
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
     * Get formatted total amount.
     */
    public function getFormattedTotalAttribute(): string
    {
        return 'R' . number_format($this->total_amount / 100, 2);
    }

    /**
     * Check if medication payment is complete.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if prescription has been sent to pharmacy.
     */
    public function isDispatched(): bool
    {
        return in_array($this->pharmacy_status, ['dispatched', 'confirmed']);
    }

    /**
     * Check if ready for pharmacy dispatch (signed + paid).
     */
    public function isReadyForDispatch(): bool
    {
        return $this->status === 'signed' && $this->isPaid() && $this->pharmacy_status === 'pending';
    }

    /**
     * Mark as paid and trigger pharmacy dispatch.
     */
    public function markPaid(string $paymentReference = null): void
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_reference' => $paymentReference,
            'paid_at' => now(),
        ]);
    }

    /**
     * Record pharmacy dispatch.
     */
    public function markDispatched(string $pharmacyReference = null, array $response = null): void
    {
        $this->update([
            'pharmacy_status' => 'dispatched',
            'pharmacy_reference' => $pharmacyReference,
            'dispatched_at' => now(),
            'pharmacy_response' => $response,
            'status' => 'dispensed',
        ]);
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

    /**
     * Calculate and set total from items.
     */
    public function calculateTotal(): void
    {
        $total = $this->items->sum('line_total');
        $this->update(['total_amount' => $total]);
    }

    /**
     * Get full delivery address as string.
     */
    public function getFullDeliveryAddressAttribute(): string
    {
        return collect([
            $this->delivery_address,
            $this->delivery_city,
            $this->delivery_province,
            $this->delivery_postal_code,
        ])->filter()->implode(', ');
    }
}
