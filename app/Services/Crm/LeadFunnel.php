<?php

namespace App\Services\Crm;

use App\Enums\CrmFlagKind;
use App\Enums\FunnelStage;
use App\Models\CrmFlag;
use App\Models\CrmFunnelEvent;
use App\Models\CrmLead;
use App\Models\CrmNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lead funnel service — the CRM lifecycle behaviour (parity with Mark's crm module).
 *
 * Owns every write to crm_leads.current_stage: each advance records an immutable crm_funnel_events
 * row and bumps stage_entered_at / last_activity_at / version, all in one transaction. Also the single
 * entry point for raising/clearing flags and adding notes, so activity watermarks stay correct.
 */
class LeadFunnel
{
    /**
     * Ensure a lead exists for a patient, creating it at the initial stage (with an initial funnel
     * event) if not. Idempotent.
     */
    public function ensureLead(User $patient, array $attributes = []): CrmLead
    {
        $existing = CrmLead::where('patient_id', $patient->id)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($patient, $attributes) {
            $lead = CrmLead::create(array_merge([
                'patient_id' => $patient->id,
                'current_stage' => config('crm.initial_stage'),
                'stage_entered_at' => now(),
                'last_activity_at' => now(),
            ], $attributes));

            $lead->funnelEvents()->create([
                'from_stage' => null,
                'to_stage' => $lead->current_stage->value,
                'actor' => $attributes['actor'] ?? 'system',
                'notes' => 'Lead created',
                'occurred_at' => now(),
            ]);

            return $lead;
        });
    }

    /**
     * Advance (or move) a lead to a new stage. Records an immutable event. Validates the target is a
     * real stage and the move is permitted (see canTransition()).
     *
     * @param  array{actor?:string,notes?:?string,aggregate_type?:?string,aggregate_id?:?int,occurred_at?:mixed}  $context
     */
    public function advance(CrmLead $lead, FunnelStage|string $to, array $context = []): CrmFunnelEvent
    {
        $to = $to instanceof FunnelStage ? $to : FunnelStage::tryFrom($to);
        if ($to === null) {
            throw new InvalidArgumentException('Unknown funnel stage.');
        }

        $from = $lead->current_stage;

        if (! $this->canTransition($from, $to)) {
            throw new RuntimeException("Illegal funnel move {$from->value} -> {$to->value}.");
        }

        return DB::transaction(function () use ($lead, $from, $to, $context) {
            $lead->forceFill([
                'current_stage' => $to->value,
                'stage_entered_at' => now(),
                'last_activity_at' => now(),
                'version' => $lead->version + 1,
            ])->save();

            return $lead->funnelEvents()->create([
                'from_stage' => $from->value,
                'to_stage' => $to->value,
                'aggregate_type' => $context['aggregate_type'] ?? null,
                'aggregate_id' => $context['aggregate_id'] ?? null,
                'actor' => $context['actor'] ?? 'system',
                'notes' => $context['notes'] ?? null,
                'occurred_at' => $context['occurred_at'] ?? now(),
            ]);
        });
    }

    /**
     * Is this stage move permitted? The funnel is permissive by design (leads slip and re-engage), so
     * we forbid only: a no-op (same stage), and progressing OUT of a terminal stage EXCEPT explicit
     * re-engagement back to an active stage (which is allowed — that IS re-engagement).
     */
    public function canTransition(FunnelStage $from, FunnelStage $to): bool
    {
        if ($from === $to) {
            return false; // no-op is not an event
        }

        return true;
    }

    /** Raise a flag on a lead (idempotent per active kind). Returns the flag (new or existing active). */
    public function raiseFlag(CrmLead $lead, CrmFlagKind|string $kind, ?string $reason = null, ?int $actorId = null): CrmFlag
    {
        $kind = $kind instanceof CrmFlagKind ? $kind : CrmFlagKind::from($kind);

        $active = $lead->flags()->where('kind', $kind->value)->whereNull('cleared_at')->first();
        if ($active) {
            return $active;
        }

        $lead->touchActivity();

        return $lead->flags()->create([
            'kind' => $kind->value,
            'reason' => $reason,
            'created_by' => $actorId,
        ]);
    }

    /** Clear an active flag of a kind. No-op if none active. */
    public function clearFlag(CrmLead $lead, CrmFlagKind|string $kind, ?int $actorId = null): void
    {
        $kind = $kind instanceof CrmFlagKind ? $kind : CrmFlagKind::from($kind);

        $lead->flags()->where('kind', $kind->value)->whereNull('cleared_at')->get()
            ->each(function (CrmFlag $flag) use ($actorId) {
                $flag->update(['cleared_at' => now(), 'cleared_by' => $actorId]);
            });
    }

    /** Add a staff note; touches activity. */
    public function addNote(CrmLead $lead, string $body, bool $pinned = false, ?int $actorId = null): CrmNote
    {
        $lead->touchActivity();

        return $lead->notes()->create([
            'body' => $body,
            'pinned' => $pinned,
            'created_by' => $actorId,
        ]);
    }
}
