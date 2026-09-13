<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subscription follow-up — a Completed order's +180d review reminder (parity with
 * subscriptions_followup). One active follow-up per origin order.
 */
class SubscriptionFollowup extends Model
{
    protected $fillable = [
        'origin_order_id', 'patient_id', 'due_at', 'notified_at',
        'consultation_id', 'booked_at', 'cancelled_at', 'cancelled_reason',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'notified_at' => 'datetime',
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function originOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'origin_order_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /** Due, not yet notified, not cancelled. */
    public function scopeDue($query)
    {
        return $query->whereNull('notified_at')
            ->whereNull('cancelled_at')
            ->where('due_at', '<=', now());
    }
}
