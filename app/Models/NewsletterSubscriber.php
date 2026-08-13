<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email', 'first_name', 'interests', 'source', 'status',
        'unsubscribe_token', 'subscribed_at', 'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'interests' => 'array',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $sub) {
            if (!$sub->unsubscribe_token) {
                $sub->unsubscribe_token = Str::random(64);
            }
            if (!$sub->subscribed_at) {
                $sub->subscribed_at = now();
            }
        });
    }

    public function scopeActive($query) { return $query->where('status', 'active'); }

    public function scopeWithInterest($query, string $interest)
    {
        return $query->whereJsonContains('interests', $interest);
    }

    public function unsubscribe(): void
    {
        $this->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
    }
}
