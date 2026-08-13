<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientGoal extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'target_value',
        'unit',
        'target_date',
        'start_value',
        'achieved',
        'achieved_at',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'start_value' => 'decimal:2',
            'target_date' => 'date',
            'achieved' => 'boolean',
            'achieved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get progress percentage toward goal.
     */
    public function getProgressPercentAttribute(): ?float
    {
        if (!$this->start_value || !$this->target_value) {
            return null;
        }

        $totalDistance = abs($this->start_value - $this->target_value);
        if ($totalDistance === 0.0) {
            return 100;
        }

        $currentValue = ProgressLog::latestWeight($this->user_id); // simplified — works for weight goals

        if ($currentValue === null) {
            return 0;
        }

        $currentDistance = abs($this->start_value - $currentValue);
        $progress = ($currentDistance / $totalDistance) * 100;

        return min(round($progress, 1), 100);
    }

    /**
     * Check and mark as achieved.
     */
    public function checkAchievement(float $currentValue): void
    {
        if ($this->achieved) {
            return;
        }

        $reached = match (true) {
            $this->target_value < $this->start_value => $currentValue <= $this->target_value, // losing (weight)
            $this->target_value > $this->start_value => $currentValue >= $this->target_value, // gaining (exercise)
            default => false,
        };

        if ($reached) {
            $this->update([
                'achieved' => true,
                'achieved_at' => now(),
            ]);
        }
    }

    /**
     * Scope to active (unachieved) goals.
     */
    public function scopeActive($query)
    {
        return $query->where('achieved', false);
    }
}
