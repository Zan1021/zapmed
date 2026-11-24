<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit trail row for a doctor request: who did what and when. */
class DoctorRequestEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['doctor_request_id', 'user_id', 'event', 'from_status', 'to_status', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
