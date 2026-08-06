<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'patient_id',
        'doctor_id',
        'type',
        'status',
        'appointment_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'reason',
        'notes',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'fee_amount',
        'is_paid',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'cancelled_at' => 'datetime',
            'fee_amount' => 'integer',
            'is_paid' => 'boolean',
            'duration_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment) {
            if (!$appointment->reference) {
                $appointment->reference = self::generateReference();
            }
        });
    }

    /**
     * Generate a unique appointment reference.
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'ZAP-' . strtoupper(Str::random(6));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }

    /**
     * Get fee formatted in Rands.
     */
    public function getFormattedFeeAttribute(): string
    {
        return 'R' . number_format($this->fee_amount / 100, 2);
    }

    /**
     * Get human-readable type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'general' => 'General Consultation',
            'follow_up' => 'Follow-up',
            'chronic_renewal' => 'Chronic Med Renewal',
            'new_patient' => 'New Patient Consultation',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'amber',
            'confirmed' => 'blue',
            'in_progress' => 'purple',
            'completed' => 'green',
            'cancelled' => 'gray',
            'no_show' => 'red',
            default => 'gray',
        };
    }

    /**
     * Check if appointment can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    /**
     * Cancel the appointment.
     */
    public function cancel(int $userId, string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Scope to upcoming appointments.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', today())
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('appointment_date')
            ->orderBy('start_time');
    }

    /**
     * Scope to today's appointments.
     */
    public function scopeToday($query)
    {
        return $query->where('appointment_date', today());
    }
}
