<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provider (PayFast) webhook event, deduped on (provider, provider_event_id). Blueprint §2.6.
 */
class PaymentWebhookEvent extends Model
{
    protected $fillable = [
        'payment_id', 'provider', 'provider_event_id', 'event_type', 'payload', 'received_at',
    ];

    protected $attributes = [
        'provider' => 'payfast',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
