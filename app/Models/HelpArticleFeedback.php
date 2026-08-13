<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpArticleFeedback extends Model
{
    public $timestamps = false;

    protected $table = 'help_article_feedback';

    protected $fillable = [
        'help_article_id',
        'is_helpful',
        'comment',
        'user_id',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'is_helpful' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class, 'help_article_id');
    }

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
