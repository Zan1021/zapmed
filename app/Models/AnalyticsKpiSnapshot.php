<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A nightly KPI snapshot for a date × dimension slice. Composite PK (snapshot_date, dimension,
 * dimension_value); `metrics` is a flexible named-metric bag rather than a column per KPI.
 */
class AnalyticsKpiSnapshot extends Model
{
    protected $table = 'analytics_kpi_snapshots';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'snapshot_date', 'dimension', 'dimension_value', 'metrics', 'computed_at',
    ];

    protected $attributes = [
        'dimension' => 'all',
        'dimension_value' => 'all',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'metrics' => 'array',
            'computed_at' => 'datetime',
        ];
    }
}
