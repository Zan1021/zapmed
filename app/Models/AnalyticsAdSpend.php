<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A marketing spend line for a channel over a period. Fed into CAC = spend / new patients.
 */
class AnalyticsAdSpend extends Model
{
    protected $table = 'analytics_ad_spend';

    protected $fillable = [
        'period_start', 'period_end', 'channel', 'campaign',
        'amount_cents', 'currency', 'metadata', 'created_by',
    ];

    protected $attributes = [
        'currency' => 'ZAR',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'amount_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
