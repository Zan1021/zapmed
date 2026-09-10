<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Zapmed\SparCore\Services\SparDuplicateMerger;

/**
 * National identity, Phase 4 — collapse cross-store duplicate patients into one
 * national record. Automated, with a safety valve: only unambiguous matches
 * (same profile + dependent, phones agree/one-blank) are merged; phone conflicts
 * are skipped and left flagged for review.
 *
 *   php artisan spar:merge-duplicates --dry-run
 *   php artisan spar:merge-duplicates
 */
class SparMergeDuplicates extends Command
{
    protected $signature = 'spar:merge-duplicates {--dry-run : Report what would merge, change nothing}';
    protected $description = 'Merge unambiguous cross-store duplicate SPAR patients into one national record';

    public function handle(SparDuplicateMerger $merger): int
    {
        $dry = (bool) $this->option('dry-run');
        $stats = $merger->mergeAll($dry);

        $this->info(($dry ? '[dry-run] ' : '') . sprintf(
            'Duplicate groups: %d | merged %d loser(s) | skipped %d group(s).',
            $stats['groups'],
            $stats['merged'],
            $stats['skipped']
        ));

        foreach ($stats['skipped_reasons'] as $reason) {
            $this->warn('  skipped: ' . $reason);
        }

        return self::SUCCESS;
    }
}
