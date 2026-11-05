<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A question in the bank. Defined once, reused across categories by key.
 * The live wording lives in the current version (currentVersion); editing creates a new version.
 */
class AssessmentQuestion extends Model
{
    protected $fillable = [
        'question_key',
        'scope',
        'clinical',
        'core_order',
        'status',
        'current_version_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'clinical' => 'boolean',
            'core_order' => 'integer',
        ];
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestionVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AssessmentQuestionVersion::class, 'question_id');
    }

    public function categoryLinks(): HasMany
    {
        return $this->hasMany(AssessmentCategoryQuestion::class, 'question_id');
    }

    public function scopeCore($query)
    {
        return $query->where('scope', 'core');
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    /** How many categories use this question (US-6). */
    public function usedInCount(): int
    {
        return $this->categoryLinks()->distinct('category_id')->count('category_id');
    }

    public function isRetired(): bool
    {
        return $this->status === 'retired';
    }
}
