<?php

namespace App\Models;

use App\Traits\EncryptsSensitiveFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparPatient extends Model
{
    use EncryptsSensitiveFields;

    protected array $encryptedFields = [
        'profile_code',
        'medical_aid_number',
    ];

    protected $fillable = [
        'user_id',
        'spar_pharmacy_id',
        'profile_code',
        'dependent_code',
        'dependent_relation',
        'medical_aid_name',
        'medical_aid_option',
        'consent_status',
        'consent_given_at',
        'consent_revoked_at',
        'consent_channel',
        'is_primary_member',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'consent_given_at' => 'datetime',
            'consent_revoked_at' => 'datetime',
            'is_primary_member' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(SparPharmacy::class, 'spar_pharmacy_id');
    }

    public function journeys(): HasMany
    {
        return $this->hasMany(SparPrescriptionJourney::class, 'spar_patient_id');
    }

    public function dispenseRecords(): HasMany
    {
        return $this->hasMany(SparDispenseRecord::class, 'spar_patient_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SparOrder::class, 'spar_patient_id');
    }

    /**
     * Check if patient has opted in to communications.
     */
    public function hasConsented(): bool
    {
        return $this->consent_status === 'opted_in';
    }

    /**
     * Record opt-in consent.
     */
    public function optIn(string $channel = 'whatsapp'): void
    {
        $this->update([
            'consent_status' => 'opted_in',
            'consent_given_at' => now(),
            'consent_revoked_at' => null,
            'consent_channel' => $channel,
        ]);
    }

    /**
     * Record opt-out.
     */
    public function optOut(): void
    {
        $this->update([
            'consent_status' => 'opted_out',
            'consent_revoked_at' => now(),
        ]);
    }

    /**
     * Get the active journey (if any).
     */
    public function activeJourney(): ?SparPrescriptionJourney
    {
        return $this->journeys()->where('status', 'active')->latest()->first();
    }

    /**
     * Get display name (from linked user or profile code).
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return "Patient #{$this->profile_code}";
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeConsented($query)
    {
        return $query->where('consent_status', 'opted_in');
    }

    public function scopeForPharmacy($query, int $pharmacyId)
    {
        return $query->where('spar_pharmacy_id', $pharmacyId);
    }
}
