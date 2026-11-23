<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoseEntry extends Model
{
    protected $fillable = ['user_id', 'taken_at', 'site', 'pain'];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime', 'pain' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}