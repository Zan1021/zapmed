<?php

namespace App\Models;

use App\Enums\TouchpointChannel;
use App\Enums\TouchpointKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coaching touchpoint — a single coach ↔ patient interaction (parity with coaching_touchpoint).
 */
class CoachingTouchpoint extends Model
{
    public const UPDATED_AT = null; // append-only interaction log

    protected $fillable = [
        'patient_id', 'coach_id', 'kind', 'channel', 'direction', 'successful',
        'summary', 'sentiment', 'duration_seconds', 'metadata', 'occurred_at', 'created_by',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'kind' => TouchpointKind::class,
            'channel' => TouchpointChannel::class,
            'successful' => 'boolean',
            'duration_seconds' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
