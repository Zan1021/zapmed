<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A safety escalation to the doctor (serious symptom score or 'something feels wrong'). */
class CoachFlag extends Model
{
    protected $fillable = [
        'patient_id', 'source', 'symptom', 'score', 'status', 'notified_at', 'handled_by', 'handled_at',
    ];

    protected function casts(): array
    {
        return ['score' => 'integer', 'notified_at' => 'datetime', 'handled_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function scopeOpen($q)
    {
        return $q->where('status', 'open');
    }
}