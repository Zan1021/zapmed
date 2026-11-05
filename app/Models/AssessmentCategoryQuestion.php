<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ordered link between a category and a category-scope question.
 * Unique (category_id, question_id) at the DB level blocks adding the same question twice (US-10).
 */
class AssessmentCategoryQuestion extends Model
{
    protected $fillable = [
        'category_id',
        'question_id',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'category_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'question_id');
    }
}
