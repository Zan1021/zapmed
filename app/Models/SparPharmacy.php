<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparPharmacy extends Model
{
    protected $fillable = [
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

    public function journeys(): HasMany
    {
        return $this->hasMany(SparPrescriptionJourney::class, 'spar_pharmacy_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SparOrder::class, 'spar_pharmacy_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class, 'spar_pharmacy_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
