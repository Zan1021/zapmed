<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderSetting extends Model
{
    protected $fillable = ['user_id', 'type', 'enabled', 'settings', 'last_sent_at'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'settings' => 'array', 'last_sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}