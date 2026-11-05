<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answered question. Carries the question_version the patient answered, its own timestamp,
 * and a computed `flagged` bit (US-18). The answer value is encrypted at rest, matching the
 * assessments.answers blob pattern.
 */
class AssessmentAnswer extends Model
{
    protected $fillable = [
        'assessment_id',
        'question_id',
        'question_key',
        'question_version',
        'answer',
        'flagged',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'answer' => 'encrypted',
            'question_version' => 'integer',
            'flagged' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'question_id');
    }
}
