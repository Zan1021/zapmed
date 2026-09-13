<?php

namespace App\Models;

use App\Enums\ConsentPurpose;
use App\Enums\ConsentState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Current consent state for a (principal × purpose). POPIA per-purpose consent with policy versioning.
 */
class ComplianceConsent extends Model
{
    protected $table = 'compliance_consents';

    protected $fillable = [
        'principal_id', 'purpose', 'state', 'policy_version',
        'granted_at', 'withdrawn_at', 'expires_at', 'evidence_ref', 'metadata',
    ];

    protected $attributes = [
        'state' => 'pending_reconsent',
        'policy_version' => 'v1.0',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => ConsentPurpose::class,
            'state' => ConsentState::class,
            'granted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ComplianceConsentRecord::class, 'consent_id')->latest('occurred_at');
    }
}
