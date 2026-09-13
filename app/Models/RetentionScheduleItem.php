<?php

namespace App\Models;

use App\Enums\RetentionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scheduled retention action against one source record (data_class × aggregate_id). legal_hold
 * blocks execution until released.
 */
class RetentionScheduleItem extends Model
{
    protected $table = 'compliance_retention_schedule';

    protected $fillable = [
        'policy_id', 'data_class', 'aggregate_id', 'principal_id', 'due_at', 'status',
        'legal_hold', 'legal_hold_reason', 'completed_at', 'failure_reason', 'metadata',
    ];

    protected $attributes = [
        'status' => 'scheduled',
        'legal_hold' => false,
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'status' => RetentionStatus::class,
            'legal_hold' => 'boolean',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicy::class, 'policy_id');
    }

    /** Due now, still scheduled, and NOT under legal hold — the runner's work list. */
    public function scopeRunnable(Builder $query): Builder
    {
        return $query->where('status', RetentionStatus::Scheduled->value)
            ->where('legal_hold', false)
            ->where('due_at', '<=', now());
    }
}
