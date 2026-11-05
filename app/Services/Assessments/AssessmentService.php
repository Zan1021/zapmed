<?php

namespace App\Services\Assessments;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentCategory;
use App\Models\AssessmentQuestion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Assembles the question flow for a category from the bank and records versioned answers.
 *
 * The flow order is CORE (live, fixed core_order) → category questions (by position) → free-text last
 * (design.md §2/§3, US-1). The returned shape matches the LEGACY config shape exactly
 * (id/type/label/options/show_if/required/placeholder/...) so the existing patient Blade view and
 * BookAppointment::isQuestionVisible() keep working unchanged when the source is re-pointed (US-4).
 *
 * Admin-only management lives in QuestionBankService; this class is the read/runtime engine.
 */
class AssessmentService
{
    /**
     * Ordered, assembled flow for a category slug, in the legacy array shape.
     * Returns [] if there is no live category manifest for the slug.
     *
     * @return array<int,array<string,mixed>>
     */
    public function flowFor(string $categorySlug): array
    {
        $category = AssessmentCategory::query()
            ->where('slug', $categorySlug)
            ->where('status', 'live')
            ->first();

        if (! $category) {
            return [];
        }

        $core = $this->coreQuestions();          // CORE first
        $specific = $this->categoryQuestions($category); // then category-specific, by position

        // Free-text "anything else for your doctor" is a CORE question flagged as the tail; keep it last.
        [$coreHead, $coreTail] = $this->splitFreeTextTail($core);

        return $coreHead
            ->concat($specific)
            ->concat($coreTail)
            ->values()
            ->all();
    }

    /** The manifest version to pin on an assessment when it starts (US-3). */
    public function manifestVersionFor(string $categorySlug): ?int
    {
        return AssessmentCategory::query()
            ->where('slug', $categorySlug)
            ->where('status', 'live')
            ->value('manifest_version');
    }

    /** Category id for a slug (to pin on the assessment). */
    public function categoryIdFor(string $categorySlug): ?int
    {
        return AssessmentCategory::query()
            ->where('slug', $categorySlug)
            ->where('status', 'live')
            ->value('id');
    }

    /**
     * Record a single answer with its question version + timestamp + computed flag (US-18).
     * $value is the raw answer (string, or array for multi/checkbox).
     */
    public function saveAnswer(Assessment $assessment, string $questionKey, mixed $value): AssessmentAnswer
    {
        $question = AssessmentQuestion::query()
            ->with('currentVersion')
            ->where('question_key', $questionKey)
            ->first();

        $version = $question?->currentVersion;

        return $assessment->assessmentAnswers()->create([
            'question_id' => $question?->id,
            'question_key' => $questionKey,
            'question_version' => $version?->version ?? 1,
            'answer' => is_array($value) ? json_encode(array_values($value)) : $value,
            'flagged' => $version ? $this->isFlagged($version->flag_rule, $value) : false,
            'answered_at' => Carbon::now(),
        ]);
    }

    /**
     * Whether an answer trips the version's flag_rule (raises a "Needs attention" item for the doctor).
     * flag_rule shape: ['values' => ['option_code', ...]] — flagged if the answer matches any.
     */
    public function isFlagged(?array $flagRule, mixed $value): bool
    {
        if (empty($flagRule['values'] ?? null)) {
            return false;
        }

        $answerValues = is_array($value) ? $value : [$value];

        return count(array_intersect($flagRule['values'], $answerValues)) > 0;
    }

    /**
     * CORE questions (scope=core, live) in fixed core_order, mapped to the legacy array shape.
     *
     * @return Collection<int,array<string,mixed>>
     */
    protected function coreQuestions(): Collection
    {
        return AssessmentQuestion::query()
            ->with('currentVersion')
            ->where('scope', 'core')
            ->where('status', 'live')
            ->orderByRaw('core_order is null') // nulls last
            ->orderBy('core_order')
            ->orderBy('id')
            ->get()
            ->map(fn (AssessmentQuestion $q) => $this->toLegacyShape($q));
    }

    /**
     * Category-specific questions (live) in manifest position order.
     *
     * @return Collection<int,array<string,mixed>>
     */
    protected function categoryQuestions(AssessmentCategory $category): Collection
    {
        return $category->questionLinks()
            ->with(['question' => fn ($q) => $q->with('currentVersion')])
            ->get()
            ->map(fn ($link) => $link->question)
            ->filter(fn (?AssessmentQuestion $q) => $q && $q->status === 'live')
            ->map(fn (AssessmentQuestion $q) => $this->toLegacyShape($q))
            ->values();
    }

    /**
     * Split the CORE block into [everything-else, free-text-tail] so the free-text "anything else"
     * question renders last (US-1). A core question is a tail if its version type is textarea and its
     * key marks it as the closing note (profile_card = 'patient_notes' or key ending in _NOTES).
     *
     * @param  Collection<int,array<string,mixed>>  $core
     * @return array{0:Collection<int,array<string,mixed>>,1:Collection<int,array<string,mixed>>}
     */
    protected function splitFreeTextTail(Collection $core): array
    {
        $isTail = fn (array $q) => ($q['_profile_card'] ?? null) === 'patient_notes'
            || str_ends_with(strtoupper($q['id'] ?? ''), '_NOTES');

        return [
            $core->reject($isTail)->values(),
            $core->filter($isTail)->values(),
        ];
    }

    /**
     * Map a bank question + its live version onto the legacy config array shape that the patient
     * Blade view and isQuestionVisible() already understand. `show_if` is rebuilt from branch_rules:
     *   branch_rules: { depends_on_key: [code, ...] }  →  show_if: { depends_on_key: code }
     * (The current UI/validation compares a single required value; we emit the first code to preserve
     *  identical behaviour. Multi-value branches are a later enhancement, tracked in the spec.)
     *
     * @return array<string,mixed>
     */
    protected function toLegacyShape(AssessmentQuestion $question): array
    {
        $v = $question->currentVersion;

        $shape = [
            'id' => $question->question_key,
            'type' => $this->legacyType($v?->type ?? 'single'),
            'label' => $v?->text ?? $question->question_key,
            'required' => (bool) ($v?->required ?? false),
        ];

        if (! empty($v?->placeholder)) {
            $shape['placeholder'] = $v->placeholder;
        }

        if (! empty($v?->helper_text)) {
            $shape['helper'] = $v->helper_text;
        }

        if (! empty($v?->icon)) {
            $shape['icon'] = $v->icon;
        }

        // options: version stores [ {code,label,flag?} ]; legacy view expects a flat list of labels.
        if (! empty($v?->options) && is_array($v->options)) {
            $shape['options'] = array_map(
                fn ($o) => is_array($o) ? ($o['label'] ?? $o['code'] ?? '') : $o,
                $v->options
            );
        }

        // branch_rules → show_if (first code per dependency, matching current single-value behaviour).
        if (! empty($v?->branch_rules) && is_array($v->branch_rules)) {
            $showIf = [];
            foreach ($v->branch_rules as $depKey => $codes) {
                $showIf[$depKey] = is_array($codes) ? ($codes[0] ?? null) : $codes;
            }
            $shape['show_if'] = $showIf;
        }

        // internal hint (underscore-prefixed) used only by splitFreeTextTail; stripped before render.
        if (! empty($v?->profile_card)) {
            $shape['_profile_card'] = $v->profile_card;
        }

        return $shape;
    }

    /**
     * Map bank type vocabulary to the legacy view's type strings.
     * Bank: single|multi|text|textarea|number|date|select|upload
     * View: radio|checkbox|text|textarea|number|date|select|file
     */
    protected function legacyType(string $type): string
    {
        return match ($type) {
            'single' => 'radio',
            'multi' => 'checkbox',
            'upload' => 'file',
            default => $type, // text, textarea, number, date, select pass through
        };
    }
}
