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
                // A widget hint already resolved to date/number/stop — keep it, don't override.
                $hinted = in_array($pendingQuestion['type'] ?? '', ['date', 'number', 'stop'], true);

                if (! $hinted) {
                    if (! empty($pendingQuestion['options'])) {
                        $pendingQuestion['type'] = $pendingQuestion['numbered'] ? 'single' : 'multi';
                    } else {
                        $pendingQuestion['type'] = 'text';
                    }
                }

                // Explicit widget hints in the question text reduce typing for the patient.
                $this->applyWidgetHint($pendingQuestion);

                unset($pendingQuestion['numbered']);
                $followups = $pendingQuestion['followups'] ?? [];
                unset($pendingQuestion['followups']);
                $current['questions'][] = $pendingQuestion;

                // Inline "Other - [Please specify]" style follow-ups become their own conditional
                // free-text questions, shown only when that exact option is picked.
                foreach ($followups as $fu) {
                    $current['questions'][] = [
                        'text' => $fu['text'],
                        'type' => 'text',
                        'options' => [],
                        'if_yes' => true,
                        'cond' => ['kind' => 'label', 'label' => $fu['label']],
                        'depends' => 'parent',
                    ];
                }
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
                    if (! in_array($current['questions'][$last]['type'] ?? 'text', ['date', 'number', 'select', 'stop'], true)) {
                        $current['questions'][$last]['type'] = 'text';
                        $current['questions'][$last]['options'] = [];
                    }
                }
                continue;
            }

            // conditional follow-up — [IF YES], [IF NO], [IF SELECTED], [IF OTHER], "IF SELECTED: …" etc.
            // "[THEN …]" is the same but hangs off the PREVIOUS choice question (even if that one was
            // itself conditional) — this is what lets a nested flow be written down:
            //     [IF NO]   Have you previously used a method?   Yes/No
            //     [THEN YES] Which method did you last use?      (only if the line above = Yes)
            // Becomes a PENDING question so any option lines that follow attach to it (making it a
            // single/multi choice). If no options follow, $flush resolves it to free-text — preserving
            // the old behaviour for bare "[IF YES] Please provide further details" follow-ups.
            // The tag is matched case-SENSITIVELY (the source writes it in capitals) so an ordinary
            // sentence starting "If you …" is never mistaken for a directive.
            if (preg_match('/^\[?(IF|THEN)\s+([A-Z][A-Z ;,]*?)\s*(?:\]|:)\s*[-:]?\s*(.+)$/', $text, $m)) {
                $flush();
                $body = $m[3];
                $isStop = false;
                if (preg_match('/^\[STOP\]\s*(.+)$/i', $body, $sm)) {
                    $isStop = true;
                    $body = $sm[1];
                }
                $cond = $this->parseCondition($m[2]);
                if ($m[1] === 'THEN') {
                    $cond['rel'] = 'prev';
                }
                $pendingQuestion = [
                    'text' => $this->clean(rtrim($body)),
                    'type' => $isStop ? 'stop' : 'text',
                    'options' => [],
                    'if_yes' => true, // "is conditional" (kept name for compatibility)
                    'cond' => $cond,
                    'numbered' => false,
                ];
                if (! $isStop) {
                    $this->applyWidgetHint($pendingQuestion); // honour "(calendar date)"/"(number)" on conditionals too
                }
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

            // numbered option  [1] Label   (an inline "- [follow-up]" tail becomes a conditional
            // follow-up question; a "- [JUMP …]" tail is routing metadata and is dropped)
            if (preg_match('/^\[(\d+)\]\s*(.+)$/', $text, $m)) {
                if ($pendingQuestion !== null) {
                    [$label, $followText] = $this->splitOptionTail($m[2]);
                    $label = $this->clean(rtrim($label, ': '));
                    $pendingQuestion['options'][] = $label;
                    $pendingQuestion['numbered'] = true;
                    if ($followText !== null) {
                        $pendingQuestion['followups'][] = ['label' => $label, 'text' => $this->clean($followText)];
                    } elseif (($jumpTo = $this->extractJumpTarget($m[2])) !== null) {
                        $pendingQuestion['jumps'][] = ['label' => $label, 'target' => $jumpTo];
                    }
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

            // SELECT CALENDAR: question (month/year date picker)
            if (preg_match('/^SELECT\s+CALENDAR:\s*(.+)$/i', $text, $m)) {
                $flush();
                $pendingQuestion = ['text' => $this->clean($m[1]), 'type' => 'date', 'options' => [], 'if_yes' => false, 'numbered' => false];
                continue;
            }

            // SELECT: question (single choice)
            if (preg_match('/^SELECT:\s*(.+)$/i', $text, $m)) {
                $flush();
                $pendingQuestion = ['text' => $this->clean($m[1]), 'type' => 'single', 'options' => [], 'if_yes' => false, 'numbered' => false];
                continue;
            }

            // a question line (ends with ?) — opens a new question.
            // Tolerate a trailing widget hint, e.g. "What is your current weight? (number)" or
            // "When diagnosed? (calendar date)", by ignoring the hint when testing for the '?'.
            if (str_ends_with(rtrim($this->stripWidgetHint($text)), '?')) {
                $flush();
                $pendingQuestion = ['text' => $this->clean($text), 'type' => 'single', 'options' => [], 'if_yes' => false, 'numbered' => false];
                continue;
            }

            // an un-tagged "Please provide further details…" line is a free-text follow-up to the
            // question above it (shown when that answer is something other than "No"/"None"),
            // never an answer option.
            if ($pendingQuestion !== null || ! empty($current['questions'])) {
                if (preg_match('/^please\s+(provide|describe|list|explain|specify)\b/i', $text)) {
                    $flush();
                    $pendingQuestion = [
                        'text' => $this->clean($text),
                        'type' => 'text',
                        'options' => [],
                        'if_yes' => true,
                        'cond' => ['kind' => 'selected'],
                        'numbered' => false,
                    ];
                    continue;
                }
            }

            // otherwise: a bare option line belonging to the pending (multi-choice) question.
            if ($pendingQuestion !== null) {
                [$label, $followText] = $this->splitOptionTail($text);
                $label = $this->clean($label);
                $pendingQuestion['options'][] = $label;
                if ($followText !== null) {
                    $pendingQuestion['followups'][] = ['label' => $label, 'text' => $this->clean($followText)];
                } elseif (($jumpTo = $this->extractJumpTarget($text)) !== null) {
                    $pendingQuestion['jumps'][] = ['label' => $label, 'target' => $jumpTo];
                }
            }
        }

        $flush();
        if ($current) {
            $categories[] = $current;
        }

        return array_map(fn (array $cat) => $this->applyJumps($cat), $categories);
    }

    /**
     * Split "Other - [Please specify]" into [label, followUpText]. Only genuine follow-up QUESTIONS
     * (starting with please/why/which/what/how/when/specify/provide/describe/list/explain) become a
     * follow-up; routing tails such as "[JUMP: …]" / "[Jump to Minoxidil]" and references to other
     * questions are stripped and ignored here (see extractJumpTarget).
     *
     * @return array{0:string,1:?string}
     */
    protected function splitOptionTail(string $raw): array
    {
        if (preg_match('/^(.*?)\s*-\s*\[?\s*((?:please|why|which|what|how|when|specify|provide|describe|list|explain)\b[^\]]*)\]?\s*$/i', $raw, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [trim(preg_replace('/\s*-\s*\[.*\]\s*$/', '', $raw)), null];
    }

    /**
     * The question TEXT an option routes to, from a tail like "- [JUMP: <q>]", "- [JUMP TO: <q>]",
     * "- [Jump to Minoxidil]" or a bare pointer such as "- [Have you sought treatment …?]".
     * Resolved later (applyJumps) against the questions that follow; unresolved pointers are ignored.
     */
    protected function extractJumpTarget(string $raw): ?string
    {
        if (preg_match('/-\s*\[\s*JUMP(?:\s+TO)?\s*:?\s*(.+?)\s*\]?\s*$/i', $raw, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/-\s*\[\s*(.+\?)\s*\]\s*$/', $raw, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Honour `[2] No - [JUMP: <later question>]`: every UNCONDITIONAL question between the asking
     * question and its jump target is only shown when the answer was NOT that option. (Questions that
     * are already conditional keep their own, tighter condition.)
     *
     * @param  array{name:string,questions:array}  $cat
     * @return array{name:string,questions:array}
     */
    protected function applyJumps(array $cat): array
    {
        $qs = $cat['questions'];
        $norm = fn (string $s): string => trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', strtolower($s))));

        foreach ($qs as $i => $q) {
            foreach ($q['jumps'] ?? [] as $jump) {
                $needle = $norm($jump['target']);
                if ($needle === '') {
                    continue;
                }
                $to = null;
                for ($k = $i + 1; $k < count($qs); $k++) {
                    if (str_contains($norm($qs[$k]['text']), $needle)) {
                        $to = $k;
                        break;
                    }
                }
                if ($to === null) {
                    continue; // unresolved pointer — ignore rather than guess
                }
                for ($k = $i + 1; $k < $to; $k++) {
                    if (empty($qs[$k]['if_yes'])) {
                        $qs[$k]['skip'][] = ['idx' => $i, 'label' => $jump['label']];
                    }
                }
            }
            unset($qs[$i]['jumps']);
        }

        $cat['questions'] = $qs;

        return $cat;
    }

    /**
     * Turn the text inside "[IF …]" into a condition descriptor.
     *   YES            → kind=yes      (parent answer === "Yes")
     *   NO / NO THEN   → kind=no       (parent answer === "No")
     *   SELECTED       → kind=selected (parent answered with anything other than a negative option)
     *   OTHER / OTHER SELECTED / SURGICAL REMOVAL → kind=option, hint=<words> (parent option containing the hint)
     *
     * @return array{kind:string,hint?:string}
     */
    protected function parseCondition(string $tag): array
    {
        $t = strtoupper(trim($tag));

        if (preg_match('/\bYES\b/', $t)) {
            return ['kind' => 'yes'];
        }
        if (preg_match('/^NO\b/', $t)) {
            return ['kind' => 'no'];
        }

        $hint = trim(preg_replace('/\b(SELECTED|SELECT|THEN|IF)\b|[;,]/', ' ', $t));
        $hint = trim(preg_replace('/\s+/', ' ', $hint));

        return $hint === '' ? ['kind' => 'selected'] : ['kind' => 'option', 'hint' => $hint];
    }

    /**
     * Resolve a condition descriptor to a branch_rules map against the parent question.
     *
     * @param  array<string,mixed>  $q
     * @param  array<int,string>    $parentOptions
     * @return array<string,array<int,string>>|null
     */
    protected function branchFor(array $q, ?string $parentKey, array $parentOptions): ?array
    {
        if (empty($q['if_yes']) || ! $parentKey) {
            return null;
        }

        $cond = $q['cond'] ?? ['kind' => 'yes'];

        switch ($cond['kind']) {
            case 'label':
                return [$parentKey => [$cond['label']]];
            case 'yes':
                return [$parentKey => ['Yes']];
            case 'no':
                return [$parentKey => ['No']];
            case 'option':
                foreach ($parentOptions as $opt) {
                    if (stripos($opt, $cond['hint']) !== false) {
                        return [$parentKey => [$opt]];
                    }
                }
                // no option carries that hint — fall back to "anything but a negative answer"
                // fall through
            default:
                return [$parentKey => [\App\Services\Assessments\AssessmentService::BRANCH_ANY_SELECTED]];
        }
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
                $lastKey = null;      // last NON-conditional question (what [IF YES]/[IF SELECTED] hang off)
                $lastOptions = [];
                $parentKey = null;    // question immediately before an inline option follow-up
                $parentOptions = [];
                $keysByIndex = [];    // question index within the category → question_key (for jump skips)

                foreach ($cat['questions'] as $idx => $q) {
                    $question = AssessmentQuestion::create([
                        'question_key' => $this->uniqueKey($cat['name'], $q['text']),
                        'scope' => 'category',
                        'clinical' => $this->looksClinical($q['text']),
                        'status' => 'draft',
                    ]);

                    $isFollowUp = ($q['depends'] ?? null) === 'parent';
                    $hangsOffPrevious = $isFollowUp || (($q['cond']['rel'] ?? null) === 'prev');
                    $branch = $hangsOffPrevious
                        ? $this->branchFor($q, $parentKey, $parentOptions)
                        : $this->branchFor($q, $lastKey, $lastOptions);

                    // `[2] No - [JUMP: …]` skips: show only when that parent was NOT answered with that option.
                    foreach ($q['skip'] ?? [] as $skip) {
                        if (isset($keysByIndex[$skip['idx']])) {
                            $branch[$keysByIndex[$skip['idx']]] = ['!'.$skip['label']];
                        }
                    }

                    $version = $question->versions()->create([
                        'version' => 1,
                        'text' => $q['text'],
                        'type' => $q['type'],
                        'options' => $this->mapOptions($q['options']),
                        'branch_rules' => $branch,
                        'required' => ! $q['if_yes'] && $q['type'] !== 'stop',
                        'reason' => 'md-import',
                    ]);
                    $question->update(['current_version_id' => $version->id]);
                    $createdQuestions++;
                    $keysByIndex[$idx] = $question->question_key;

                    AssessmentCategoryQuestion::create([
                        'category_id' => $category->id,
                        'question_id' => $question->id,
                        'position' => $position++,
                    ]);
                    $links++;

                    // "previous question" for [THEN …] and inline follow-ups = the last CHOICE question
                    // (free-text / date / stop leaves are never a sensible thing to branch on).
                    if (! $isFollowUp && ! empty($q['options'])) {
                        $parentKey = $question->question_key;
                        $parentOptions = $q['options'];
                    }
                    if (! $q['if_yes']) {
                        $lastKey = $question->question_key;
                        $lastOptions = $q['options'];
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

        $q['text'] = $this->stripWidgetHint($t);
    }

    /** Remove a trailing "(number)"/"(calendar date)"/"(date)"/"(month/year)" widget hint from a label. */
    protected function stripWidgetHint(string $t): string
    {
        return preg_replace('/\s*\((?:month\s*\/?\s*year|calendar\s*date|date|number)\)\s*$/i', '', $t);
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
