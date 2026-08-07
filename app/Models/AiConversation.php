<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    protected $fillable = [
        'question',
        'response',
        'matched_treatment_slug',
        'matched_treatment_name',
        'had_match',
        'ip_address',
        'user_agent',
        'source_page',
    ];

    protected function casts(): array
    {
        return [
            'had_match' => 'boolean',
        ];
    }

    /**
     * Scope to unmatched queries (no treatment found).
     */
    public function scopeUnmatched($query)
    {
        return $query->where('had_match', false);
    }

    /**
     * Scope to matched queries.
     */
    public function scopeMatched($query)
    {
        return $query->where('had_match', true);
    }
}
