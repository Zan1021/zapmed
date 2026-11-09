<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ParsesQuestionBankSource;
use App\Models\AssessmentCategory;
use App\Models\AssessmentCategoryQuestion;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionVersion;
use Illuminate\Console\Command;

/**
 * Import the clinical assessment question set from the MARKDOWN source
 * (resources/question-bank/health-assessments.md by default).
 *
 * This is now the PRIMARY, human-editable source of the question bank. The .md is a plain-text
 * rendering of Craig's Word doc that anyone (incl. Dr Anke) can read and correct without hidden
 * Word formatting mangling the structure. It is parsed through the EXACT same grammar as the legacy
 * docx importer (see ParsesQuestionBankSource) so behaviour is identical — only the file format
 * changed. Fix the questions in the .md, re-run this, review + publish per category in admin.
 *
 * Grammar (per section `##`):
 *   SELECT: <q>?            + [n] options  → single-choice
 *   <q>?                    + [n] options  → single-choice
 *   <q>?                    + bare lines   → multi-choice (pick many)
 *   [IF YES] <q>                           → free-text follow-up shown when the prior Q = Yes
 *
 * Everything imported as DRAFT, scope=category. Idempotent: --fresh wipes prior md-import drafts;
 * --purge-all wipes the ENTIRE bank for a clean official-set rebuild.
 *
 * Usage: php artisan questionbank:import-md [path] [--fresh] [--purge-all]
 */
class ImportQuestionBankFromMarkdown extends Command
{
    use ParsesQuestionBankSource;

    protected $signature = 'questionbank:import-md
        {path? : Path to the markdown source (default: resources/question-bank/health-assessments.md)}
        {--fresh : Wipe prior md-import drafts first}
        {--purge-all : Wipe the ENTIRE bank first (official-set rebuild)}
        {--only= : Re-import ONLY this category slug (wipes just that category first), leaving the rest untouched}';

    protected $description = 'Import the clinical assessment question set from the markdown source';

    public function handle(): int
    {
        $path = $this->argument('path')
            ?: resource_path('question-bank/health-assessments.md');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $lines = $this->extractLines($path);
        if (empty($lines)) {
            $this->error('No content parsed from the markdown file.');
            return self::FAILURE;
        }

        if ($this->option('purge-all')) {
            $this->purgeEntireBank();
        } elseif ($this->option('fresh')) {
            $this->wipeMdImported();
        }

        $parsed = $this->parseSource($lines);

        // --only=<slug>: surgically rebuild a SINGLE category, leaving the rest of the bank untouched.
        if ($only = $this->option('only')) {
            $parsed = array_values(array_filter(
                $parsed,
                fn ($cat) => $this->slugForCategory($cat['name']) === $only
            ));

            if (empty($parsed)) {
                $this->error("No category in the source maps to slug '{$only}'.");
                return self::FAILURE;
            }

            $this->wipeCategory($only);
        }

        $counts = $this->persistParsed($parsed);

        $this->newLine();
        $this->info('Imported from markdown (all DRAFT):');
        $this->line("  source    : {$path}");
        $this->line("  categories: {$counts['categories']}");
        $this->line("  questions : {$counts['questions']}");
        $this->line("  links     : {$counts['links']}");
        $this->newLine();
        $this->comment('All Draft. Review + publish per category in admin (or questionbank:approve-all). CORE block is a clinical decision — not auto-set.');

        return self::SUCCESS;
    }

    /**
     * Turn markdown into the {text,is_h1} line shape the shared parser expects.
     *   "## Title"   → a section boundary (is_h1 = true, '##'/'#' stripped)
     *   "# Title"    → document title, skipped
     *   "_italics_"  → the generated intro notes, skipped
     * All other non-blank lines pass through verbatim (the clinical grammar lives in the trait).
     *
     * @return array<int,array{text:string,is_h1:bool}>
     */
    private function extractLines(string $path): array
    {
        $raw = preg_split('/\R/', (string) file_get_contents($path));
        $out = [];

        foreach ($raw as $line) {
            $t = trim($line);
            if ($t === '') {
                continue;
            }
            // document H1 title — skip
            if (preg_match('/^#\s+/', $t)) {
                continue;
            }
            // section heading — H1 boundary for the parser
            if (preg_match('/^##\s+(.+)$/', $t, $m)) {
                $out[] = ['text' => trim($m[1]), 'is_h1' => true];
                continue;
            }
            // skip the generated italic intro/help notes (lines wrapped entirely in underscores)
            if (preg_match('/^_.*_$/', $t)) {
                continue;
            }
            $out[] = ['text' => $t, 'is_h1' => false];
        }

        return $out;
    }

    private function purgeEntireBank(): void
    {
        $qCount = AssessmentQuestion::count();
        $cCount = AssessmentCategory::count();

        AssessmentCategoryQuestion::query()->delete();
        AssessmentQuestionVersion::query()->delete();
        \App\Models\Assessment::query()->update(['category_id' => null]);
        AssessmentQuestion::query()->update(['current_version_id' => null]);
        AssessmentQuestion::query()->delete();
        AssessmentCategory::query()->delete();

        $this->warn("Purged the entire bank: {$qCount} questions, {$cCount} categories (official-set rebuild).");
    }

    /**
     * Wipe a SINGLE category's questions/versions/links so --only can rebuild it cleanly.
     * The category ROW is deleted too (persistParsed re-creates it as a fresh Draft); any assessments
     * that referenced it are detached (category_id → null) to respect the FK, same as purge-all.
     */
    private function wipeCategory(string $slug): void
    {
        $category = AssessmentCategory::where('slug', $slug)->first();
        if (! $category) {
            $this->warn("Category '{$slug}' did not exist yet — nothing to wipe.");
            return;
        }

        $questionIds = AssessmentCategoryQuestion::where('category_id', $category->id)
            ->pluck('question_id')->unique();

        AssessmentCategoryQuestion::where('category_id', $category->id)->delete();
        AssessmentQuestionVersion::whereIn('question_id', $questionIds)->delete();
        \App\Models\Assessment::where('category_id', $category->id)->update(['category_id' => null]);
        AssessmentQuestion::whereIn('id', $questionIds)->update(['current_version_id' => null]);
        AssessmentQuestion::whereIn('id', $questionIds)->delete();
        $category->delete();

        $this->warn("Wiped category '{$slug}': {$questionIds->count()} questions (surgical re-import).");
    }

    private function wipeMdImported(): void
    {
        $ids = AssessmentQuestionVersion::where('reason', 'md-import')
            ->whereNull('published_by')
            ->pluck('question_id')->unique();

        AssessmentCategoryQuestion::whereIn('question_id', $ids)->delete();
        AssessmentQuestionVersion::whereIn('question_id', $ids)->delete();
        AssessmentQuestion::whereIn('id', $ids)->delete();
        AssessmentCategory::where('status', 'draft')->whereDoesntHave('questionLinks')->delete();

        $this->warn('Wiped '.$ids->count().' prior md-import draft questions.');
    }
}
