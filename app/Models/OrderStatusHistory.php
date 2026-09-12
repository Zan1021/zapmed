<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Immutable order status audit row (from/to/trigger/actor/notes/payload).
 *
 * Append-only: once created a history row must never be mutated or deleted (except via the parent
 * order's cascade). We enforce immutability at the model layer by blocking updates.
 */
class OrderStatusHistory extends Model
{
    public const UPDATED_AT = null; // no updated_at column — append-only

    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id', 'from_status', 'to_status', 'trigger_type', 'triggered_by',
        'notes', 'payload', 'occurred_at',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Block any UPDATE to an existing history row — the audit trail is immutable.
        static::updating(function () {
            throw new RuntimeException('order_status_history is immutable and cannot be updated.');
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
