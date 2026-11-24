<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The six-month period a doctor authorised an order's repeats for. */
class OrderAuthorisation extends Model
{
    public const ACTIVE = 'active';
    public const RENEWED = 'renewed';
    public const EXPIRED = 'expired';

    protected $fillable = [
        'order_id', 'patient_id', 'doctor_id', 'previous_id', 'follow_up_request_id', 'starts_on', 'expires_on', 'status', 'note', 'renewed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'expires_on' => 'date',
            'note' => 'encrypted',
            'renewed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUpRequest::class, 'follow_up_request_id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', self::ACTIVE);
    }
}
