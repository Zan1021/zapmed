<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Zapmed\SparCore\Models\SparPatient;

/**
 * Phase 10.5 — POPIA data lifecycle: right-to-erasure + retention purge.
 *
 * POPIA requires personal information not be kept longer than necessary and
 * that data subjects can request erasure. This command:
 *   --erase=<patientId>  : anonymise ONE patient's PHI on request (irreversible).
 *   (default)            : retention sweep — anonymise opted-out patients whose
 *                          opt-out is older than the retention window.
 *
 * Anonymisation nulls the SPAR-owned identity (name/cellphone/email) and marks
 * the row erased, preserving non-PHI aggregates + the consent audit trail
 * (which records that erasure happened) for accountability.
 */
class SparDataRetention extends Command
{
    protected $signature = 'spar:data-retention
        {--erase= : Anonymise a single SparPatient by id (right-to-erasure)}
        {--days= : Retention window in days for opted-out patients (default from config)}
        {--dry-run : Report what would change without writing}';

    protected $description = '[POPIA] Erase-on-request + retention purge of SPAR patient PHI';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($id = $this->option('erase')) {
            return $this->eraseOne((int) $id, $dryRun);
        }

        return $this->retentionSweep($dryRun);
    }

    private function eraseOne(int $id, bool $dryRun): int
    {
        $patient = SparPatient::find($id);
        if (!$patient) {
            $this->error("Patient #{$id} not found.");

            return self::FAILURE;
        }

        $this->line("Erasing PHI for patient #{$id} ({$patient->profile_code}).");

        if (!$dryRun) {
            $this->anonymise($patient, 'erasure_request');
        }

        $this->info($dryRun ? 'Dry run — nothing written.' : 'Patient PHI anonymised.');

        return self::SUCCESS;
    }

    private function retentionSweep(bool $dryRun): int
    {
        $days = (int) ($this->option('days') ?? config('spar.retention.opted_out_days', 365));
        $cutoff = now()->subDays($days);

        // Opted-out patients whose opt-out is older than the window.
        $stale = SparPatient::where('consent_status', 'opted_out')
            ->whereNotNull('consent_revoked_at')
            ->where('consent_revoked_at', '<', $cutoff)
            ->get();

        if ($stale->isEmpty()) {
            $this->info("No opted-out patients older than {$days} days. Nothing to purge.");

            return self::SUCCESS;
        }

        foreach ($stale as $patient) {
            $this->line("  Purging PHI for patient #{$patient->id} (opted out {$patient->consent_revoked_at}).");
            if (!$dryRun) {
                $this->anonymise($patient, 'retention_purge');
            }
        }

        $this->info(($dryRun ? 'Would purge ' : 'Purged ') . $stale->count() . " patient record(s) (>{$days}d opted-out).");

        return self::SUCCESS;
    }

    /**
     * Irreversibly null the SPAR-owned PHI while keeping the row + audit trail.
     */
    private function anonymise(SparPatient $patient, string $reason): void
    {
        $patient->forceFill([
            'first_name' => null,
            'last_name' => null,
            'cellphone' => null,
            'email' => null,
            'metadata' => null,
        ])->save();

        Log::channel(config('logging.channels.spar_audit') ? 'spar_audit' : 'stack')->info(
            "phi_erased: patient #{$patient->id}",
            ['event' => 'phi_erased', 'spar_patient_id' => $patient->id, 'reason' => $reason, 'actor_id' => auth()->id()]
        );
    }
}
