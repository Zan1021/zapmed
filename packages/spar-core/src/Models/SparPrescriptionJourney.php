<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Zapmed\SparCore\Concerns\ResolvesActionable;
use Zapmed\SparCore\Contracts\SparActionable;

class SparPrescriptionJourney extends Model implements SparActionable
{
    use ResolvesActionable;

    protected $table = 'spar_prescription_journeys';

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

    // zapmedPrescription() (belongsTo App\Models\Prescription) intentionally
    // omitted (AC-3). zapmed_prescription_id remains a plain nullable column;
    // the integrated host subclass may add the relation. The renewal handoff /
    // return path goes through the TelehealthBridge contract.

    public function dispenseRecords(): HasMany
    {
        return $this->hasMany(SparDispenseRecord::class, 'journey_id');
    }

    public function isFinalDispense(): bool
    {
        return $this->dispenses_completed >= $this->total_dispenses;
    }

    public function isRenewalDue(): bool
    {
        return $this->status === 'renewal_due' ||
            ($this->renewal_due_date && $this->renewal_due_date->isPast());
    }

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

    public function markRenewed(string $route): void
    {
        $this->update([
            'status' => 'renewed',
            'renewal_route' => $route,
        ]);
    }

    public function getRemainingDispensesAttribute(): int
    {
        return max(0, $this->total_dispenses - $this->dispenses_completed);
    }

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

    /**
     * Fail-closed actor scoping (mirrors SparOrder/SparPatient). Journeys carry
     * spar_pharmacy_id directly:
     *   super-admin  → all   |  pharmacy → own  |  group-admin → group's pharmacies
     *   otherwise     → nothing (unauthenticated / out of scope)
     */
    public function scopeVisibleToCurrentActor($query)
    {
        $identity = app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class);

        if ($identity->isSuperAdmin()) {
            return $query;
        }

        $pharmacyId = $identity->currentPharmacyId();
        if ($pharmacyId !== null) {
            return $query->where('spar_pharmacy_id', $pharmacyId);
        }

        $groupId = $identity->currentGroupId();
        if ($groupId !== null) {
            $pharmacyIds = SparPharmacy::where('group_id', $groupId)->pluck('id')->all();

            return $query->whereIn('spar_pharmacy_id', $pharmacyIds);
        }

        return $query->whereRaw('1 = 0');
    }
}
