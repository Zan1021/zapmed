<?php

namespace App\Models;

use App\Enums\CrmFlagKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CRM flag — a lightweight tag on a lead with optional reason (parity with crm_flag). At most one
 * ACTIVE (cleared_at IS NULL) flag of a given kind per lead, enforced by a unique index.
 */
class CrmFlag extends Model
{
    protected $fillable = [
        'crm_lead_id', 'kind', 'reason', 'cleared_at', 'cleared_by', 'created_by',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CrmFlagKind::class,
            'cleared_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function isActive(): bool
    {
        return $this->cleared_at === null;
    }
}
