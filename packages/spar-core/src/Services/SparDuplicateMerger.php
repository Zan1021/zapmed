<?php

namespace Zapmed\SparCore\Services;

use Zapmed\SparCore\Contracts\AuditLogger;
use Zapmed\SparCore\Models\SparConsent;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Illuminate\Support\Facades\DB;

/**
 * National identity, Phase 4 — collapse cross-store duplicate patients into ONE
 * national record. Duplicates arise from data imported before national de-dup
 * (or edge cases). Merges are AUTOMATED per Captain Zan, but with a SAFETY VALVE:
 * a group is only auto-merged when the match is UNAMBIGUOUS —
 *   same profile_code_hash + dependent_code, AND phones agree (or one side blank).
 * Any phone conflict is SKIPPED and left flagged for human review, so two
 * different people's medical histories are never silently fused.
 *
 * A merge repoints journeys, dispenses, orders and consents to the surviving
 * patient, then retires the losers (is_active=false + metadata.merged_into).
 * Audit-logged. Idempotent.
 */
class SparDuplicateMerger
{
    public function __construct(private ?AuditLogger $audit = null)
    {
        // AuditLogger is host-bound; resolve lazily so the merger is usable in
        // contexts where the container binding may be absent (fail-soft).
        $this->audit = $audit ?? (app()->bound(AuditLogger::class) ? app(AuditLogger::class) : null);
    }

    /**
     * Merge all unambiguous duplicate groups.
     *
     * @return array{groups:int, merged:int, skipped:int, skipped_reasons:array<int,string>}
     */
    public function mergeAll(bool $dryRun = false): array
    {
        $stats = ['groups' => 0, 'merged' => 0, 'skipped' => 0, 'skipped_reasons' => []];

        $groups = SparPatient::query()
            ->selectRaw('profile_code_hash, dependent_code, COUNT(*) as c')
            ->whereNotNull('profile_code_hash')
            ->where('is_active', true)
            ->groupBy('profile_code_hash', 'dependent_code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $g) {
            $stats['groups']++;
            $members = SparPatient::where('profile_code_hash', $g->profile_code_hash)
                ->where('dependent_code', $g->dependent_code)
                ->where('is_active', true)
                ->orderBy('id') // lowest id = survivor (oldest record)
                ->get();

            if (!$this->phonesAreConsistent($members)) {
                $stats['skipped']++;
                $stats['skipped_reasons'][] = "profile " . substr($g->profile_code_hash, 0, 10) . "…/{$g->dependent_code}: conflicting phones — flagged for review, not merged";
                if (!$dryRun) {
                    $members->each(fn (SparPatient $m) => $m->flagIdentityReview('Duplicate group has conflicting phones — manual merge required.'));
                }
                continue;
            }

            if (!$dryRun) {
                $this->mergeGroup($members);
            }
            $stats['merged'] += $members->count() - 1;
        }

        return $stats;
    }

    /**
     * Consistent if every non-empty phone hash in the group is identical.
     */
    private function phonesAreConsistent($members): bool
    {
        $hashes = $members->pluck('cellphone_hash')->filter()->unique();

        return $hashes->count() <= 1;
    }

    private function mergeGroup($members): void
    {
        $survivor = $members->first();
        $losers = $members->slice(1);

        DB::transaction(function () use ($survivor, $losers) {
            foreach ($losers as $loser) {
                SparPrescriptionJourney::where('spar_patient_id', $loser->id)->update(['spar_patient_id' => $survivor->id]);
                SparDispenseRecord::where('spar_patient_id', $loser->id)->update(['spar_patient_id' => $survivor->id]);
                SparOrder::where('spar_patient_id', $loser->id)->update(['spar_patient_id' => $survivor->id]);
                SparConsent::where('spar_patient_id', $loser->id)->update(['spar_patient_id' => $survivor->id]);

                // Fill any identity gap on the survivor from the loser (don't clobber).
                $this->backfillSurvivorIdentity($survivor, $loser);

                $meta = $loser->metadata ?? [];
                $meta['merged_into'] = $survivor->id;
                $meta['merged_at'] = now()->toIso8601String();
                $loser->forceFill([
                    'is_active' => false,
                    'metadata' => $meta,
                ])->saveQuietly();

                $this->audit?->log('patient_merged', 'SPAR duplicate patient merged', [
                    'survivor_id' => $survivor->id,
                    'merged_id' => $loser->id,
                    'dependent_code' => $survivor->dependent_code,
                ]);
            }

            $survivor->save();
        });
    }

    private function backfillSurvivorIdentity(SparPatient $survivor, SparPatient $loser): void
    {
        foreach (['first_name', 'last_name', 'cellphone', 'email', 'medical_aid_name', 'medical_aid_option'] as $field) {
            if (empty($survivor->{$field}) && !empty($loser->{$field})) {
                $survivor->{$field} = $loser->{$field};
            }
        }
    }
}
