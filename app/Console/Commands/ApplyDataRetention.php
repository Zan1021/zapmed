<?php

namespace App\Console\Commands;

use App\Services\RetentionService;
use Illuminate\Console\Command;

class ApplyDataRetention extends Command
{
    protected $signature = 'data:apply-retention {--force : Actually dispose (default is dry-run)}';
    protected $description = 'Apply the per-category data-retention policy (POPIA). Dry-run unless --force.';

    public function handle(RetentionService $service): int
    {
        $dryRun = !$this->option('force');

        $this->info($dryRun
            ? 'Retention DRY-RUN (no data changed) — pass --force to dispose.'
            : 'Applying retention policy (disposing past-retention records)...');

        $results = $service->apply($dryRun);

        foreach ($results as $category => $count) {
            $line = "  {$category}: {$count} record(s) past retention";
            $count > 0 ? $this->warn($line) : $this->line($line);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
