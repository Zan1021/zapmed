<?php

namespace App\Models;

use App\Enums\ConsentPurpose;
use App\Enums\ConsentState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit row for a single consent grant/withdraw. Append-only — written by ComplianceService,
 * never updated. (No CREATED_AT/UPDATED_AT churn: the fixed timestamp is occurred_at.)
 */
class ComplianceConsentRecord extends Model
{
    protected $table = 'compliance_consent_records';

    public $timestamps = false;

    protected $fillable = [
        'consent_id', 'principal_id', 'purpose', 'new_state', 'policy_version',
        'reason', 'ip_address', 'user_agent', 'actor_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => ConsentPurpose::class,
            'new_state' => ConsentState::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(ComplianceConsent::class, 'consent_id');
    }
}
