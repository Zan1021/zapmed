<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Zapmed\SparCore\Models\SparPatient;

/**
 * National identity, Phase 1.4 — populate the blind-index hashes
 * (profile_code_hash, cellphone_hash) for existing patients from their
 * decrypted values, and (Phase 4.1) report duplicates that share a profile
 * across pharmacies.
 *
 *   php artisan spar:backfill-blind-index --dry-run
 *   php artisan spar:backfill-blind-index
 *
 * Idempotent — safe to re-run (also the way to re-hash after a key rotation).
 */
class SparBackfillBlindIndex extends Command
{
    protected $signature = 'spar:backfill-blind-index {--dry-run : Report only, write nothing}';
    protected $description = 'Populate blind-index hashes for existing SPAR patients + report cross-store duplicates';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $updated = 0;

        // profile_code / cellphone are encrypted — must decrypt in PHP (model
        // accessor) then re-hash. Chunk to keep memory flat on large datasets.
        SparPatient::query()->chunkById(200, function ($patients) use (&$updated, $dry) {
            foreach ($patients as $p) {
                $profileHash = SparPatient::blindIndex($p->profile_code, 'profile');
                $phoneHash = SparPatient::blindIndex($p->cellphone, 'phone');

                if ($p->profile_code_hash === $profileHash && $p->cellphone_hash === $phoneHash) {
                    continue;
                }

                if (!$dry) {
                    // saveQuietly-ish: set directly to avoid the saving() hook recomputing again.
                    $p->forceFill([
                        'profile_code_hash' => $profileHash,
                        'cellphone_hash' => $phoneHash,
                    ])->saveQuietly();
                }
                $updated++;
            }
        });

        $this->info(($dry ? '[dry-run] ' : '') . "Blind index: {$updated} patient(s) " . ($dry ? 'would be' : '') . ' updated.');

        $this->reportDuplicates();

        return self::SUCCESS;
    }

    /**
     * Report patients that share (profile_code_hash, dependent_code) across more
     * than one pharmacy — the cross-store duplicates the national-identity change
     * is meant to collapse (Phase 4). Report only; merge is a separate command.
     */
    private function reportDuplicates(): void
    {
        $dupes = SparPatient::query()
            ->selectRaw('profile_code_hash, dependent_code, COUNT(*) as c')
            ->whereNotNull('profile_code_hash')
            ->groupBy('profile_code_hash', 'dependent_code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($dupes->isEmpty()) {
            $this->info('No cross-store duplicate patients detected.');
            return;
        }

        $this->warn("Found {$dupes->count()} duplicate identity group(s) (same profile+dependent across rows):");
        $rows = [];
        foreach ($dupes as $d) {
            $members = SparPatient::where('profile_code_hash', $d->profile_code_hash)
                ->where('dependent_code', $d->dependent_code)
                ->get(['id', 'spar_pharmacy_id']);
            $rows[] = [
                substr($d->profile_code_hash, 0, 12) . '…',
                $d->dependent_code,
                $d->c,
                $members->pluck('id')->implode(', '),
                $members->pluck('spar_pharmacy_id')->implode(', '),
            ];
        }
        $this->table(['profile_hash', 'dep', 'count', 'patient ids', 'pharmacy ids'], $rows);
        $this->line('Run `spar:merge-duplicates` to collapse unambiguous matches (Phase 4).');
    }
}
