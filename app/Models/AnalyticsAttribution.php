<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * First/last-touch marketing attribution for a patient. One row per principal (PK = principal_id).
 * first_* is captured once and never overwritten; last_* updates on each new touch.
 */
class AnalyticsAttribution extends Model
{
    protected $table = 'analytics_attribution';

    protected $primaryKey = 'principal_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public const CREATED_AT = 'captured_at';

    protected $fillable = [
        'principal_id', 'visitor_id',
        'first_source', 'first_medium', 'first_campaign', 'first_referrer',
        'last_source', 'last_medium', 'last_campaign', 'landing_page',
        'captured_at', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_id');
    }
}
