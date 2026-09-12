<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A staged Contro record that could not be safely reconciled (blueprint §3 rule 5).
 * Flagged for manual review — never silently dropped.
 */
class ImportQuarantine extends Model
{
    protected $table = 'import_quarantine';

    protected $fillable = [
        'entity_set', 'upstream_id', 'reason', 'detail', 'payload', 'status', 'sync_run_id', 'resolved_at',
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Record (or update) a quarantine entry. Idempotent per (entity_set, upstream_id, reason) so a
     * repeated reconcile of the same bad row doesn't pile up duplicates.
     */
    public static function flag(
        string $entitySet,
        ?string $upstreamId,
        string $reason,
        ?string $detail = null,
        ?array $payload = null,
        ?int $syncRunId = null,
    ): self {
        return static::updateOrCreate(
            ['entity_set' => $entitySet, 'upstream_id' => $upstreamId, 'reason' => $reason],
            ['detail' => $detail, 'payload' => $payload, 'sync_run_id' => $syncRunId, 'status' => 'open'],
        );
    }
}
