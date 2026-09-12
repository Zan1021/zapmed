<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $fillable = [
        'reference',
        'patient_id',
        'appointment_id',
        'order_id',
        'provider',
        'provider_reference',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_type',
        'failure_reason',
        'retry_count',
        'is_repeat_charge',
        'medical_aid_claim_status',
        'description',
        'provider_data',
        'paid_at',
        'refunded_at',
        'refund_amount',
        'refund_reason',
        'upstream_id',
        'upstream_source',
        'upstream_synced_at',
    ];

    protected $attributes = [
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'refund_amount' => 'integer',
            'retry_count' => 'integer',
            'is_repeat_charge' => 'boolean',
            'provider_data' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (!$payment->reference) {
                $payment->reference = self::generateReference();
            }
        });
    }

    /**
     * Generate a unique payment reference.
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'PAY-' . strtoupper(Str::random(10));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function attempts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentAttempt::class)->orderBy('attempt_number');
    }

    public function refunds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function webhookEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentWebhookEvent::class);
    }

    /**
     * Get formatted amount in Rands.
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'R' . number_format($this->amount / 100, 2);
    }

    /**
     * Check if payment is complete.
     */
    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
