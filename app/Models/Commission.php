<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $fillable = [
        'partner_id',
        'referral_id',
        'patient_id',
        'type',
        'reference',
        'sale_amount',
        'commission_rate',
        'commission_amount',
        'status',
        'approved_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_amount' => 'integer',
            'commission_rate' => 'integer',
            'commission_amount' => 'integer',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * Get formatted commission amount in Rands.
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'R' . number_format($this->commission_amount / 100, 2);
    }
}
