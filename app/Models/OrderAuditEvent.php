<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of the trail of what Operations did to an order. Append-only. */
class OrderAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'user_id', 'event', 'summary', 'data', 'created_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
