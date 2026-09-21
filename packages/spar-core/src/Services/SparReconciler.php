<?php

namespace Zapmed\SparCore\Services;

use Illuminate\Support\Facades\Log;
use Zapmed\SparCore\Enums\SparActionableStatus;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparPrescriptionJourney;

/**
 * SPAR Close-the-Loop, Wave B5 — the import auto-resolve reconciler (FR-B5).
 *
 * After an import records real dispense FACTS from the SPAR file, this service
 * reconciles those facts against the in-app close-the-loop state on the
 * affected journey and its dispense records. It is the "reality check": the
 * SPAR till is what actually happened; the in-app engine only ever inferred.
 *
 * The conflict rule (decision D2) is config-driven so both branches ship and a
 * change is a config flip, not a rewrite (config `spar.reconciler.conflict_rule`):
 *
 *   'overlay'      (DEFAULT, D2 option A) File = historical truth, in-app =
 *                  overlay. A confirmed dispense RESOLVES the open loop it
 *                  satisfies. In-app resolutions the file hasn't confirmed are
 *                  LEFT in place (absence is not contradiction — additive truth).
 *
 *   'system_wins'  (D2 option B) Only file facts drive state. Confirmed
 *                  dispenses resolve; AND any in-app resolution NOT backed by a
 *                  matching dispense fact is reopened so the app matches SPAR.
 *
 * Package-pure: operates only on package models via the ResolvesActionable
 * trait (reopenActionable / resolveActionable). Names no host class.
 */
class SparReconciler
{
    public const RULE_OVERLAY = 'overlay';
    public const RULE_SYSTEM_WINS = 'system_wins';

    /**
     * Reconcile a single journey against its recorded dispense facts.
     *
     * @return array{resolved:int,reopened:int,rule:string,skipped:bool}
     */
    public function reconcileJourney(SparPrescriptionJourney $journey): array
    {
        $stats = ['resolved' => 0, 'reopened' => 0, 'rule' => $this->rule(), 'skipped' => false];

        if (! $this->enabled()) {
            $stats['skipped'] = true;

            return $stats;
        }

        $rule = $this->rule();

        // A dispense row with status 'collected' (or 'delivered') and a
        // completed_at is a CONFIRMED fact from the file — the loop for that
        // fill is closed in reality. Resolve any still-open such record.
        $dispenses = $journey->dispenseRecords()->get();

        foreach ($dispenses as $dispense) {
            if ($this->isConfirmedFact($dispense)) {
                if (! $dispense->actionStatus()->isResolved()) {
                    $dispense->resolveActionable();
                    $stats['resolved']++;
                }

                continue;
            }

            // Not a confirmed fact. Under system_wins, an in-app resolution that
            // the file does not back must be reopened so the app view matches
            // the SPAR system. Under overlay, we leave the in-app state alone.
            if ($rule === self::RULE_SYSTEM_WINS && $dispense->actionStatus()->isResolved()) {
                $dispense->reopenActionable();
                $stats['reopened']++;
            }
        }

        // Journey-level loop: once the file shows the course is fully dispensed
        // (or moved to renewal_due), the journey's own actionable loop is closed.
        // Under overlay a not-yet-confirmed in-app resolution stays; under
        // system_wins a resolution unsupported by the file's progress is reopened.
        $courseComplete = $journey->status === 'renewal_due'
            || ($journey->total_dispenses > 0 && $journey->dispenses_completed >= $journey->total_dispenses);

        if ($courseComplete) {
            if (! $journey->actionStatus()->isResolved()) {
                $journey->resolveActionable();
                $stats['resolved']++;
            }
        } elseif ($rule === self::RULE_SYSTEM_WINS && $journey->actionStatus()->isResolved()) {
            $journey->reopenActionable();
            $stats['reopened']++;
        }

        $this->audit($journey, $stats);

        return $stats;
    }

    /**
     * A dispense record is a confirmed real-world fact when the file marks it
     * collected/delivered with a completion timestamp.
     */
    private function isConfirmedFact(SparDispenseRecord $dispense): bool
    {
        return in_array($dispense->status, ['collected', 'delivered'], true)
            && $dispense->completed_at !== null;
    }

    private function enabled(): bool
    {
        return (bool) config('spar.reconciler.enabled', true);
    }

    private function rule(): string
    {
        $rule = (string) config('spar.reconciler.conflict_rule', self::RULE_OVERLAY);

        return in_array($rule, [self::RULE_OVERLAY, self::RULE_SYSTEM_WINS], true)
            ? $rule
            : self::RULE_OVERLAY;
    }

    private function audit(SparPrescriptionJourney $journey, array $stats): void
    {
        if ($stats['resolved'] === 0 && $stats['reopened'] === 0) {
            return; // nothing changed — don't spam the audit log
        }

        $channel = config('logging.channels.spar_audit') ? 'spar_audit' : 'stack';

        Log::channel($channel)->info('reconciler_ran: Journey #' . $journey->getKey(), [
            'action' => 'reconciler_ran',
            'subject_type' => get_class($journey),
            'subject_id' => $journey->getKey(),
            'spar_patient_id' => $journey->spar_patient_id,
            'rule' => $stats['rule'],
            'resolved' => $stats['resolved'],
            'reopened' => $stats['reopened'],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
