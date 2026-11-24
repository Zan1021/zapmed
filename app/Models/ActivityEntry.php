<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityEntry extends Model
{
    protected $fillable = [
        'user_id', 'logged_at', 'kind', 'workout_key', 'name', 'minutes', 'steps', 'kcal', 'met', 'weight_kg',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'minutes' => 'integer',
            'steps' => 'integer',
            'kcal' => 'integer',
            'met' => 'float',
            'weight_kg' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
