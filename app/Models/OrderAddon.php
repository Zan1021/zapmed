<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One add-on on one order, with the price it was added at. */
class OrderAddon extends Model
{
    protected $fillable = [
        'order_id', 'addon_id', 'price_cents', 'status', 'started_on', 'paused_at', 'cancelled_at', 'reason', 'initiator', 'added_by',
    ];

    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'started_on' => 'date', 'paused_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}
