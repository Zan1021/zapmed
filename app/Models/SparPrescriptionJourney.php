<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparPrescriptionJourney extends Model
{
    protected $fillable = [
        'spar_patient_id',
        'spar_pharmacy_id',
        'script_number',
        'status',
        'total_dispenses',
        'dispenses_completed',
        'start_date',
        'next_dispense_date',
        'renewal_due_date',
        'renewal_route',
        'zapmed_prescription_id',
        'doctor_name',
        'doctor_bhf',
        'medications',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'next_dispense_date' => 'date',
            'renewal_due_date' => 'date',
            'medications' => 'array',
            'metadata' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(SparPatient::class, 'spar_patient_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(SparPharmacy::class, 'spar_pharmacy_id');
    }

    public function zapmedPrescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class, 'zapmed_prescription_id');
    }

    public function dispenseRecords(): HasMany
    {
        return $this->hasMany(SparDispenseRecord::class, 'journey_id');
    }

    /**
     * Check if this is the final dispense in the cycle.
     */
    public function isFinalDispense(): bool
    {
        return $this->dispenses_completed >= $this->total_dispenses;
    }

    /**
     * Check if renewal is due.
     */
    public function isRenewalDue(): bool
    {
        return $this->status === 'renewal_due' ||
            ($this->renewal_due_date && $this->renewal_due_date->isPast());
    }

    /**
     * Record a dispense event.
     */
    public function recordDispense(): void
    {
        $this->increment('dispenses_completed');

        if ($this->isFinalDispense()) {
            $this->update([
                'status' => 'renewal_due',
                'next_dispense_date' => null,
            ]);
        } else {
            $this->update([
                'next_dispense_date' => now()->addMonth(),
            ]);
        }
    }

    /**
     * Mark as renewed and start new journey.
     */
    public function markRenewed(string $route): void
    {
        $this->update([
            'status' => 'renewed',
            'renewal_route' => $route,
        ]);
    }

    /**
     * Get remaining dispenses count.
     */
    public function getRemainingDispensesAttribute(): int
    {
        return max(0, $this->total_dispenses - $this->dispenses_completed);
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentAttribute(): int
    {
        if ($this->total_dispenses === 0) return 0;
        return (int) round(($this->dispenses_completed / $this->total_dispenses) * 100);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRenewalDue($query)
    {
        return $query->where('status', 'renewal_due');
    }

    public function scopeForPharmacy($query, int $pharmacyId)
    {
        return $query->where('spar_pharmacy_id', $pharmacyId);
    }
}
