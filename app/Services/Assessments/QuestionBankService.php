<?php

namespace App\Services\Assessments;

use App\Models\AssessmentCategory;
use App\Models\AssessmentCategoryQuestion;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionVersion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Admin-side writes for the question bank. ADMIN-ONLY — there is NO clinical-approver gate
 * (Captain Zan decision 2026-11-03): the main system admin (Craig) creates, edits, publishes and
 * retires everything; a change reason is recorded on every publish for audit.
 *
 * Rules enforced here (not just in the UI):
 *   - Questions are NEVER deleted, only retired (past answers still reference them). US-7.
 *   - Editing creates a NEW draft version; publishing moves the live pointer. US-15.
 *   - A question cannot be added to a category twice, nor duplicate a CORE question. US-10.
 */
class QuestionBankService
{
    /**
     * Create a brand-new question with its first (draft) version.
     */
    public function createQuestion(array $attrs, array $versionAttrs, ?User $actor = null): AssessmentQuestion
    {
        return DB::transaction(function () use ($attrs, $versionAttrs, $actor) {
            $question = AssessmentQuestion::create([
                'question_key' => $attrs['question_key'] ?? $this->generateKey($attrs['scope'] ?? 'category', $versionAttrs['text'] ?? ''),
                'scope' => $attrs['scope'] ?? 'category',
                'clinical' => $attrs['clinical'] ?? false,
                'core_order' => $attrs['core_order'] ?? null,
                'status' => 'draft',
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $version = $this->newVersion($question, $versionAttrs, 1);
            $question->update(['current_version_id' => $version->id]);

            return $question->refresh();
        });
    }

    /**
     * Edit a question = create a NEW draft version (never overwrite). The live pointer does not move
     * until publish(). Returns the new draft version.
     */
    public function editQuestion(AssessmentQuestion $question, array $versionAttrs, ?User $actor = null): AssessmentQuestionVersion
    {
        if ($question->isRetired()) {
            throw new RuntimeException('Cannot edit a retired question. Duplicate it instead.');
        }

        return DB::transaction(function () use ($question, $versionAttrs, $actor) {
            $next = ((int) $question->versions()->max('version')) + 1;
            $version = $this->newVersion($question, $versionAttrs, $next);

            $question->update([
                'status' => 'draft',
                'updated_by' => $actor?->id,
            ]);

            return $version;
        });
    }

    /**
     * Publish a question version Live. Admin-only (authorisation enforced by route middleware).
     * Moves the live pointer, stamps who/when/why. No clinical-approver gate.
     */
    public function publish(AssessmentQuestion $question, AssessmentQuestionVersion $version, string $reason, ?User $actor = null): void
    {
        DB::transaction(function () use ($question, $version, $reason, $actor) {
            $version->update([
                'published_at' => Carbon::now(),
                'published_by' => $actor?->id,
                'reason' => $reason,
            ]);

            $question->update([
                'current_version_id' => $version->id,
                'status' => 'live',
                'updated_by' => $actor?->id,
            ]);
        });
    }

    /**
     * Retire a question (US-7). Never a hard delete — past answers still point at it.
     */
    public function retire(AssessmentQuestion $question, ?User $actor = null): void
    {
        $question->update(['status' => 'retired', 'updated_by' => $actor?->id]);
    }

    /**
     * Duplicate a question into a fresh draft (e.g. to adapt a retired one).
     */
    public function duplicate(AssessmentQuestion $question, ?User $actor = null): AssessmentQuestion
    {
        $v = $question->currentVersion ?? $question->versions()->latest('version')->first();

        return $this->createQuestion(
            [
                'scope' => $question->scope,
                'clinical' => $question->clinical,
                'question_key' => $question->question_key.'_COPY',
            ],
            [
                'text' => ($v?->text ?? '').' (copy)',
                'helper_text' => $v?->helper_text,
                'why_we_ask' => $v?->why_we_ask,
                'type' => $v?->type ?? 'single',
                'options' => $v?->options,
                'icon' => $v?->icon,
                'branch_rules' => $v?->branch_rules,
                'flag_rule' => $v?->flag_rule,
                'required' => $v?->required ?? false,
                'placeholder' => $v?->placeholder,
            ],
            $actor
        );
    }

    /**
     * Add a question to a category at the end of the flow. Blocks duplicates and CORE overlap (US-10).
     *
     * @throws RuntimeException with a message naming the conflicting question.
     */
    public function addQuestionToCategory(AssessmentCategory $category, AssessmentQuestion $question, ?User $actor = null): AssessmentCategoryQuestion
    {
        if ($question->scope === 'core') {
            throw new RuntimeException('"'.$question->question_key.'" is a CORE question — it is already asked in every category. Edit it in the question library.');
        }

        $exists = $category->questionLinks()->where('question_id', $question->id)->exists();
        if ($exists) {
            throw new RuntimeException('"'.$question->question_key.'" is already in this category.');
        }

        return DB::transaction(function () use ($category, $question, $actor) {
            $position = ((int) $category->questionLinks()->max('position')) + 1;

            $link = $category->questionLinks()->create([
                'question_id' => $question->id,
                'position' => $position,
            ]);

            $this->bumpManifest($category, $actor);

            return $link;
        });
    }

    /**
     * Reorder a category's questions. $orderedQuestionIds is the new top-to-bottom order.
     */
    public function reorderCategory(AssessmentCategory $category, array $orderedQuestionIds, ?User $actor = null): void
    {
        DB::transaction(function () use ($category, $orderedQuestionIds, $actor) {
            foreach (array_values($orderedQuestionIds) as $i => $questionId) {
                $category->questionLinks()
                    ->where('question_id', $questionId)
                    ->update(['position' => $i + 1]);
            }
            $this->bumpManifest($category, $actor);
        });
    }

    /**
     * Create a new category manifest (US-12 — the whole feature's acceptance test).
     */
    public function createCategory(string $slug, string $name, ?User $actor = null): AssessmentCategory
    {
        return AssessmentCategory::create([
            'slug' => Str::slug($slug),
            'name' => $name,
            'status' => 'draft',
            'manifest_version' => 1,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    /** Publish a category Live so the patient flow picks it up. Admin-only. */
    public function publishCategory(AssessmentCategory $category, ?User $actor = null): void
    {
        $category->update(['status' => 'live', 'updated_by' => $actor?->id]);
    }

    // ── internals ───────────────────────────────────────────────────────────────────────────────

    protected function newVersion(AssessmentQuestion $question, array $attrs, int $version): AssessmentQuestionVersion
    {
        return $question->versions()->create([
            'version' => $version,
            'text' => $attrs['text'] ?? '',
            'helper_text' => $attrs['helper_text'] ?? null,
            'why_we_ask' => $attrs['why_we_ask'] ?? null,
            'type' => $attrs['type'] ?? 'single',
            'options' => $attrs['options'] ?? null,
            'icon' => $attrs['icon'] ?? null,
            'branch_rules' => $attrs['branch_rules'] ?? null,
            'flag_rule' => $attrs['flag_rule'] ?? null,
            'profile_card' => $attrs['profile_card'] ?? null,
            'required' => $attrs['required'] ?? false,
            'placeholder' => $attrs['placeholder'] ?? null,
        ]);
    }

    protected function bumpManifest(AssessmentCategory $category, ?User $actor = null): void
    {
        $category->update([
            'manifest_version' => $category->manifest_version + 1,
            'updated_by' => $actor?->id,
        ]);
    }

    protected function generateKey(string $scope, string $text): string
    {
        $prefix = $scope === 'core' ? 'CORE' : 'Q';
        $slug = Str::of($text)->slug('_')->upper()->limit(24, '');

        return $prefix.'_'.($slug->isEmpty() ? Str::upper(Str::random(6)) : $slug);
    }
}
