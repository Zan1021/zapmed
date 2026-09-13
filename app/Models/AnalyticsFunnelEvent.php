<?php

namespace App\Models;

use App\Enums\FunnelEventKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single funnel signal. Append-only — never updated. Anonymous events (pre-signup) carry a
 * visitor_id and a null principal_id.
 */
class AnalyticsFunnelEvent extends Model
{
    protected $table = 'analytics_funnel_events';

    protected $fillable = [
        'principal_id', 'visitor_id', 'kind', 'custom_label', 'service_line',
        'properties', 'occurred_at',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'kind' => FunnelEventKind::class,
            'properties' => 'array',
            'occurred_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeOfKind(Builder $query, FunnelEventKind $kind): Builder
    {
        return $query->where('kind', $kind->value);
    }

    /** @param  Builder<self>  $query */
    public function scopeBetween(Builder $query, \DateTimeInterface $since, \DateTimeInterface $until): Builder
    {
        return $query->whereBetween('occurred_at', [$since, $until]);
    }
}
