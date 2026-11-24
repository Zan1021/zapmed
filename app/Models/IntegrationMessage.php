<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One attempt to send something to another system. See the migration for what the statuses mean. */
class IntegrationMessage extends Model
{
    public const SUCCESS = 'success';
    public const FAILED = 'failed';
    public const UNKNOWN = 'unknown';

    protected $fillable = [
        'system', 'type', 'prescription_id', 'order_id', 'patient_id', 'status', 'attempt', 'parent_id', 'response_code',
        'request_summary', 'response_excerpt', 'error', 'next_retry_at', 'resent_by',
    ];

    protected function casts(): array
    {
        return ['response_excerpt' => 'encrypted', 'next_retry_at' => 'datetime', 'attempt' => 'integer', 'response_code' => 'integer'];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }
}
