<?php

namespace App\Console\Commands;

use App\Models\ComplianceDsar;
use App\Models\RetentionScheduleItem;
use App\Services\Compliance\ComplianceService;
use Illuminate\Console\Command;

/**
 * Compliance sweep (Task 9). Runs on the scheduler (see routes/console.php).
 *
 *  1. Flags DSARs past their 30-day SLA (still open + due_at in the past) — surfaced for ops.
 *  2. Runs DUE retention items (scheduled, not under legal hold, due_at ≤ now), capped by batch size.
 *     completeRetention() records + audits the decision; the concrete erase/redact is the owning
 *     module's responsibility.
 *
 *   php artisan compliance:scan
 */
class ComplianceScan extends Command
{
    protected $signature = 'compliance:scan';

    protected $description = 'Flag overdue DSARs and run due retention-schedule items (honours legal hold)';

    public function handle(ComplianceService $compliance): int
    {
        $overdue = ComplianceDsar::query()->open()->where('due_at', '<', now())->count();
        if ($overdue > 0) {
            $this->warn("{$overdue} DSAR(s) are past the 30-day SLA and still open.");
        }

        $batch = (int) config('compliance.retention_runner_batch_size', 100);
        $due = RetentionScheduleItem::query()->runnable()->limit($batch)->get();

        $done = 0;
        foreach ($due as $item) {
            try {
                $compliance->completeRetention($item);
                $done++;
            } catch (\Throwable $e) {
                $item->forceFill(['status' => \App\Enums\RetentionStatus::Failed->value, 'failure_reason' => $e->getMessage()])->save();
                $this->error("Retention item #{$item->id} failed: {$e->getMessage()}");
            }
        }

        $this->info("Compliance scan complete — {$overdue} overdue DSAR(s) flagged, {$done} retention item(s) processed.");

        return self::SUCCESS;
    }
}
