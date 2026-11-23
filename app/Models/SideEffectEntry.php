<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SideEffectEntry extends Model
{
    protected $fillable = ['user_id', 'logged_on', 'symptom', 'score', 'flagged'];

    protected function casts(): array
    {
        return ['logged_on' => 'date', 'score' => 'integer', 'flagged' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}