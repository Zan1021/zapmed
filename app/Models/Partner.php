<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'website_url',
        'contact_name',
        'contact_email',
        'contact_phone',
        'commission_consultation',
        'commission_medication',
        'cookie_days',
        'payout_method',
        'bank_name',
        'bank_account',
        'bank_branch_code',
        'status',
        'allowed_treatments',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'commission_consultation' => 'integer',
            'commission_medication' => 'integer',
            'cookie_days' => 'integer',
            'allowed_treatments' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get total earnings (all time, approved + paid).
     */
    public function getTotalEarningsAttribute(): int
    {
        return $this->commissions()->whereIn('status', ['approved', 'paid'])->sum('commission_amount');
    }

    /**
     * Get pending earnings.
     */
    public function getPendingEarningsAttribute(): int
    {
        return $this->commissions()->where('status', 'pending')->sum('commission_amount');
    }
}
