<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A doctor or health coach Operations assigned to a patient, with when it started and ended. */
class StaffAssignment extends Model
{
    protected $fillable = ['patient_id', 'staff_id', 'role', 'started_at', 'ended_at', 'assigned_by', 'ended_by', 'note'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function scopeActive($q)
    {
        return $q->whereNull('ended_at');
    }
}
