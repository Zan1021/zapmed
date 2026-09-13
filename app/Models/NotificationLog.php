<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per outbound message attempt, written by NotificationDispatcher.
 * See specs/notifications-parity/01-scope-and-gap-analysis.md (Option B).
 */
class NotificationLog extends Model
{
    // Status values.
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUPPRESSED = 'suppressed'; // blocked by opt-out
    public const STATUS_SKIPPED = 'skipped';       // no recipient / not applicable

    // Channel values.
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'channel', 'template_key', 'category', 'user_id', 'recipient',
        'status', 'provider', 'provider_ref', 'error', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSent($query) { return $query->where('status', self::STATUS_SENT); }
    public function scopeFailed($query) { return $query->where('status', self::STATUS_FAILED); }
    public function scopeChannel($query, string $channel) { return $query->where('channel', $channel); }
}
