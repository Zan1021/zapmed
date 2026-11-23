<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Per-patient settings for the Coach App (goal, units, diet, cycle switch). */
class CoachProfile extends Model
{
    protected $fillable = [
        'user_id', 'start_weight_kg', 'goal_weight_kg', 'height_cm', 'target_months', 'units', 'diet',
        'allergies', 'avoids', 'activity_level', 'health_concerns', 'worry', 'track_cycle',
        'cycle_length', 'period_length', 'last_period_start', 'onboarded_at',
    ];

    protected function casts(): array
    {
        return [
            'start_weight_kg' => 'decimal:1',
            'goal_weight_kg' => 'decimal:1',
            'allergies' => 'array',
            'avoids' => 'array',
            'health_concerns' => 'array',
            'track_cycle' => 'boolean',
            'last_period_start' => 'date',
            'onboarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}