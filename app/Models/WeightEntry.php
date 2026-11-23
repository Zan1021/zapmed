<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightEntry extends Model
{
    protected $fillable = ['user_id', 'logged_on', 'weight_kg', 'cycle_phase'];

    protected function casts(): array
    {
        return ['logged_on' => 'date', 'weight_kg' => 'decimal:1'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}