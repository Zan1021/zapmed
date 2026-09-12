<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single attempt against a payment (retries). Blueprint §2.6.
 */
class PaymentAttempt extends Model
{
    protected $fillable = [
        'payment_id', 'attempt_number', 'status', 'provider_reference',
        'failure_reason', 'provider_data', 'attempted_at',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'provider_data' => 'array',
            'attempted_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
