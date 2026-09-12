<?php

namespace App\Console\Commands;

use App\Services\Contro\ControReconciler;
use Illuminate\Console\Command;

/**
 * Reconcile staged Contro rows into canonical tables (blueprint §3). Idempotent, dependency-ordered,
 * quarantines anomalies, ZERO side-effects. Reads from upstream_ingested_rows (populated by contro:pull).
 *
 *   php artisan contro:reconcile
 */
class ControReconcile extends Command
{
    protected $signature = 'contro:reconcile';

    protected $description = 'Reconcile staged Contro data into canonical tables (idempotent, no side-effects).';

    public function handle(): int
    {
        $counts = (new ControReconciler())->reconcileAll();

        foreach ($counts as $entity => $n) {
            $this->line(sprintf('%-22s reconciled=%d', $entity, $n));
        }

        $quarantined = \App\Models\ImportQuarantine::where('status', 'open')->count();
        if ($quarantined > 0) {
            $this->warn("{$quarantined} row(s) quarantined for review (import_quarantine).");
        }

        return self::SUCCESS;
    }
}
