<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'payfast_token',
        'payment_reference',
        'starts_at',
        'current_period_start',
        'current_period_end',
        'next_billing_date',
        'cancelled_at',
        'ends_at',
        'consultations_used_this_period',
        'total_paid',
        'payment_count',
        'last_payment_at',
        'cancellation_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'next_billing_date' => 'datetime',
            'cancelled_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_payment_at' => 'datetime',
            'total_paid' => 'integer',
            'payment_count' => 'integer',
            'consultations_used_this_period' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if subscription is on a grace period (cancelled but not expired).
     */
    public function onGracePeriod(): bool
    {
        return $this->status === 'cancelled'
            && $this->ends_at
            && $this->ends_at->isFuture();
    }

    /**
     * Check if user can still use subscription features.
     */
    public function isUsable(): bool
    {
        return $this->isActive() || $this->onGracePeriod();
    }

    /**
     * Check if user has remaining consultations this period.
     */
    public function hasRemainingConsultations(): bool
    {
        $limit = $this->plan->consultations_per_month;

        // 0 means unlimited
        if ($limit === 0) {
            return true;
        }

        return $this->consultations_used_this_period < $limit;
    }

    /**
     * Record a consultation usage.
     */
    public function recordConsultation(): void
    {
        $this->increment('consultations_used_this_period');
    }

    /**
     * Record a payment.
     */
    public function recordPayment(int $amount): void
    {
        $this->update([
            'total_paid' => $this->total_paid + $amount,
            'payment_count' => $this->payment_count + 1,
            'last_payment_at' => now(),
        ]);
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => $this->current_period_end, // Access until end of paid period
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Activate the subscription.
     */
    public function activate(): void
    {
        $periodEnd = now()->addMonths($this->plan->cycle_frequency);

        $this->update([
            'status' => 'active',
            'starts_at' => $this->starts_at ?? now(),
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
            'next_billing_date' => $periodEnd,
        ]);
    }

    /**
     * Renew the period (called on successful recurring payment).
     */
    public function renewPeriod(): void
    {
        $newPeriodEnd = now()->addMonths($this->plan->cycle_frequency);

        $this->update([
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => $newPeriodEnd,
            'next_billing_date' => $newPeriodEnd,
            'consultations_used_this_period' => 0, // Reset usage
        ]);
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'green',
            'cancelled' => 'amber',
            'paused' => 'blue',
            'payment_failed' => 'red',
            'expired' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Scope for active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
