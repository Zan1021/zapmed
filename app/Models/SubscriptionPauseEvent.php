<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subscription pause/resume audit entry (parity with subscriptions_pause_event).
 */
class SubscriptionPauseEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'subscription_id', 'paused_at', 'paused_until', 'resumed_at',
        'paused_reason', 'paused_by', 'resumed_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'paused_at' => 'datetime',
            'paused_until' => 'datetime',
            'resumed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
