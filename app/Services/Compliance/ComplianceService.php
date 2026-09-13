<?php

namespace App\Services\Compliance;

use App\Enums\ConsentPurpose;
use App\Enums\ConsentState;
use App\Enums\DsarKind;
use App\Enums\DsarStatus;
use App\Enums\RetentionStatus;
use App\Models\ComplianceConsent;
use App\Models\ComplianceConsentRecord;
use App\Models\ComplianceDsar;
use App\Models\RetentionPolicy;
use App\Models\RetentionScheduleItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * POPIA compliance service — consent management, DSAR workflow, and retention scheduling.
 *
 * Every state change writes an immutable trail: consent changes append a compliance_consent_record;
 * DSAR/retention transitions append a crm_audit_events row via AuditTrail. Guarded status machines
 * prevent illegal transitions (e.g. completing a rejected DSAR, erasing under legal hold).
 */
class ComplianceService
{
    public function __construct(private readonly AuditTrail $audit)
    {
    }

    private function policyVersion(): string
    {
        return (string) config('compliance.policy_version', 'v1.0');
    }

    // ---- consent --------------------------------------------------------------------------------

    /**
     * Set a principal's consent for a purpose. Upserts the current-state row AND appends an immutable
     * consent_record. Idempotent per (principal, purpose) — always records the transition even if the
     * state is unchanged (POPIA wants the full audit).
     */
    public function setConsent(
        User $principal,
        ConsentPurpose $purpose,
        ConsentState $state,
        ?string $reason = null,
        ?string $evidenceRef = null,
        ?int $actorId = null,
    ): ComplianceConsent {
        return DB::transaction(function () use ($principal, $purpose, $state, $reason, $evidenceRef, $actorId) {
            $consent = ComplianceConsent::firstOrNew([
                'principal_id' => $principal->id,
                'purpose' => $purpose->value,
            ]);

            $consent->fill([
                'state' => $state->value,
                'policy_version' => $this->policyVersion(),
                'evidence_ref' => $evidenceRef ?? $consent->evidence_ref,
                'granted_at' => $state === ConsentState::Granted ? now() : $consent->granted_at,
                'withdrawn_at' => $state === ConsentState::Withdrawn ? now() : $consent->withdrawn_at,
            ])->save();

            ComplianceConsentRecord::create([
                'consent_id' => $consent->id,
                'principal_id' => $principal->id,
                'purpose' => $purpose->value,
                'new_state' => $state->value,
                'policy_version' => $this->policyVersion(),
                'reason' => $reason,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'actor_id' => $actorId ?? auth()->id() ?? $principal->id,
                'occurred_at' => now(),
            ]);

            $this->audit->record('consent', "consent.{$state->value}", ComplianceConsent::class, (string) $consent->id,
                after: ['purpose' => $purpose->value, 'state' => $state->value], actorId: $actorId);

            return $consent->fresh();
        });
    }

    /**
     * Ensure a new principal has the service-necessary purposes granted and the opt-in purposes marked
     * pending_reconsent. Idempotent — skips purposes that already have a row.
     */
    public function ensureDefaults(User $principal, ?int $actorId = null): void
    {
        $grantedDefaults = ConsentPurpose::defaultGranted();

        foreach (ConsentPurpose::cases() as $purpose) {
            $exists = ComplianceConsent::where('principal_id', $principal->id)->where('purpose', $purpose->value)->exists();
            if ($exists) {
                continue;
            }

            $state = in_array($purpose, $grantedDefaults, true) ? ConsentState::Granted : ConsentState::PendingReconsent;
            $this->setConsent($principal, $purpose, $state, 'default on onboarding', actorId: $actorId);
        }
    }

    /**
     * Bump every non-terminal consent to pending_reconsent — used when the policy version changes so
     * patients must re-agree. Returns the number of consents flipped.
     */
    public function requireReconsentForNewPolicy(): int
    {
        $flipped = 0;
        ComplianceConsent::query()
            ->whereIn('state', [ConsentState::Granted->value])
            ->where('policy_version', '!=', $this->policyVersion())
            ->each(function (ComplianceConsent $consent) use (&$flipped) {
                $principal = $consent->principal;
                if ($principal) {
                    $this->setConsent($principal, $consent->purpose, ConsentState::PendingReconsent, 'policy version bump');
                    $flipped++;
                }
            });

        return $flipped;
    }

    public function hasConsent(User $principal, ConsentPurpose $purpose): bool
    {
        return ComplianceConsent::where('principal_id', $principal->id)
            ->where('purpose', $purpose->value)
            ->where('state', ConsentState::Granted->value)
            ->exists();
    }

    // ---- DSAR -----------------------------------------------------------------------------------

    public function fileDsar(User $principal, DsarKind $kind, ?string $requestDetail = null, ?int $actorId = null): ComplianceDsar
    {
        $days = (int) config('compliance.dsar_complete_within_days', 30);

        $dsar = ComplianceDsar::create([
            'principal_id' => $principal->id,
            'kind' => $kind->value,
            'status' => DsarStatus::Received->value,
            'request_detail' => $requestDetail,
            'due_at' => now()->addDays($days),
            'received_at' => now(),
            'created_by' => $actorId ?? auth()->id(),
            'updated_by' => $actorId ?? auth()->id(),
        ]);

        $this->audit->record('dsar', 'dsar.received', ComplianceDsar::class, (string) $dsar->id,
            after: ['kind' => $kind->value, 'due_at' => $dsar->due_at->toIso8601String()], actorId: $actorId);

        return $dsar;
    }

    public function acknowledgeDsar(ComplianceDsar $dsar, ?int $actorId = null): ComplianceDsar
    {
        $this->transitionDsar($dsar, DsarStatus::Acknowledged, [DsarStatus::Received], $actorId, ['acknowledged_at' => now()]);

        return $dsar;
    }

    public function startDsar(ComplianceDsar $dsar, ?int $actorId = null): ComplianceDsar
    {
        $this->transitionDsar($dsar, DsarStatus::InProgress, [DsarStatus::Received, DsarStatus::Acknowledged], $actorId);

        return $dsar;
    }

    public function completeDsar(ComplianceDsar $dsar, ?int $actorId = null): ComplianceDsar
    {
        $this->transitionDsar($dsar, DsarStatus::Completed, [DsarStatus::Acknowledged, DsarStatus::InProgress], $actorId, ['completed_at' => now()]);

        return $dsar;
    }

    public function rejectDsar(ComplianceDsar $dsar, string $reason, ?int $actorId = null): ComplianceDsar
    {
        $this->transitionDsar($dsar, DsarStatus::Rejected, [DsarStatus::Received, DsarStatus::Acknowledged, DsarStatus::InProgress], $actorId, [
            'rejected_at' => now(),
            'rejected_reason' => $reason,
        ]);

        return $dsar;
    }

    /**
     * Fulfil an erasure DSAR by scheduling retention actions rather than deleting inline — the actual
     * erasure runs through the retention runner (which honours legal hold). Links the schedule row back
     * to the DSAR. Only valid for erasure-kind requests.
     */
    public function scheduleErasureForDsar(ComplianceDsar $dsar, string $dataClass, int $aggregateId, ?int $actorId = null): RetentionScheduleItem
    {
        if ($dsar->kind !== DsarKind::Erasure) {
            throw new RuntimeException("DSAR {$dsar->dsar_number} is a {$dsar->kind->value} request, not erasure.");
        }

        $item = $this->scheduleRetention($dataClass, $aggregateId, $dsar->principal_id, now(), $actorId);
        $dsar->forceFill(['retention_schedule_id' => $item->id])->save();

        return $item;
    }

    /**
     * @param  array<string,mixed>  $extra
     * @param  array<int,DsarStatus>  $allowedFrom
     */
    private function transitionDsar(ComplianceDsar $dsar, DsarStatus $to, array $allowedFrom, ?int $actorId, array $extra = []): void
    {
        if (! in_array($dsar->status, $allowedFrom, true)) {
            throw new RuntimeException("DSAR cannot move {$dsar->status->value} → {$to->value}.");
        }

        $from = $dsar->status;
        $dsar->fill(array_merge(['status' => $to->value, 'updated_by' => $actorId ?? auth()->id()], $extra))->save();

        $this->audit->record('dsar', "dsar.{$to->value}", ComplianceDsar::class, (string) $dsar->id,
            before: ['status' => $from->value], after: ['status' => $to->value], actorId: $actorId);
    }

    // ---- retention ------------------------------------------------------------------------------

    /**
     * Schedule a retention action for a source record. Idempotent per (data_class, aggregate_id).
     * due_at defaults to the policy's retain_days from now when not given.
     */
    public function scheduleRetention(string $dataClass, int $aggregateId, ?int $principalId = null, ?\DateTimeInterface $dueAt = null, ?int $actorId = null): RetentionScheduleItem
    {
        $policy = RetentionPolicy::where('data_class', $dataClass)->where('is_active', true)->first();
        if (! $policy) {
            throw new RuntimeException("No active retention policy for data class '{$dataClass}'.");
        }

        $due = $dueAt ?? now()->addDays($policy->retain_days);

        $item = RetentionScheduleItem::updateOrCreate(
            ['data_class' => $dataClass, 'aggregate_id' => $aggregateId],
            [
                'policy_id' => $policy->id,
                'principal_id' => $principalId,
                'due_at' => $due,
                'status' => RetentionStatus::Scheduled->value,
            ],
        );

        $this->audit->record('retention', 'retention.scheduled', RetentionScheduleItem::class, (string) $item->id,
            after: ['data_class' => $dataClass, 'aggregate_id' => $aggregateId, 'due_at' => $due instanceof \DateTimeInterface ? $due->format('c') : (string) $due], actorId: $actorId);

        return $item;
    }

    public function placeLegalHold(RetentionScheduleItem $item, string $reason, ?int $actorId = null): RetentionScheduleItem
    {
        $item->fill(['legal_hold' => true, 'legal_hold_reason' => $reason, 'status' => RetentionStatus::OnHold->value])->save();
        $this->audit->record('retention', 'retention.legal_hold_placed', RetentionScheduleItem::class, (string) $item->id, actorId: $actorId);

        return $item;
    }

    public function releaseLegalHold(RetentionScheduleItem $item, ?int $actorId = null): RetentionScheduleItem
    {
        $item->fill(['legal_hold' => false, 'legal_hold_reason' => null, 'status' => RetentionStatus::Scheduled->value])->save();
        $this->audit->record('retention', 'retention.legal_hold_released', RetentionScheduleItem::class, (string) $item->id, actorId: $actorId);

        return $item;
    }

    /**
     * Mark a due retention item completed. Refuses to run an item under legal hold. NOTE: this records
     * the decision + audits it; the concrete erase/redact of the source row is the owning module's job
     * (invoked by the retention runner) — this service does not delete arbitrary rows itself.
     */
    public function completeRetention(RetentionScheduleItem $item, ?int $actorId = null): RetentionScheduleItem
    {
        if ($item->legal_hold) {
            throw new RuntimeException('Retention item is under legal hold and cannot be executed.');
        }
        if ($item->status !== RetentionStatus::Scheduled) {
            throw new RuntimeException("Retention item is {$item->status->value}, not scheduled.");
        }

        $item->fill(['status' => RetentionStatus::Completed->value, 'completed_at' => now()])->save();
        $this->audit->record('retention', 'retention.completed', RetentionScheduleItem::class, (string) $item->id, actorId: $actorId);

        return $item;
    }
}
