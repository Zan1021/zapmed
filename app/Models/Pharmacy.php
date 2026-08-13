<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pharmacy extends Model
{
    protected $fillable = [
        'name',
        'group',
        'license_number',
        'phone',
        'email',
        'address',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'api_type',
        'api_endpoint',
        'api_key',
        'supports_delivery',
        'delivery_fee',
        'delivery_area',
        'is_default',
        'is_active',
        'operating_hours',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'supports_delivery' => 'boolean',
            'delivery_fee' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'operating_hours' => 'array',
            'api_key' => 'encrypted',
        ];
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    /**
     * Scope to active pharmacies only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the default (primary) pharmacy.
     */
    public static function default(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Scope to pharmacies in a specific province.
     */
    public function scopeInProvince($query, string $province)
    {
        return $query->where('province', $province);
    }

    /**
     * Scope to pharmacies in a specific city.
     */
    public function scopeInCity($query, string $city)
    {
        return $query->where('city', 'like', "%{$city}%");
    }

    /**
     * Scope to pharmacies that support delivery.
     */
    public function scopeWithDelivery($query)
    {
        return $query->where('supports_delivery', true);
    }

    /**
     * Get pharmacies sorted by distance from a given coordinate.
     */
    public function scopeNearby($query, float $lat, float $lng, int $radiusKm = 50)
    {
        // Haversine formula for SQLite/MySQL compatibility
        return $query->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw("*, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) as distance_km", [$lat, $lng, $lat])
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km');
    }

    /**
     * Check if this pharmacy has API integration.
     */
    public function hasApiIntegration(): bool
    {
        return $this->api_type !== 'none' && !empty($this->api_endpoint);
    }

    /**
     * Get formatted delivery fee.
     */
    public function getFormattedDeliveryFeeAttribute(): string
    {
        if ($this->delivery_fee === 0) {
            return 'Free';
        }
        return 'R' . number_format($this->delivery_fee / 100, 2);
    }

    /**
     * Get display label (name + group).
     */
    public function getDisplayLabelAttribute(): string
    {
        return $this->group ? "{$this->name} ({$this->group})" : $this->name;
    }
}
