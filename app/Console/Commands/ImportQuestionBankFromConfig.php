<?php

namespace App\Console\Commands;

use App\Models\AssessmentCategory;
use App\Models\AssessmentCategoryQuestion;
use App\Models\AssessmentQuestion;
use App\Services\Assessments\QuestionBankService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the question bank from config/assessment-questions.php + config/treatments.php (design.md §7,
 * Task 3.1). Everything is imported as DRAFT — nothing goes Live automatically; Craig/Dr Anke review
 * and publish via the admin portal (and later reconcile against Craig's Drive package).
 *
 * Dedup strategy for the CORE block: questions whose id appears (by id) in a majority of categories are
 * promoted to a single CORE question; per-category copies are dropped from the category manifest. The
 * CORE set is intentionally conservative here — it is a PROPOSAL for clinical review, not the final word.
 *
 * Idempotent: re-running clears the previously config-imported rows (those tagged source=config) and
 * rebuilds. It never touches rows created in the admin portal (no source tag) or Live rows.
 *
 * Usage: php artisan questionbank:import-config [--fresh]
 */
class ImportQuestionBankFromConfig extends Command
{
    protected $signature = 'questionbank:import-config {--fresh : Wipe previously config-imported draft rows first}';

    protected $description = 'Seed the question bank (as Draft) from the legacy assessment-questions config';

    /** ids that recur across many categories → promote to the shared CORE block (proposal). */
    private const CORE_MIN_CATEGORIES = 8;

    public function handle(QuestionBankService $bank): int
    {
        $byCategory = config('assessment-questions', []);
        $treatments = $this->treatmentNameMap();

        if (empty($byCategory)) {
            $this->error('config/assessment-questions.php is empty or missing.');
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->wipeConfigImported();
        }

        // 1) Count how often each question id appears across categories → CORE candidates.
        $idFrequency = [];
        foreach ($byCategory as $questions) {
            foreach ($questions as $q) {
                $idFrequency[$q['id']] = ($idFrequency[$q['id']] ?? 0) + 1;
            }
        }
        $coreIds = array_keys(array_filter($idFrequency, fn ($n) => $n >= self::CORE_MIN_CATEGORIES));

        $this->info(sprintf('Categories: %d · CORE candidates (in ≥%d categories): %d',
            count($byCategory), self::CORE_MIN_CATEGORIES, count($coreIds)));

        $createdQuestions = 0;
        $createdCategories = 0;
        $links = 0;

        // id (legacy) → question_key (bank) map, so branch_rules dependency keys resolve correctly.
        $keyByLegacyId = [];

        DB::transaction(function () use ($byCategory, $treatments, $coreIds, &$createdQuestions, &$createdCategories, &$links, &$keyByLegacyId) {
            // 2) Build the CORE block once, from the first definition seen of each core id.
            $coreMap = []; // id => AssessmentQuestion
            $coreOrder = 1;
            foreach ($coreIds as $coreId) {
                $def = $this->firstDefinitionOf($byCategory, $coreId);
                if (! $def) {
                    continue;
                }
                $q = $this->makeQuestion($def, 'core', $coreOrder++);
                $coreMap[$coreId] = $q;
                $keyByLegacyId[$coreId] = $q->question_key;
                $createdQuestions++;
            }

            // 3) Each category → manifest of its NON-core questions, in source order.
            foreach ($byCategory as $slug => $questions) {
                $category = AssessmentCategory::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $treatments[$slug] ?? $this->humanise($slug), 'status' => 'draft', 'manifest_version' => 1]
                );
                $createdCategories++;

                // per-category id → key map (seeded with core keys), so branch deps resolve to the
                // SAME category's question keys — recurring ids like medical_conditions get the right
                // per-category suffix, not a globally-ambiguous one.
                $localKeyById = $keyByLegacyId; // core keys are shared/global
                $madeThisCategory = [];

                $position = 1;
                foreach ($questions as $q) {
                    if (in_array($q['id'], $coreIds, true)) {
                        continue; // covered by CORE, not repeated in the manifest
                    }

                    $question = $this->makeQuestion($q, 'category');
                    $localKeyById[$q['id']] = $question->question_key;
                    $madeThisCategory[] = $question->id;
                    $createdQuestions++;

                    AssessmentCategoryQuestion::create([
                        'category_id' => $category->id,
                        'question_id' => $question->id,
                        'position' => $position++,
                    ]);
                    $links++;
                }

                // remap branch deps for THIS category's questions using the local map
                $this->remapBranchDependencies($localKeyById, $madeThisCategory);
            }
        });

        $this->newLine();
        $this->info("Imported (all DRAFT):");
        $this->line("  questions : {$createdQuestions}");
        $this->line("  categories: {$createdCategories}");
        $this->line("  links     : {$links}");
        $this->newLine();
        $this->comment('Nothing is Live. Review + publish via the admin portal. Re-run with --fresh to rebuild.');

        return self::SUCCESS;
    }

    /** Create a question + its v1 draft version from a legacy config question array. */
    private function makeQuestion(array $q, string $scope, ?int $coreOrder = null): AssessmentQuestion
    {
        $question = AssessmentQuestion::create([
            'question_key' => $this->keyFor($q['id'], $scope),
            'scope' => $scope,
            'clinical' => $this->looksClinical($q),
            'core_order' => $coreOrder,
            'status' => 'draft',
        ]);

        $version = $question->versions()->create([
            'version' => 1,
            'text' => $q['label'] ?? $q['id'],
            'type' => $this->bankType($q['type'] ?? 'text'),
            'options' => $this->mapOptions($q['options'] ?? null),
            'branch_rules' => $this->mapShowIf($q['show_if'] ?? null),
            'required' => (bool) ($q['required'] ?? false),
            'placeholder' => $q['placeholder'] ?? null,
            // tag the source so --fresh can find config-imported rows later.
            'reason' => 'config-import',
        ]);

        $question->update(['current_version_id' => $version->id]);

        return $question;
    }

    /** Legacy view type → bank type vocabulary. */
    private function bankType(string $type): string
    {
        return match ($type) {
            'radio' => 'single',
            'checkbox' => 'multi',
            'file' => 'upload',
            default => $type, // text, textarea, number, date, select
        };
    }

    /** Legacy options (flat label list) → bank option objects [ {code,label} ]. */
    private function mapOptions(?array $options): ?array
    {
        if (empty($options)) {
            return null;
        }

        return array_map(fn ($label) => [
            'code' => \Illuminate\Support\Str::slug((string) $label, '_'),
            'label' => $label,
        ], $options);
    }

    /** Legacy show_if (id => value) → branch_rules (key => [code]). */
    private function mapShowIf(?array $showIf): ?array
    {
        if (empty($showIf)) {
            return null;
        }

        $rules = [];
        foreach ($showIf as $depId => $value) {
            $rules[$depId] = [(string) $value];
        }

        return $rules;
    }

    /**
     * Rewrite branch_rules dependency keys from legacy ids to the bank question_keys the checkboxes
     * actually bind to. Scoped to a given set of question ids so each category's conditional deps
     * resolve to that SAME category's keys (recurring ids get the right per-category suffix).
     *
     * @param  array<string,string>  $keyByLegacyId  legacy id → bank question_key (for this category)
     * @param  array<int,int>         $questionIds    only remap versions of these questions
     */
    private function remapBranchDependencies(array $keyByLegacyId, array $questionIds): void
    {
        if (empty($questionIds)) {
            return;
        }

        $versions = \App\Models\AssessmentQuestionVersion::whereIn('question_id', $questionIds)
            ->whereNotNull('branch_rules')
            ->get();

        foreach ($versions as $version) {
            $rules = $version->branch_rules;
            if (empty($rules)) {
                continue;
            }

            $remapped = [];
            foreach ($rules as $depId => $codes) {
                $depKey = $keyByLegacyId[$depId] ?? strtoupper($depId); // fall back to uppercase
                $remapped[$depKey] = $codes;
            }

            $version->update(['branch_rules' => $remapped]);
        }
    }

    /** First time a given id is defined across all categories (its canonical wording). */
    private function firstDefinitionOf(array $byCategory, string $id): ?array
    {
        foreach ($byCategory as $questions) {
            foreach ($questions as $q) {
                if ($q['id'] === $id) {
                    return $q;
                }
            }
        }
        return null;
    }

    private function keyFor(string $id, string $scope): string
    {
        $prefix = $scope === 'core' ? 'CORE_' : '';
        $key = $prefix.strtoupper($id);
        // ensure uniqueness if the same category-id string recurs (category scope only)
        $base = $key;
        $n = 2;
        while (AssessmentQuestion::where('question_key', $key)->exists()) {
            $key = $base.'_'.$n++;
        }
        return $key;
    }

    /** Heuristic: questions touching meds, allergies, conditions, pregnancy are clinical. */
    private function looksClinical(array $q): bool
    {
        $hay = strtolower(($q['label'] ?? '').' '.($q['id'] ?? ''));
        foreach (['allerg', 'medication', 'medicine', 'condition', 'pregnan', 'diabet', 'nitrate', 'blood', 'cardiac', 'disease'] as $needle) {
            if (str_contains($hay, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function treatmentNameMap(): array
    {
        $map = [];
        foreach (config('treatments', []) as $category) {
            foreach ($category['treatments'] ?? [] as $slug => $t) {
                $map[$slug] = $t['name'] ?? $this->humanise($slug);
            }
        }
        return $map;
    }

    private function humanise(string $slug): string
    {
        return \Illuminate\Support\Str::of($slug)->replace('-', ' ')->title();
    }

    /**
     * Remove ALL config-imported rows so a re-run rebuilds cleanly (idempotent). A question is
     * config-imported if ANY of its versions carries reason='config-import'. We only skip questions
     * that have an admin-authored version (reason != config-import and not null) AND are Live — so we
     * never destroy human edits, but we DO reclaim demoted/renamed draft rows (the accumulation bug).
     */
    private function wipeConfigImported(): void
    {
        $configQuestionIds = \App\Models\AssessmentQuestionVersion::where('reason', 'config-import')
            ->pluck('question_id')->unique();

        // keep any question that has a human (non-config) published version
        $humanEditedIds = \App\Models\AssessmentQuestionVersion::whereIn('question_id', $configQuestionIds)
            ->whereNotNull('published_by')
            ->pluck('question_id')->unique();

        $wipeIds = $configQuestionIds->diff($humanEditedIds);

        AssessmentCategoryQuestion::whereIn('question_id', $wipeIds)->delete();
        \App\Models\AssessmentQuestionVersion::whereIn('question_id', $wipeIds)->delete();
        AssessmentQuestion::whereIn('id', $wipeIds)->delete();

        // drop now-empty categories that have no remaining questions and no human edits (draft only)
        AssessmentCategory::where('status', 'draft')
            ->whereDoesntHave('questionLinks')
            ->delete();

        $this->warn('Wiped '.$wipeIds->count().' previously config-imported questions.');
    }
}
