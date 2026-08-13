<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    protected $fillable = [
        'subject', 'body', 'segment', 'status',
        'recipients_count', 'sent_count', 'opened_count', 'clicked_count',
        'scheduled_at', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Get subscribers for this newsletter's segment.
     */
    public function getRecipients()
    {
        $query = NewsletterSubscriber::active();

        if ($this->segment !== 'all') {
            $query->withInterest($this->segment);
        }

        return $query;
    }

    public function getOpenRateAttribute(): float
    {
        return $this->sent_count > 0 ? round(($this->opened_count / $this->sent_count) * 100, 1) : 0;
    }

    public function getClickRateAttribute(): float
    {
        return $this->sent_count > 0 ? round(($this->clicked_count / $this->sent_count) * 100, 1) : 0;
    }
}
