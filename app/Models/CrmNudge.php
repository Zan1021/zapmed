<?php

namespace App\Models;

use App\Enums\NudgeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An AI-/rule-drafted outreach nudge for a lead. Created in draft; ops approves before it is sent.
 * generated_by = 'ai' (LLM) or 'rules' (deterministic fallback).
 */
class CrmNudge extends Model
{
    protected $fillable = [
        'crm_lead_id', 'patient_id', 'kind', 'channel', 'title', 'draft_body',
        'generated_by', 'context', 'status',
        'approved_by', 'approved_at', 'sent_at', 'dismissed_by', 'dismissed_at', 'dismissed_reason',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'kind' => 'reengagement',
        'channel' => 'email',
        'generated_by' => 'rules',
        'status' => 'draft',
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'status' => NudgeStatus::class,
            'context' => 'array',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @param  Builder<self>  $query */
    public function scopeStatus(Builder $query, NudgeStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /** @param  Builder<self>  $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [NudgeStatus::Draft->value, NudgeStatus::Approved->value]);
    }
}
