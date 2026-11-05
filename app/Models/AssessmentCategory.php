<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A category manifest: a name + an ordered list of category-scope question links.
 * The CORE block is NOT stored here — it is resolved at assembly time from live core questions,
 * so it is identical everywhere and cannot be removed from a category (US-8).
 */
class AssessmentCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'status',
        'manifest_version',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'manifest_version' => 'integer',
        ];
    }

    /** Ordered category-scope question links. */
    public function questionLinks(): HasMany
    {
        return $this->hasMany(AssessmentCategoryQuestion::class, 'category_id')->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }
}
