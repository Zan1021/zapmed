<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoSession extends Model
{
    protected $fillable = [
        'appointment_id',
        'consultation_id',
        'doctor_id',
        'patient_id',
        'room_name',
        'room_url',
        'doctor_token',
        'patient_token',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'ended_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * Check if the session is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['waiting', 'in_progress']);
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): ?string
    {
        if (!$this->duration_seconds) {
            return null;
        }

        $minutes = intdiv($this->duration_seconds, 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * End the session.
     */
    public function endSession(string $endedBy = 'doctor'): void
    {
        $duration = $this->started_at
            ? now()->diffInSeconds($this->started_at)
            : null;

        $this->update([
            'status' => 'completed',
            'ended_at' => now(),
            'duration_seconds' => $duration,
            'ended_by' => $endedBy,
        ]);
    }
}
