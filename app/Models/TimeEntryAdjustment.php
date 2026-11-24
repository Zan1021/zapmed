<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit row: a doctor changed the minutes on a time entry (old, new, why, when). Never edited or deleted. */
class TimeEntryAdjustment extends Model
{
    public $timestamps = false;

    protected $fillable = ['time_entry_id', 'user_id', 'old_minutes', 'new_minutes', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
