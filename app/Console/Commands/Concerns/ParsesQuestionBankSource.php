<?php

namespace App\Console\Commands\Concerns;

use App\Models\AssessmentCategory;
use App\Models\AssessmentCategoryQuestion;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shared clinical-source parser for the question bank.
 *
 * Both importers (Word .docx and Markdown .md) feed a flat list of lines through the SAME grammar so
 * behaviour is identical regardless of source format. The grammar (learned from Craig's source doc):
 *
 *   <section title>                      → an assessment / category boundary
 *   "SELECT: <text>?"  then  "[n] opt"   → single-choice question with those options
 *   "<text>?"          then  "[n] opt"   → single-choice question
 *   "<text>?"          then bare lines   → multi-choice (checkbox); bare lines are options
 *   "[IF YES] <text>"                    → conditional text question, shown if previous Q = Yes
 *
 * Line shape consumed: array<int,array{text:string,is_h1:bool}>.
 */
trait ParsesQuestionBankSource
{
    /**
     * Parse the flat line list into categories → questions.
     *
     * @param  array<int,array{text:string,is_h1:bool}>  $lines
     * @return array<int,array{name:string,questions:array}>
     */
    protected function parseSource(array $lines): array
    {
        $categories = [];
        $current = null;
        $pendingQuestion = null; // question awaiting its options

        $flush = function () use (&$pendingQuestion, &$current) {
            if ($pendingQuestion !== null && $current !== null) {
                if (! empty($pendingQuestion['options'])) {
                    $pendingQuestion['type'] = $pendingQuestion['numbered'] ? 'single' : 'multi';
                } else {
                    $pendingQuestion['type'] = 'text';
                }

                // Explicit widget hints in the question text reduce typing for the patient.
                $this->applyWidgetHint($pendingQuestion);

                unset($pendingQuestion['numbered']);
                $current['questions'][] = $pendingQuestion;
            }
            $pendingQuestion = null;
        };

        foreach ($lines as $ln) {
            $text = $ln['text'];

            if ($ln['is_h1'] || $this->looksLikeTitle($text)) {
                $flush();
                if ($current) {
                    $categories[] = $current;
                }
                $current = ['name' => $this->cleanTitle($text), 'questions' => []];
                continue;
            }

            if ($current === null) {
                continue; // skip preamble before first heading
            }

            // bare "[TEXT]" marker — the source's way of saying "the preceding question takes a
            // free-text answer". Force the pending question (or the last-emitted one) to type=text
            // and clear any stray options. This is why e.g. "Please provide further details" lines
            // must render as a text box, not a one-option choice.
            if (preg_match('/^\[TEXT\]$/i', trim($text))) {
                if ($pendingQuestion !== null) {
                    $pendingQuestion['type'] = 'text';
                    $pendingQuestion['options'] = [];
                    $pendingQuestion['numbered'] = false;
                    $flush();
                } elseif (! empty($current['questions'])) {
                    $last = array_key_last($current['questions']);
                    // don't downgrade a question that already resolved to a richer widget
                    // (date/number/select) via an explicit hint — the [TEXT] here is redundant.
                    if (! in_array($current['questions'][$last]['type'] ?? 'text', ['date', 'number', 'select'], true)) {
                        $current['questions'][$last]['type'] = 'text';
                        $current['questions'][$last]['options'] = [];
                    }
                }
                continue;
            }

            // conditional follow-up — [IF YES], [IF NO], [IF SELECTED], etc.
            if (preg_match('/^\[IF\s+([A-Z ]+)\]\s*(.+)$/i', $text, $m)) {
                $flush();
                $q = [
                    'text' => $this->clean(rtrim($m[2])),
                    'type' => 'text',
                    'options' => [],
                    'if_yes' => stripos($m[1], 'YES') !== false,
                ];
                $this->applyWidgetHint($q); // honour "(calendar date)"/"(number)" etc. on conditionals too
                $current['questions'][] = $q;
                continue;
            }

            // bracketed follow-up QUESTION — e.g. "[Do you have any allergies?]". Must NOT match
            // numbered options like "[2] No - [Current weight? (kg)]".
            if (! preg_match('/^\[\d+\]/', $text)
                && preg_match('/^\[(.+)\]$/', trim($text), $m)
                && str_contains($m[1], '?')) {
                $flush();
                $inner = $this->clean(rtrim($m[1]));
                $pendingQuestion = [
                    'text' => $inner,
                    'type' => preg_match('/\b(kg|cm|years?|age)\b/i', $inner) ? 'number' : 'text',
                    'options' => [],
                    'if_yes' => false,
                    'numbered' => false,
                ];
                continue;
            }

            // numbered option  [1] Label   (strip any inline "- [follow-up]" tail → keep just the label)
            if (preg_match('/^\[(\d+)\]\s*(.+)$/', $text, $m)) {
                if ($pendingQuestion !== null) {
                    $label = preg_replace('/\s*-\s*\[.*\]\s*$/', '', $m[2]);
                    $pendingQuestion['options'][] = $this->clean(rtrim($label, ": "));
                    $pendingQuestion['numbered'] = true;
                }
                continue;
            }

            // bracketed INSTRUCTION stem — opens a multi-choice question whose options are the bare
            // lines that follow it.
            if (preg_match('/^\[(.+)\]$/', trim($text), $m)) {
                $flush();
                $pendingQuestion = [
                    'text' => $this->clean($m[1]),
                    'type' => 'multi',
                    'options' => [],
                    'if_yes' => false,
                    'numbered' => false,
                ];
                continue;
            }

            // SELECT: question (single choice)
            if (preg_match('/^SELECT:\s*(.+)$/i', $text, $m)) {
                $flush();
                $pendingQuestion = ['text' => $this->clean($m[1]), 'type' => 'single', 'options' => [], 'if_yes' => false, 'numbered' => false];
                continue;
            }

            // a question line (ends with ?) — opens a new question
            if (str_ends_with(rtrim($text), '?')) {
                $flush();
                $pendingQuestion = ['text' => $this->clean($text), 'type' => 'single', 'options' => [], 'if_yes' => false, 'numbered' => false];
                continue;
            }

            // otherwise: a bare option line belonging to the pending (multi-choice) question.
            if ($pendingQuestion !== null) {
                $label = preg_replace('/\s*-\s*\[.*\]\s*$/', '', $text);
                $pendingQuestion['options'][] = $this->clean($label);
            }
        }

        $flush();
        if ($current) {
            $categories[] = $current;
        }

        return $categories;
    }

    /**
     * Persist parsed categories → questions (all DRAFT, scope=category). Idempotent per run.
     * Shared by both importers so the DB shape is identical.
     *
     * @param  array<int,array{name:string,questions:array}>  $parsed
     * @return array{categories:int,questions:int,links:int}
     */
    protected function persistParsed(array $parsed): array
    {
        $createdQuestions = 0;
        $createdCategories = 0;
        $links = 0;

        DB::transaction(function () use ($parsed, &$createdQuestions, &$createdCategories, &$links) {
            foreach ($parsed as $cat) {
                $slug = $this->slugForCategory($cat['name']);
                $category = AssessmentCategory::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $cat['name'], 'status' => 'draft', 'manifest_version' => 1],
                );
                $createdCategories++;

                $position = 1;
                $lastKey = null;

                foreach ($cat['questions'] as $q) {
                    $question = AssessmentQuestion::create([
                        'question_key' => $this->uniqueKey($cat['name'], $q['text']),
                        'scope' => 'category',
                        'clinical' => $this->looksClinical($q['text']),
                        'status' => 'draft',
                    ]);

                    $branch = null;
                    if ($q['if_yes'] && $lastKey) {
                        $branch = [$lastKey => ['Yes']];
                    }

                    $version = $question->versions()->create([
                        'version' => 1,
                        'text' => $q['text'],
                        'type' => $q['type'],
                        'options' => $this->mapOptions($q['options']),
                        'branch_rules' => $branch,
                        'required' => ! $q['if_yes'],
                        'reason' => 'md-import',
                    ]);
                    $question->update(['current_version_id' => $version->id]);
                    $createdQuestions++;

                    AssessmentCategoryQuestion::create([
                        'category_id' => $category->id,
                        'question_id' => $question->id,
                        'position' => $position++,
                    ]);
                    $links++;

                    if (! $q['if_yes']) {
                        $lastKey = $question->question_key;
                    }
                }
            }
        });

        return ['categories' => $createdCategories, 'questions' => $createdQuestions, 'links' => $links];
    }

    protected function looksLikeTitle(string $text): bool
    {
        $t = trim($text);
        if (str_contains($t, '?') || str_starts_with($t, '[') || Str::startsWith(Str::upper($t), 'SELECT:')) {
            return false;
        }
        if (Str::length($t) > 55) {
            return false;
        }
        return (bool) preg_match('/\b(Consultation|Health Assessment|Assessment)\s*$/i', $t);
    }

    protected function slugForCategory(string $name): string
    {
        $key = Str::of($name)->lower()->squish()->toString();

        $map = [
            'acne' => 'acne-treatment',
            'anti-aging' => 'anti-aging-treatment',
            'anti-ageing' => 'anti-aging-treatment',
            'rosacea' => 'rosacea-treatment',
            'bacterial vaginosis' => 'bacterial-vaginosis-treatment',
            'birth control' => 'birth-control',
            'menopause management' => 'menopause-management',
            'menopause' => 'menopause-management',
            'erectile dysfunction' => 'erectile-dysfunction-treatment',
            'premature ejaculation' => 'premature-ejaculation-treatment',
            'genital herpes' => 'genital-herpes-101',
            'genital warts' => 'genital-warts-treatment',
            'sti' => 'sti-treatment',
            'cold sores' => 'cold-sores-treatment',
            'acid reflux' => 'acid-reflux-treatment',
            'haemorrhoids' => 'haemorrhoids-treatment',
            'haemorrhoid' => 'haemorrhoids-treatment',
            'haemorrhoid treatment' => 'haemorrhoids-treatment',
            'hair loss' => 'hair-loss-treatment',
            'hairloss' => 'hair-loss-treatment',
            'uti' => 'uti-treatment',
            'thrush' => 'thrush-treatment',
            'gp' => 'gp-consult',
            'gp consult' => 'gp-consult',
            'weight loss' => 'weight-loss',
            'health coach' => 'health-coach-support',
        ];

        return $map[$key] ?? Str::slug($name);
    }

    /**
     * Apply an explicit widget hint found in a question's text so patients can SELECT instead of type.
     * Overrides the structural type for OPTION-LESS questions only (never clobbers a real choice list):
     *   "(month/year)" / "(calendar date)" / "(date)"  → month+year date picker (type=date)
     *   "(number)"     / "how many ...?"                → numeric field        (type=number)
     * The hint tag itself is stripped from the visible label. Mutates $q by reference.
     *
     * @param  array<string,mixed>  $q
     */
    protected function applyWidgetHint(array &$q): void
    {
        if (! empty($q['options'])) {
            return; // keep real multiple-choice questions untouched
        }

        $t = $q['text'] ?? '';

        if (preg_match('/\((?:month\s*\/?\s*year|calendar\s*date|date)\)\s*$/i', $t)) {
            $q['type'] = 'date';
        } elseif (preg_match('/\(number\)\s*$/i', $t) || preg_match('/^how many\b.*\?$/i', $t)) {
            $q['type'] = 'number';
        }

        $q['text'] = preg_replace('/\s*\((?:month\s*\/?\s*year|calendar\s*date|date|number)\)\s*$/i', '', $t);
    }

    protected function mapOptions(array $options): ?array
    {
        if (empty($options)) {
            return null;
        }
        return array_map(fn ($label) => [
            'code' => Str::slug($label, '_') ?: Str::lower(Str::random(6)),
            'label' => $label,
        ], $options);
    }

    /** Fix Windows-1252 mojibake and strip leading conditional directives from labels. */
    protected function clean(string $s): string
    {
        $s = str_replace(["\u{FFFD}", '�'], "'", $s);
        $s = preg_replace('/^\s*\[?\s*IF\b[^\]:]*(?:\]|:)\s*/i', '', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    protected function cleanTitle(string $s): string
    {
        return $this->clean(preg_replace('/\s*(Consultation|Health Assessment|Assessment)\s*$/i', '', $s)) ?: $this->clean($s);
    }

    protected function looksClinical(string $text): bool
    {
        $hay = Str::lower($text);
        foreach (['allerg', 'medicat', 'medicine', 'condition', 'pregnan', 'diabet', 'nitrate', 'blood', 'cardiac', 'disease', 'breastfeed', 'kidney', 'liver', 'prescri'] as $n) {
            if (str_contains($hay, $n)) {
                return true;
            }
        }
        return false;
    }

    protected function uniqueKey(string $category, string $text): string
    {
        $prefix = Str::of($category)->slug('_')->upper()->limit(10, '');
        $body = Str::of($text)->slug('_')->upper()->limit(28, '');
        $key = trim($prefix.'_'.$body, '_');
        $base = $key;
        $n = 2;
        while (AssessmentQuestion::where('question_key', $key)->exists()) {
            $key = $base.'_'.$n++;
        }
        return $key;
    }
}
