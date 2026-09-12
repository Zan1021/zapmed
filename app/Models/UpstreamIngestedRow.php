<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A raw Contro row landed in staging, one per (entity_set, upstream_id).
 * Reconcile reads from here; nothing writes canonical tables directly from the API.
 * See specs/contro-rebuild/03-contro-import-blueprint.md §3.
 */
class UpstreamIngestedRow extends Model
{
    protected $fillable = [
        'entity_set',
        'upstream_id',
        'payload',
        'upstream_updated_at',
        'sync_run_id',
        'pulled_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'upstream_updated_at' => 'datetime',
            'pulled_at' => 'datetime',
        ];
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(UpstreamSyncRun::class, 'sync_run_id');
    }

    public function scopeForEntity($query, string $entitySet)
    {
        return $query->where('entity_set', $entitySet);
    }
}
