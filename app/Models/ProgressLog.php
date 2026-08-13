<?php

namespace App\Models;

use App\Traits\EncryptsSensitiveFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressLog extends Model
{
    use EncryptsSensitiveFields;

    protected array $encryptedFields = [
        'symptoms',
        'notes',
    ];

    protected $fillable = [
        'user_id',
        'log_date',
        'weight_kg',
        'waist_cm',
        'energy_level',
        'mood',
        'sleep_hours',
        'sleep_quality',
        'water_glasses',
        'medication_taken',
        'exercised',
        'exercise_type',
        'exercise_minutes',
        'meals_summary',
        'symptoms',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'weight_kg' => 'decimal:1',
            'waist_cm' => 'decimal:1',
            'medication_taken' => 'boolean',
            'exercised' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to date range.
     */
    public function scopeBetween($query, string $from, string $to)
    {
        return $query->whereBetween('log_date', [$from, $to]);
    }

    /**
     * Get the most recent weight entry for a user.
     */
    public static function latestWeight(int $userId): ?float
    {
        return static::where('user_id', $userId)
            ->whereNotNull('weight_kg')
            ->orderByDesc('log_date')
            ->value('weight_kg');
    }

    /**
     * Calculate weight change over a period.
     */
    public static function weightChange(int $userId, int $days = 30): ?float
    {
        $current = static::latestWeight($userId);
        $previous = static::where('user_id', $userId)
            ->whereNotNull('weight_kg')
            ->where('log_date', '<=', now()->subDays($days))
            ->orderByDesc('log_date')
            ->value('weight_kg');

        if ($current === null || $previous === null) {
            return null;
        }

        return round($current - $previous, 1);
    }
}
