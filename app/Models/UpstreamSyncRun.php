<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Audit of a single Contro pull run (backfill or delta).
 * See specs/contro-rebuild/03-contro-import-blueprint.md §3.
 */
class UpstreamSyncRun extends Model
{
    protected $fillable = [
        'entity_set',
        'status',
        'trigger',
        'rows_pulled',
        'rows_upserted',
        'rows_quarantined',
        'watermark_from',
        'watermark_to',
        'error',
        'started_at',
        'finished_at',
    ];

    /**
     * Model-level defaults so a freshly-created run reports its state without a DB round-trip
     * (the migration also defaults these, but the in-memory instance should be correct too).
     */
    protected $attributes = [
        'status' => 'running',
        'trigger' => 'manual',
        'rows_pulled' => 0,
        'rows_upserted' => 0,
        'rows_quarantined' => 0,
    ];

    protected function casts(): array
    {
        return [
            'rows_pulled' => 'integer',
            'rows_upserted' => 'integer',
            'rows_quarantined' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function ingestedRows(): HasMany
    {
        return $this->hasMany(UpstreamIngestedRow::class, 'sync_run_id');
    }

    public function markCompleted(): void
    {
        $this->update(['status' => 'completed', 'finished_at' => now()]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error' => $error, 'finished_at' => now()]);
    }
}
