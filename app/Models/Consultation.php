<?php

namespace App\Models;

use App\Traits\EncryptsSensitiveFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consultation extends Model
{
    use EncryptsSensitiveFields;

    protected array $encryptedFields = [
        'presenting_complaint',
        'history_of_presenting_illness',
        'examination_findings',
        'diagnosis',
        'treatment_plan',
        'doctor_notes',
        'follow_up_notes',
    ];
    protected $fillable = [
        'appointment_id',
        'patient_id',
        'doctor_id',
        'status',
        'presenting_complaint',
        'history_of_presenting_illness',
        'examination_findings',
        'diagnosis',
        'icd10_code',
        'treatment_plan',
        'doctor_notes',
        'follow_up_required',
        'follow_up_date',
        'follow_up_notes',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * Get the testimonial for this consultation.
     */
    public function testimonial(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Testimonial::class);
    }

    /**
     * Check if consultation is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if consultation is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get duration in minutes.
     */
    public function getDurationMinutesAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->completed_at);
    }
}
