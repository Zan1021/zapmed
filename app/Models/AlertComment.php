<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alert comment / activity entry (parity with alerts_comment). Append-only in practice.
 */
class AlertComment extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'alerts_comments';

    protected $fillable = ['alert_id', 'body', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
