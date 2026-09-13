<?php

namespace App\Models;

use App\Enums\FunnelStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Immutable funnel stage-transition event (parity with crm_funnel_event). Append-only: once written a
 * row is never mutated or deleted (except via the parent lead's cascade).
 */
class CrmFunnelEvent extends Model
{
    public const UPDATED_AT = null; // append-only

    protected $fillable = [
        'crm_lead_id', 'from_stage', 'to_stage', 'aggregate_type', 'aggregate_id',
        'notes', 'actor', 'occurred_at',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'from_stage' => FunnelStage::class,
            'to_stage' => FunnelStage::class,
            'occurred_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('crm_funnel_events is immutable and cannot be updated.');
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }
}
