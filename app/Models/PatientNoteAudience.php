<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One addressee of a staff note: a person or a whole role. */
class PatientNoteAudience extends Model
{
    protected $fillable = ['patient_note_id', 'user_id', 'role', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(PatientNote::class, 'patient_note_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
