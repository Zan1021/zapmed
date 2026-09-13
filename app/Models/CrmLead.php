<?php

namespace App\Models;

use App\Enums\FunnelStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * CRM lead — one staff-internal lifecycle record per patient (specs/contro-rebuild §2.1, parity with
 * Mark's crm_lead). Distinct from PatientProfile (the patient's own data): this is ops intelligence.
 *
 * Stage transitions go through App\Services\Crm\LeadFunnel::advance() which records an immutable
 * crm_funnel_events row — never write current_stage directly from outside the service.
 */
class CrmLead extends Model
{
    protected $fillable = [
        'patient_id', 'source', 'channel', 'campaign', 'referrer',
        'utm_source', 'utm_medium', 'utm_campaign', 'service_line',
        'current_stage', 'stage_entered_at', 'last_activity_at', 'assigned_to',
        'metadata', 'version',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'current_stage' => 'lead',
        'upstream_source' => 'contro',
        'version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'current_stage' => FunnelStage::class,
            'stage_entered_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'metadata' => 'array',
            'version' => 'integer',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function funnelEvents(): HasMany
    {
        return $this->hasMany(CrmFunnelEvent::class)->orderBy('occurred_at');
    }

    public function notes(): HasMany
    {
        // Pinned first, then newest.
        return $this->hasMany(CrmNote::class)->orderByDesc('pinned')->latest();
    }

    public function flags(): HasMany
    {
        return $this->hasMany(CrmFlag::class);
    }

    public function activeFlags(): HasMany
    {
        return $this->flags()->whereNull('cleared_at');
    }

    public function riskScore(): HasOne
    {
        return $this->hasOne(CrmRiskScore::class);
    }

    /** Touch the activity watermark (parity with their trg_lead_touch). */
    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }
}
