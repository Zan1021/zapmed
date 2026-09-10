<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enforces the per-category data-retention policy (config/retention.php).
 * POPIA March-2026 health-data regs require documented retention + secure
 * disposal per data category. This computes what is past retention and, in
 * enforce mode, disposes of it (anonymise clinical/financial, delete marketing).
 *
 * Run via `php artisan data:apply-retention` (dry-run by default).
 */
class RetentionService
{
    /**
     * @return array<string,int> category => count past retention (or disposed)
     */
    public function apply(bool $dryRun = true): array
    {
        $results = [];

        foreach (config('retention', []) as $category => $policy) {
            $cutoff = Carbon::now()->subDays((int) $policy['retention_days']);
            $anchor = $policy['anchor'] ?? 'created_at';

            $query = DB::table($category)->whereNotNull($anchor)->where($anchor, '<', $cutoff);

            $count = (clone $query)->count();
            $results[$category] = $count;

            if ($dryRun || $count === 0) {
                continue;
            }

            if (($policy['disposal'] ?? 'anonymise') === 'delete') {
                $query->delete();
            } else {
                $this->anonymise($category, (clone $query));
            }

            Log::channel('clinical_audit')->info("retention_applied: {$category}", [
                'category' => $category,
                'disposal' => $policy['disposal'] ?? 'anonymise',
                'affected' => $count,
                'cutoff' => $cutoff->toDateString(),
                'basis' => $policy['basis'] ?? null,
            ]);
        }

        return $results;
    }

    /**
     * Anonymise a clinical/financial record: null the patient link so the
     * record survives for legal retention but is no longer personally
     * identifiable. Only touches columns that exist on the table.
     */
    private function anonymise(string $table, $query): void
    {
        $updates = [];
        if (\Schema::hasColumn($table, 'patient_id')) {
            $updates['patient_id'] = null;
        }
        if (\Schema::hasColumn($table, 'user_id')) {
            $updates['user_id'] = null;
        }

        if (!empty($updates)) {
            $query->update($updates);
        }
    }
}
