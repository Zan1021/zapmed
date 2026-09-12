<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A refund against a payment (supports multiple partial refunds). Money in minor units. Blueprint §2.6.
 */
class Refund extends Model
{
    protected $fillable = [
        'payment_id', 'amount_minor', 'currency', 'status', 'reason',
        'provider_reference', 'refunded_at',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'currency' => 'ZAR',
        'status' => 'pending',
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'refunded_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
