<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Incremental pull watermark, one row per Contro entity_set.
 * See specs/contro-rebuild/03-contro-import-blueprint.md §1.
 */
class UpstreamSyncState extends Model
{
    protected $fillable = [
        'entity_set',
        'last_high_watermark',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }
}
