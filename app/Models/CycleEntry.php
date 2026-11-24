<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleEntry extends Model
{
    protected $fillable = ['user_id', 'entry_date', 'mood', 'symptoms'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'mood' => 'integer', 'symptoms' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
