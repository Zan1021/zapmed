<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Minutes a doctor spent on a patient record and/or a doctor request. A null ended_at means the timer is running. */
class TimeEntry extends Model
{
    protected $fillable = [
        'user_id', 'patient_id', 'doctor_request_id', 'started_at', 'ended_at', 'minutes', 'source', 'capped', 'note',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutes' => 'integer',
            'capped' => 'boolean',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(DoctorRequest::class, 'doctor_request_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(TimeEntryAdjustment::class)->orderBy('id');
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public function scopeRunning($q)
    {
        return $q->whereNull('ended_at');
    }
}
