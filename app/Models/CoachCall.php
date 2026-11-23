<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachCall extends Model
{
    protected $fillable = ['patient_id', 'coach_id', 'starts_at', 'length_minutes', 'status'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'length_minutes' => 'integer'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}