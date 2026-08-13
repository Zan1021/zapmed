<?php

namespace App\Models;

use App\Traits\EncryptsSensitiveFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientProfile extends Model
{
    use HasFactory, EncryptsSensitiveFields;

    protected array $encryptedFields = [
        'medical_aid_number',
        'surgical_history',
        'family_history',
    ];

    protected $fillable = [
        'user_id',
        'medical_aid_name',
        'medical_aid_number',
        'medical_aid_plan',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'blood_type',
        'height_cm',
        'weight_kg',
        'is_smoker',
        'consumes_alcohol',
        'surgical_history',
        'family_history',
        'onboarding_complete',
        'consent_given',
        'consent_given_at',
    ];

    protected function casts(): array
    {
        return [
            'is_smoker' => 'boolean',
            'consumes_alcohol' => 'boolean',
            'onboarding_complete' => 'boolean',
            'consent_given' => 'boolean',
            'consent_given_at' => 'datetime',
            'height_cm' => 'decimal:1',
            'weight_kg' => 'decimal:1',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function chronicConditions(): HasMany
    {
        return $this->hasMany(PatientChronicCondition::class);
    }

    /**
     * Calculate BMI from height and weight.
     */
    public function getBmiAttribute(): ?float
    {
        if (!$this->height_cm || !$this->weight_kg) {
            return null;
        }

        $heightM = $this->height_cm / 100;
        return round($this->weight_kg / ($heightM * $heightM), 1);
    }
}
