<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachCall extends Model
{
    public const BOOKED = 'booked';
    public const CANCELLED = 'cancelled';
    public const COMPLETED = 'completed';

    protected $fillable = ['patient_id', 'coach_id', 'starts_at', 'length_minutes', 'status', 'slot_key', 'purpose', 'reminded_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'length_minutes' => 'integer', 'reminded_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function scopeBooked($q)
    {
        return $q->where('status', self::BOOKED);
    }
}