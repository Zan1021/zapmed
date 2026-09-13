<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coaching assignment — links a patient to a health coach (parity with coaching_assignment).
 * At most one ACTIVE (ended_at IS NULL) assignment per patient, enforced by a partial-unique index.
 */
class CoachingAssignment extends Model
{
    protected $fillable = [
        'patient_id', 'coach_id', 'reason', 'started_at', 'ended_at', 'ended_reason', 'created_by',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
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

    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
