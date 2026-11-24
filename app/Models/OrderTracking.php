<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One courier tracking update for an order. The newest row is the current state. */
class OrderTracking extends Model
{
    protected $table = 'order_tracking';

    protected $fillable = ['order_id', 'courier', 'tracking_number', 'status', 'url', 'pharmacy_reference', 'source', 'tracked_at', 'created_by'];

    protected function casts(): array
    {
        return ['tracked_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
