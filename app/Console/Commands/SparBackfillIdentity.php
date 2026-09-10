<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Zapmed\SparCore\Models\SparPatient;

/**
 * Task 1.2 backfill — populate SPAR-owned patient identity from a linked ZapMed
 * `User`, for records that predate the SPAR-owned identity fields.
 *
 * Integrated-host only: it reads `App\Models\User`, which does not exist in the
 * standalone app. Never overwrites an already-populated SPAR field (so
 * pharmacist-captured / imported data wins), then recomputes onboarding status.
 *
 * Field map: User.first_name → first_name, User.last_name → last_name,
 * User.phone → cellphone, User.email → email.
 */
class SparBackfillIdentity extends Command
{
    protected $signature = 'spar:backfill-identity {--dry-run : Report what would change without writing}';

    protected $description = 'Backfill SPAR patient identity (name/cellphone/email) from linked ZapMed Users';

    public function handle(): int
    {
        if (config('spar.host_mode') !== 'integrated') {
            $this->warn('host_mode is not "integrated" — no ZapMed Users to backfill from. Nothing to do.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;
        $noUser = 0;

        // Only patients that have a linked user_id are candidates.
        $candidates = SparPatient::whereNotNull('user_id')->get();

        if ($candidates->isEmpty()) {
            $this->info('No SPAR patients with a linked user_id. Nothing to backfill.');

            return self::SUCCESS;
        }

        foreach ($candidates as $patient) {
            $user = User::find($patient->user_id);

            if (!$user) {
                $noUser++;
                continue;
            }

            $changes = $this->buildChanges($patient, $user);

            if (empty($changes)) {
                $skipped++;
                continue;
            }

            $this->line(sprintf(
                '  #%d (profile %s): %s',
                $patient->id,
                $patient->profile_code,
                collect($changes)->keys()->implode(', ')
            ));

            if (!$dryRun) {
                $patient->fill($changes);
                $patient->save();
                $patient->refreshOnboardingStatus();
            }

            $updated++;
        }

        $verb = $dryRun ? 'would update' : 'updated';
        $this->info("Backfill complete: {$verb} {$updated}, skipped {$skipped} (already populated), {$noUser} missing linked user.");

        if ($dryRun) {
            $this->comment('Dry run — no changes written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Build the set of fields to fill — only where the SPAR value is currently
     * empty AND the User carries a value. Never overwrites captured data.
     *
     * @return array<string, string>
     */
    private function buildChanges(SparPatient $patient, User $user): array
    {
        $map = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'cellphone' => $user->phone,
            'email' => $user->email,
        ];

        $changes = [];
        foreach ($map as $field => $sourceValue) {
            $sourceValue = is_string($sourceValue) ? trim($sourceValue) : $sourceValue;
            if (!empty($sourceValue) && empty($patient->{$field})) {
                $changes[$field] = $sourceValue;
            }
        }

        return $changes;
    }
}
