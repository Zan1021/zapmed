<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable snapshot of a question's wording + options + branching at a point in time.
 * Never overwritten; a new row is created on every edit.
 */
class AssessmentQuestionVersion extends Model
{
    protected $fillable = [
        'question_id',
        'version',
        'text',
        'helper_text',
        'why_we_ask',
        'type',
        'options',
        'icon',
        'branch_rules',
        'flag_rule',
        'profile_card',
        'required',
        'placeholder',
        'published_at',
        'published_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'options' => 'array',
            'branch_rules' => 'array',
            'flag_rule' => 'array',
            'required' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'question_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
