<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A limited-use, limited-time link to one patient's follow-up booking page. Only the hash of the link is stored. */
class BookingLink extends Model
{
    protected $fillable = ['token_hash', 'patient_id', 'follow_up_request_id', 'max_uses', 'uses', 'expires_at', 'last_used_at', 'revoked_at', 'created_by'];

    protected function casts(): array
    {
        return ['max_uses' => 'integer', 'uses' => 'integer', 'expires_at' => 'datetime', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->uses < $this->max_uses && $this->expires_at->isFuture();
    }

    /** Why it cannot be used, in words for the person who clicked it. */
    public function problem(): ?string
    {
        return match (true) {
            $this->revoked_at !== null => 'This link was switched off.',
            $this->expires_at->isPast() => 'This link has expired.',
            $this->uses >= $this->max_uses => 'This link has been used too many times.',
            default => null,
        };
    }
}
