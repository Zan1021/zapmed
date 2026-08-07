<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Medication extends Model
{
    protected $fillable = [
        'name',
        'generic_name',
        'brand_name',
        'form',
        'strength',
        'schedule',
        'nappi_code',
        'price',
        'contraindications',
        'side_effects',
        'interactions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price' => 'integer',
        ];
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'R' . number_format($this->price / 100, 2);
    }

    /**
     * Scope to search medications by name or generic name.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('generic_name', 'like', "%{$term}%");
        });
    }

    /**
     * Scope to only active medications.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
