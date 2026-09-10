<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparPharmacy extends Model
{
    protected $table = 'spar_pharmacies';

    protected $fillable = [
        'group_id',
        'name',
        'spar_store_id',
        'bhf_code',
        'phone',
        'email',
        'address',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'supports_delivery',
        'delivery_fee',
        'operating_hours',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'supports_delivery' => 'boolean',
            'delivery_fee' => 'integer',
            'operating_hours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function patients(): HasMany
    {
        return $this->hasMany(SparPatient::class, 'spar_pharmacy_id');
    }

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SparPharmacyGroup::class, 'group_id');
    }

    public function journeys(): HasMany
    {
        return $this->hasMany(SparPrescriptionJourney::class, 'spar_pharmacy_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SparOrder::class, 'spar_pharmacy_id');
    }

    // staff() (hasMany User) intentionally omitted — AC-3 (no App\Models\User).
    // Staff resolved via the host SparIdentityProvider; integrated host may add
    // the relation on its subclass.

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Restrict to pharmacies the CURRENT actor may see (spec FR-16).
     * Super-admin: all. Group-admin: their group. Pharmacy actor: their store.
     * Resolves scope via the bound SparIdentityProvider (host-agnostic — no host
     * user class referenced here, preserving AC-3/AC-15).
     */
    public function scopeVisibleToCurrentActor($query)
    {
        $identity = app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class);

        if ($identity->isSuperAdmin()) {
            return $query;
        }

        $pharmacyId = $identity->currentPharmacyId();
        if ($pharmacyId !== null) {
            return $query->whereKey($pharmacyId);
        }

        $groupId = $identity->currentGroupId();
        if ($groupId !== null) {
            return $query->where('group_id', $groupId);
        }

        // No resolvable scope → see nothing (fail closed).
        return $query->whereRaw('1 = 0');
    }

    public function getActivePatientsCountAttribute(): int
    {
        return $this->patients()->where('is_active', true)->count();
    }

    public function getActiveJourneysCountAttribute(): int
    {
        return $this->journeys()->where('status', 'active')->count();
    }

    public function getPendingOrdersCountAttribute(): int
    {
        return $this->orders()->whereIn('status', ['requested', 'preparing'])->count();
    }
}
