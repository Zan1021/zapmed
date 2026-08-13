<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpSearchLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'query',
        'results_count',
        'user_id',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'results_count' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->created_at = $model->created_at ?? now();
        });
    }
}
