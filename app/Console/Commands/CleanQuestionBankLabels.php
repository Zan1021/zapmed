<?php

namespace App\Console\Commands;

use App\Models\AssessmentQuestionVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off cleanup for raw importer artifacts left in question-bank labels:
 *
 *  1. Leading conditional directives the docx importer failed to strip, e.g.
 *     "[IF SELECTED; THEN SELECT] ...", "[IF YES; SELECT] ...", "IF SELECTED: ...".
 *     These sit in front of an otherwise-good question — we strip the directive
 *     and keep the real question text.
 *
 *  2. Orphan free-text follow-ups whose label is literally "TEXT" (the importer
 *     saw a bare [TEXT] type directive and lost the real prompt). We relabel
 *     these to a neutral "Please provide further details" — matching their
 *     sibling follow-ups — rather than invent clinical wording (that is Dr Anke's
 *     call). The question TYPE/options are untouched.
 *
 * Edits the CURRENT published version text in place (no new version row) so the
 * live manifest keeps rendering — this is a label typo fix, not a clinical change.
 *
 * Idempotent: re-running finds nothing to do.
 */
class CleanQuestionBankLabels extends Command
{
    protected $signature = 'questionbank:clean-labels {--dry-run : Show what would change without writing}';

    protected $description = 'Strip raw importer directive prefixes and relabel orphan "TEXT" questions in the question bank';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $fixedPrefix = 0;
        $fixedText = 0;

        // Match a leading directive: optional "[", then IF ... up to the first "]" or ":",
        // with optional "; THEN SELECT" / "; SELECT". Case-insensitive.
        $directive = '/^\s*\[?\s*IF\b[^\]:]*(?:\]|:)\s*/i';

        $versions = AssessmentQuestionVersion::query()
            ->where(function ($q) {
                $q->where('text', 'like', '%IF SELECTED%')
                    ->orWhere('text', 'like', '%IF YES%')
                    ->orWhere('text', 'like', '%IF NO%')
                    ->orWhere('text', 'like', '%IF SURGICAL%')
                    ->orWhere('text', '=', 'TEXT')
                    ->orWhere('text', 'like', 'TEXT %')
                    ->orWhere('text', 'like', '% TEXT');
            })
            ->get();

        DB::transaction(function () use ($versions, $directive, $dry, &$fixedPrefix, &$fixedText) {
            foreach ($versions as $v) {
                $original = (string) $v->text;
                $new = $original;

                if (preg_match('/^\s*TEXT\s*$/', $original)) {
                    // Orphan free-text follow-up — give it a neutral, safe prompt.
                    $new = 'Please provide further details';
                    $fixedText++;
                } else {
                    $stripped = preg_replace($directive, '', $original);
                    if ($stripped !== null && $stripped !== $original) {
                        $new = trim($stripped);
                        $fixedPrefix++;
                    }
                }

                if ($new !== $original) {
                    $this->line(sprintf('  v%d  "%s"  ->  "%s"', $v->id, $original, $new));
                    if (! $dry) {
                        $v->text = $new;
                        $v->save();
                    }
                }
            }
        });

        $this->info(sprintf(
            '%sStripped %d directive prefix(es); relabelled %d orphan "TEXT" question(s).',
            $dry ? '[DRY RUN] ' : '',
            $fixedPrefix,
            $fixedText
        ));

        return self::SUCCESS;
    }
}
