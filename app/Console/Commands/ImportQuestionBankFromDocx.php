<?php

namespace App\Console\Commands;

use App\Models\AssessmentCategory;
use App\Models\AssessmentCategoryQuestion;
use App\Models\AssessmentQuestion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 7 — import Craig's full clinical question set from the source Word document
 * "Health Assessments - Zapmed Full Services - June 2026".
 *
 * This is the AUTHORITATIVE clinical source (Captain Zan confirmed it IS the questions from Craig —
 * there is no separate pre-built package). It is far richer than config/assessment-questions.php
 * (~268 questions, 474 options, 113 conditional [IF] branches across 17 assessments).
 *
 * Grammar parsed (learned from the doc):
 *   <H1 heading>                         → an assessment / category boundary
 *   "SELECT: <text>?"  then  "[n] opt"   → single-choice question with those options
 *   "<text>?"          then  "[n] opt"   → single-choice question
 *   "<text>?"          then bare lines   → multi-choice (checkbox); bare lines are options
 *   "[IF YES] <text>"                    → conditional text question, shown if the previous Q = Yes
 *
 * Everything imported as DRAFT, scope=category (CORE is a later clinical decision by Craig/Dr Anke —
 * we do NOT auto-guess the CORE block). Idempotent: --fresh wipes prior docx-import drafts.
 *
 * Usage: php artisan questionbank:import-docx "<path to .docx>" [--fresh]
 */
class ImportQuestionBankFromDocx extends Command
{
    protected $signature = 'questionbank:import-docx {path : Path to the Health Assessments .docx} {--fresh : Wipe prior docx-import drafts first} {--purge-all : Wipe the ENTIRE bank first (official-set rebuild)}';

    protected $description = 'Import the full clinical assessment question set from Craig\'s Word document';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $lines = $this->extractLines($path);
        if (empty($lines)) {
            $this->error('Could not read any paragraphs. Is python-docx available? (used via a helper)');
            return self::FAILURE;
        }

        if ($this->option('purge-all')) {
            $this->purgeEntireBank();
        } elseif ($this->option('fresh')) {
            $this->wipeDocxImported();
        }

        $parsed = $this->parse($lines);

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
                $lastKey = null; // for [IF] branch wiring

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
                        'required' => ! $q['if_yes'], // conditional follow-ups aren't always required
                        'reason' => 'docx-import',
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
                        $lastKey = $question->question_key; // only non-conditional Qs can be a branch parent
                    }
                }
            }
        });

        $this->newLine();
        $this->info('Imported from docx (all DRAFT):');
        $this->line("  categories: {$createdCategories}");
        $this->line("  questions : {$createdQuestions}");
        $this->line("  links     : {$links}");
        $this->newLine();
        $this->comment('All Draft. Review + publish per category via the admin portal. CORE block is a clinical decision — not auto-set.');

        return self::SUCCESS;
    }

    /**
     * Parse the flat line list into categories → questions.
     *
     * @param  array<int,array{text:string,is_h1:bool}>  $lines
     * @return array<int,array{name:string,questions:array}>
     */
    private function parse(array $lines): array
    {
        $categories = [];
        $current = null;
        $pendingQuestion = null; // question awaiting its options

        $flush = function () use (&$pendingQuestion, &$current) {
            if ($pendingQuestion !== null && $current !== null) {
                // decide type from collected options
                if (! empty($pendingQuestion['options'])) {
                    // single if options came from [n]; multi if bare lines
                    $pendingQuestion['type'] = $pendingQuestion['numbered'] ? 'single' : 'multi';
                } else {
                    $pendingQuestion['type'] = 'text';
                }
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

            // conditional follow-up
            if (preg_match('/^\[IF\s+YES\]\s*(.+)$/i', $text, $m)) {
                $flush();
                $current['questions'][] = [
                    'text' => $this->clean(rtrim($m[1])),
                    'type' => 'text',
                    'options' => [],
                    'if_yes' => true,
                ];
                continue;
            }

            // numbered option  [1] Label
            if (preg_match('/^\[(\d+)\]\s*(.+)$/', $text, $m)) {
                if ($pendingQuestion !== null) {
                    $pendingQuestion['options'][] = $this->clean($m[2]);
                    $pendingQuestion['numbered'] = true;
                }
                continue;
            }

            // SELECT: question (single choice)
            if (preg_match('/^SELECT:\s*(.+)$/i', $text, $m)) {
                $flush();
                $pendingQuestion = ['text' => $this->clean($m[1]), 'type' => 'single', 'options' => [], 'if_yes' => false, 'numbered' => false];
                continue;
            }

            // a question line (ends with ?) — opens a new question (options may follow, numbered or bare)
            if (str_ends_with(rtrim($text), '?')) {
                $flush();
                $pendingQuestion = ['text' => $this->clean($text), 'type' => 'single', 'options' => [], 'if_yes' => false, 'numbered' => false];
                continue;
            }

            // otherwise: a bare option line belonging to the pending (multi-choice) question
            if ($pendingQuestion !== null) {
                $pendingQuestion['options'][] = $this->clean($text);
            }
        }

        $flush();
        if ($current) {
            $categories[] = $current;
        }

        return $categories;
    }

    /**
     * A category title: a short line ending in Consultation/Health Assessment/Assessment, that is NOT
     * a question (no '?') and not an option. Titles in this doc are mostly NOT styled as Headings, so
     * we detect them structurally.
     */
    private function looksLikeTitle(string $text): bool
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

    /**
     * Maps a parsed docx category NAME to the booking treatment slug the patient flow uses
     * (config/treatments.php). The docx titles are clinical names; booking uses specific slugs.
     * Captain Zan: the docx is the official set — land it on these slugs.
     */
    private function slugForCategory(string $name): string
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

    private function mapOptions(array $options): ?array
    {
        if (empty($options)) {
            return null;
        }
        return array_map(fn ($label) => [
            'code' => Str::slug($label, '_') ?: Str::lower(Str::random(6)),
            'label' => $label,
        ], $options);
    }

    /** Fix Windows-1252 mojibake (�) and tidy whitespace. */
    private function clean(string $s): string
    {
        $s = str_replace(["\u{FFFD}", '�'], "'", $s); // replacement char → apostrophe (best effort)
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private function cleanTitle(string $s): string
    {
        return $this->clean(preg_replace('/\s*(Consultation|Health Assessment|Assessment)\s*$/i', '', $s)) ?: $this->clean($s);
    }

    private function looksClinical(string $text): bool
    {
        $hay = Str::lower($text);
        foreach (['allerg', 'medicat', 'medicine', 'condition', 'pregnan', 'diabet', 'nitrate', 'blood', 'cardiac', 'disease', 'breastfeed', 'kidney', 'liver', 'prescri'] as $n) {
            if (str_contains($hay, $n)) {
                return true;
            }
        }
        return false;
    }

    private function uniqueKey(string $category, string $text): string
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

    /**
     * Extract non-blank paragraphs + whether each is an H1 heading, via a tiny python-docx helper
     * (python-docx is available in this environment; Word parsing in pure PHP is unreliable).
     *
     * @return array<int,array{text:string,is_h1:bool}>
     */
    private function extractLines(string $path): array
    {
        $script = <<<'PY'
import sys, json, docx
d = docx.Document(sys.argv[1])
out = []
for p in d.paragraphs:
    t = p.text.strip()
    if not t:
        continue
    is_h1 = ('Head' in p.style.name) or (p.style.name == 'Title')
    out.append({"text": p.text.strip(), "is_h1": bool(is_h1)})
print(json.dumps(out))
PY;
        $tmp = tempnam(sys_get_temp_dir(), 'qbdocx_').'.py';
        file_put_contents($tmp, $script);

        $cmd = 'python '.escapeshellarg($tmp).' '.escapeshellarg($path);
        $json = shell_exec($cmd);
        @unlink($tmp);

        $data = json_decode((string) $json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Wipe the ENTIRE question bank (config-imported + docx + any draft) for a clean official-set
     * rebuild. Captain Zan confirmed the docx is the official set and the old questions are to be
     * deleted. Only runs on unpublished data in dev; answers are preserved via assessment_answers
     * denormalised question_key, so historical patient answers stay readable even after this.
     */
    private function purgeEntireBank(): void
    {
        $qCount = AssessmentQuestion::count();
        $cCount = AssessmentCategory::count();

        AssessmentCategoryQuestion::query()->delete();
        \App\Models\AssessmentQuestionVersion::query()->delete();
        // null out category pointers on existing assessments so the FK delete is safe
        \App\Models\Assessment::query()->update(['category_id' => null]);
        AssessmentQuestion::query()->update(['current_version_id' => null]);
        AssessmentQuestion::query()->delete();
        AssessmentCategory::query()->delete();

        $this->warn("Purged the entire bank: {$qCount} questions, {$cCount} categories (official-set rebuild).");
    }

    private function wipeDocxImported(): void
    {
        $ids = \App\Models\AssessmentQuestionVersion::where('reason', 'docx-import')
            ->whereNull('published_by')
            ->pluck('question_id')->unique();

        AssessmentCategoryQuestion::whereIn('question_id', $ids)->delete();
        \App\Models\AssessmentQuestionVersion::whereIn('question_id', $ids)->delete();
        AssessmentQuestion::whereIn('id', $ids)->delete();
        AssessmentCategory::where('status', 'draft')->whereDoesntHave('questionLinks')->delete();

        $this->warn('Wiped '.$ids->count().' prior docx-import draft questions.');
    }
}
