<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A coupon on one order's monthly service fee. Never touches the medication fee. */
class OrderDiscount extends Model
{
    protected $fillable = [
        'order_id', 'type', 'value', 'months', 'starts_on', 'ends_on', 'is_active', 'reason',
        'created_by', 'disabled_by', 'disabled_at', 'disabled_reason',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'months' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
