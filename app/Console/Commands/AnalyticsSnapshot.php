<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsService;
use Illuminate\Console\Command;

/**
 * Compute + persist the daily KPI snapshot (Task 7, analytics module parity).
 *
 * Runs nightly (see routes/console.php). Idempotent — re-running for a date overwrites that day's
 * snapshot. Defaults to yesterday (a completed day) unless --date is given.
 *
 *   php artisan analytics:snapshot
 *   php artisan analytics:snapshot --date=2026-09-14
 */
class AnalyticsSnapshot extends Command
{
    protected $signature = 'analytics:snapshot {--date= : The day to snapshot (Y-m-d); defaults to yesterday}';

    protected $description = 'Compute and store the daily analytics KPI snapshot';

    public function handle(AnalyticsService $analytics): int
    {
        $date = $this->option('date')
            ? \Illuminate\Support\Carbon::parse($this->option('date'))
            : now()->subDay();

        $snapshot = $analytics->snapshot($date);

        $this->info("KPI snapshot stored for {$snapshot->snapshot_date->toDateString()} ({$snapshot->dimension}/{$snapshot->dimension_value}).");

        return self::SUCCESS;
    }
}
