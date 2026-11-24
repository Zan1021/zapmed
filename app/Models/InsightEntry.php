<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsightEntry extends Model
{
    protected $fillable = ['user_id', 'entry_date', 'answer', 'helpful', 'coach_told_at'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'helpful' => 'boolean', 'coach_told_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
