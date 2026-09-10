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

    /**
     * Clinical fields that are INTERNAL — staff/doctor-only, never exposed to the patient.
     * (Maps to the build-spec's clinical_note.is_internal concept.)
     */
    public const INTERNAL_FIELDS = [
        'history_of_presenting_illness',
        'examination_findings',
        'doctor_notes',
        'follow_up_notes',
    ];

    /**
     * Clinical fields that ARE safe to show the patient.
     */
    public const PATIENT_VISIBLE_FIELDS = [
        'presenting_complaint',
        'diagnosis',
        'icd10_code',
        'treatment_plan',
        'follow_up_required',
        'follow_up_date',
        'status',
    ];

    /**
     * Return only the patient-visible clinical data for this consultation.
     * Patient-facing endpoints MUST use this instead of exposing the model
     * directly, so internal notes can never leak by accident. Never trust a
     * client-side filter for this — enforce it here at the source.
     */
    public function patientVisibleData(): array
    {
        return collect(self::PATIENT_VISIBLE_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => $this->{$field}])
            ->all();
    }

    /**
     * True if the given attribute name is an internal (staff-only) clinical field.
     */
    public static function isInternalField(string $field): bool
    {
        return in_array($field, self::INTERNAL_FIELDS, true);
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
