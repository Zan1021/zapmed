<?php

namespace App\Models;

use App\Enums\RiskBand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CRM risk score — current computed retention/conversion risk for a lead (parity with crm_risk_score
 * + the AI risk rubric). Score 0–100, band low/medium/high/critical, contributing factors[]. One
 * current row per lead (recompute overwrites via updateOrCreate).
 */
class CrmRiskScore extends Model
{
    protected $fillable = [
        'crm_lead_id', 'score', 'band', 'factors', 'reasoning', 'computed_by', 'computed_at',
    ];

    protected $attributes = [
        'score' => 0,
        'band' => 'low',
        'computed_by' => 'rules',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'band' => RiskBand::class,
            'factors' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }
}
