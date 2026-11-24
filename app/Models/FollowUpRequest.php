<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A doctor's request for a follow-up appointment. Open (requested or booked) = the order's next repeat is blocked. */
class FollowUpRequest extends Model
{
    public const REQUESTED = 'requested';
    public const BOOKED = 'booked';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    public const OPEN = [self::REQUESTED, self::BOOKED];

    protected $fillable = [
        'order_id', 'patient_id', 'requested_by', 'consultation_id', 'appointment_id', 'days', 'due_at', 'status',
        'is_free', 'note', 'cancel_reason', 'order_status_before', 'reminded_at', 'booked_at', 'completed_at',
        'cancelled_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'integer',
            'due_at' => 'datetime',
            'is_free' => 'boolean',
            'note' => 'encrypted',
            'reminded_at' => 'datetime',
            'booked_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', self::OPEN);
    }
}
