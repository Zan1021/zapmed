<?php

namespace App\Console\Commands;

use App\Services\Contro\ControParityReport;
use Illuminate\Console\Command;

/**
 * Print the Contro import parity report: staged vs reconciled vs quarantined per entity, plus the
 * open-quarantine summary. Read-only. Blueprint §4 step 2.
 *
 *   php artisan contro:parity
 */
class ControParity extends Command
{
    protected $signature = 'contro:parity';

    protected $description = 'Show Contro import parity (staged vs reconciled vs quarantined per entity).';

    public function handle(): int
    {
        $report = new ControParityReport();
        $counts = $report->build();

        $rows = [];
        foreach ($counts as $entity => $c) {
            $rows[] = [$entity, $c['staged'], $c['reconciled'], $c['quarantined'], $c['unaccounted']];
        }
        $this->table(['entity', 'staged', 'reconciled', 'quarantined', 'unaccounted'], $rows);

        $summary = $report->quarantineSummary();
        if ($summary !== []) {
            $this->newLine();
            $this->warn('Open quarantine:');
            $qRows = array_map(fn ($r) => [$r->entity_set, $r->reason, $r->total], $summary);
            $this->table(['entity', 'reason', 'count'], $qRows);
        }

        if ($report->isClean()) {
            $this->info('Parity clean: every staged row is reconciled or quarantined.');
            return self::SUCCESS;
        }

        $this->error('Parity has UNACCOUNTED rows — investigate before cutover.');
        return self::FAILURE;
    }
}
