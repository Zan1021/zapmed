<?php

namespace App\Console\Commands;

use App\Models\AssessmentCategory;
use App\Models\AssessmentQuestion;
use App\Services\Assessments\QuestionBankService;
use Illuminate\Console\Command;

/**
 * Bulk clinical sign-off of the question bank.
 *
 * Captain Zan's instruction (2026-11-05): Dr Anke has ALREADY signed off the full question set
 * offline, so her per-question review in the admin Review Queue is not required to move forward.
 * This command performs the exact same action the Review Queue's "Approve" button does — for every
 * pending (draft / in_review) question at once — then publishes each treatment category Live once it
 * has no remaining pending questions. Mirrors ReviewQueue::approve() + maybePublishCategory().
 *
 * It uses QuestionBankService::publish()/publishCategory() so every version is audit-stamped
 * (published_at / published_by / reason) just like a manual approval. Nothing is deleted; version
 * history is preserved. Idempotent — re-running does nothing once everything is Live.
 *
 * Usage:
 *   php artisan questionbank:approve-all                 # approve + publish everything, with confirm
 *   php artisan questionbank:approve-all --force         # skip the confirmation prompt
 *   php artisan questionbank:approve-all --treatment=acne-treatment   # one category only
 */
class ApproveAllQuestionBank extends Command
{
    protected $signature = 'questionbank:approve-all
        {--treatment= : Limit to a single category slug (default: all treatments)}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Mark all draft/in-review questions as clinically approved (Live) and publish their treatments';

    public function handle(QuestionBankService $bank): int
    {
        $treatment = $this->option('treatment');

        $pending = AssessmentQuestion::query()
            ->whereIn('status', ['draft', 'in_review'])
            ->when($treatment, fn ($q) => $q->whereHas(
                'categoryLinks.category',
                fn ($c) => $c->where('slug', $treatment)
            ))
            ->with('versions')
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Nothing to approve — no draft/in-review questions'
                .($treatment ? " for \"{$treatment}\"." : '.'));
            return self::SUCCESS;
        }

        $scopeLabel = $treatment ? "treatment \"{$treatment}\"" : 'ALL treatments';
        $this->warn("About to publish {$pending->count()} question(s) Live across {$scopeLabel}.");
        $this->line('This marks them as clinically approved (Dr Anke signed off offline) and will');
        $this->line('publish every category whose questions are all approved so patients see the flow.');

        if (! $this->option('force') && ! $this->confirm('Proceed?', true)) {
            $this->info('Aborted — nothing changed.');
            return self::SUCCESS;
        }

        $publishedQuestions = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($pending->count());
        $bar->start();

        foreach ($pending as $question) {
            $latest = $question->versions->sortByDesc('version')->first();
            if (! $latest) {
                $skipped++;
                $bar->advance();
                continue;
            }
            // Same call the Review Queue makes on Approve.
            $bank->publish($question, $latest, 'clinically approved (bulk sign-off — Dr Anke)', null);
            $publishedQuestions++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Publish any category that now has no pending questions. Mirrors maybePublishCategory().
        $categories = AssessmentCategory::query()
            ->when($treatment, fn ($q) => $q->where('slug', $treatment))
            ->get();

        $publishedCategories = 0;
        foreach ($categories as $cat) {
            if ($cat->status === 'live') {
                continue;
            }
            $hasPending = $cat->questionLinks()
                ->whereHas('question', fn ($qq) => $qq->whereIn('status', ['draft', 'in_review']))
                ->exists();
            if (! $hasPending) {
                $bank->publishCategory($cat, null);
                $publishedCategories++;
                $this->line("  <info>Live:</info> {$cat->name} ({$cat->slug})");
            }
        }

        $this->newLine();
        $this->info("Done. {$publishedQuestions} question(s) published Live"
            .($skipped ? ", {$skipped} skipped (no version)" : '')
            .", {$publishedCategories} treatment(s) now Live.");

        $liveCats = AssessmentCategory::where('status', 'live')->count();
        $totalCats = AssessmentCategory::count();
        $this->line("Treatments Live: {$liveCats} / {$totalCats}.");

        return self::SUCCESS;
    }
}
