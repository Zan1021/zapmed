<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'cycle_frequency',
        'consultations_per_month',
        'includes_chronic_renewals',
        'includes_priority_booking',
        'includes_messaging',
        'features',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'consultations_per_month' => 'integer',
            'cycle_frequency' => 'integer',
            'includes_chronic_renewals' => 'boolean',
            'includes_priority_booking' => 'boolean',
            'includes_messaging' => 'boolean',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'R' . number_format($this->price / 100, 2);
    }

    /**
     * Get active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Get the billing label.
     */
    public function getBillingLabelAttribute(): string
    {
        if ($this->cycle_frequency === 1) {
            return match ($this->billing_cycle) {
                'monthly' => '/month',
                'annually' => '/year',
                default => '/' . $this->billing_cycle,
            };
        }

        return "every {$this->cycle_frequency} months";
    }
}
