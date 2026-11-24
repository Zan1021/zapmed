<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A note about a patient with an author, an audience, a time, an optional order and an action status. Append-only. */
class PatientNote extends Model
{
    public const CLINICAL = 'clinical';
    public const PATIENT_SUMMARY = 'patient_summary';
    public const OPERATIONS = 'operations';

    public const TYPES = [self::CLINICAL, self::PATIENT_SUMMARY, self::OPERATIONS];

    public const LABELS = [
        self::CLINICAL => 'Confidential clinical note',
        self::PATIENT_SUMMARY => 'Summary for the patient',
        self::OPERATIONS => 'Staff note',
    ];

    protected $fillable = ['patient_id', 'order_id', 'author_id', 'type', 'body', 'action_status', 'action_done_by', 'action_done_at'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'action_done_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(PatientNoteAudience::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }
}
