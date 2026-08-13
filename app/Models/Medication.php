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
        'category',
        'price_cents',
        'repeat_cycle_days',
        'is_subscription',
        'description',
        'dosage_instructions',
        'manufacturer',
        'sort_order',
        'contraindications',
        'side_effects',
        'interactions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_subscription' => 'boolean',
            'price_cents' => 'integer',
            'repeat_cycle_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        if (!$this->price_cents) {
            return 'Price on consultation';
        }
        return 'R' . number_format($this->price_cents / 100, 2);
    }

    /**
     * Get repeat cycle as human-readable text.
     */
    public function getRepeatCycleTextAttribute(): string
    {
        return match ($this->repeat_cycle_days) {
            7 => 'Weekly',
            14 => 'Every 2 weeks',
            28, 30 => 'Monthly',
            60 => 'Every 2 months',
            84, 90 => 'Every 3 months',
            180 => 'Every 6 months',
            365 => 'Yearly',
            default => $this->repeat_cycle_days ? "Every {$this->repeat_cycle_days} days" : 'Once-off',
        };
    }

    /**
     * Scope to search medications by name, generic name, or NAPPI code.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('generic_name', 'like', "%{$term}%")
              ->orWhere('brand_name', 'like', "%{$term}%")
              ->orWhere('nappi_code', 'like', "%{$term}%");
        });
    }

    /**
     * Scope to only active medications.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category.
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope subscription medications only.
     */
    public function scopeSubscription(Builder $query): Builder
    {
        return $query->where('is_subscription', true);
    }
}
