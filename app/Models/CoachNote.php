<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A coach's note about a member. Private = author only. Shared = assigned coach + treating doctor. Body encrypted. */
class CoachNote extends Model
{
    public const PRIVATE = 'private';
    public const SHARED = 'shared';

    protected $fillable = ['patient_id', 'author_id', 'visibility', 'body'];

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Notes this viewer may read: their own private notes plus every shared note. Caller must already have checked patient access. */
    public function scopeReadableBy($q, User $viewer)
    {
        return $q->where(fn ($q) => $q->where('visibility', self::SHARED)
            ->orWhere(fn ($q) => $q->where('visibility', self::PRIVATE)->where('author_id', $viewer->id)));
    }
}
