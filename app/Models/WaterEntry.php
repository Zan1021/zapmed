<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterEntry extends Model
{
    protected $fillable = ['user_id', 'logged_at', 'ml'];

    protected function casts(): array
    {
        return ['logged_at' => 'datetime', 'ml' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}