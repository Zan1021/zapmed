<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CRM note — a pinned/unpinned staff note on a lead (parity with crm_note).
 */
class CrmNote extends Model
{
    protected $fillable = [
        'crm_lead_id', 'body', 'pinned', 'created_by',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'pinned' => false,
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
