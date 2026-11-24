<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shared work item between doctors, Operations and coaches (Craig's guide 8.1).
 * Status: pending > in_review (an assignee opened it) > in_progress > submitted (response sent) > completed.
 */
class DoctorRequest extends Model
{
    public const PENDING = 'pending';
    public const IN_REVIEW = 'in_review';
    public const IN_PROGRESS = 'in_progress';
    public const SUBMITTED = 'submitted';
    public const COMPLETED = 'completed';

    public const STATUSES = [self::PENDING, self::IN_REVIEW, self::IN_PROGRESS, self::SUBMITTED, self::COMPLETED];

    public const LABELS = [
        self::PENDING => 'Pending',
        self::IN_REVIEW => 'In review',
        self::IN_PROGRESS => 'In progress',
        self::SUBMITTED => 'Submitted',
        self::COMPLETED => 'Completed',
    ];

    protected $fillable = [
        'requester_id', 'title', 'description', 'status', 'response', 'opened_at', 'submitted_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'description' => 'encrypted',
            'response' => 'encrypted',
            'opened_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'doctor_request_users')->withPivot('role')->withTimestamps();
    }

    public function assignees(): BelongsToMany
    {
        return $this->people()->wherePivot('role', 'assignee');
    }

    public function participants(): BelongsToMany
    {
        return $this->people()->wherePivot('role', 'participant');
    }

    public function refs(): HasMany
    {
        return $this->hasMany(DoctorRequestRef::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(DoctorRequestFile::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DoctorRequestEvent::class)->orderBy('id');
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return $this->status !== self::COMPLETED;
    }

    /** Requests this user is part of (requester, assignee or participant). */
    public function scopeInvolving($q, User $user)
    {
        return $q->where(fn ($q) => $q->where('requester_id', $user->id)
            ->orWhereHas('people', fn ($p) => $p->where('users.id', $user->id)));
    }
}
