<?php

namespace App\Models;

use App\Enums\CycleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subscription renewal cycle — one row per renewal attempt (parity with subscriptions_cycle).
 */
class SubscriptionCycle extends Model
{
    protected $fillable = [
        'subscription_id', 'sequence_no', 'status', 'order_id', 'scheduled_for',
        'started_at', 'placed_at', 'failed_at', 'fulfilled_at', 'cancelled_at',
        'failure_reason', 'metadata',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'status' => 'scheduled',
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'status' => CycleStatus::class,
            'sequence_no' => 'integer',
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'placed_at' => 'datetime',
            'failed_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeDue($query)
    {
        return $query->where('status', CycleStatus::Scheduled->value)
            ->where('scheduled_for', '<=', now());
    }
}
