<?php

namespace App\Services\Crm;

use App\Enums\FunnelEventKind;
use App\Enums\FunnelStage;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Support\Facades\Log;

/**
 * Journey → CRM capture bridge.
 *
 * Task 6 (CRM capture): the live patient journey (sign-up, intake, booking, consult, script, payment)
 * previously never touched the CRM funnel or the analytics event log — LiveOrderBridge only mirrored
 * Orders + finance. This service closes that gap: for each journey milestone it (a) ensures a CrmLead
 * exists, (b) advances the lead's funnel stage via LeadFunnel (immutable event), and (c) appends an
 * analytics funnel event via AnalyticsService.
 *
 * Design guarantees:
 *  - Idempotent & safe: ensureLead is idempotent; a stage advance that is a no-op or illegal is caught
 *    and ignored (the funnel is permissive but same-stage is a no-op). Backward moves are never forced.
 *  - Non-fatal: every capture is wrapped so a CRM hiccup can NEVER break the patient/doctor journey.
 *    (Mirrors the try/catch discipline already used around LiveOrderBridge calls.)
 *  - Additive: does not alter or remove any existing write or side-effect.
 */
class JourneyCapture
{
    public function __construct(
        private readonly LeadFunnel $funnel,
        private readonly AnalyticsService $analytics,
    ) {
    }

    public function signedUp(User $patient): void
    {
        $this->capture($patient, FunnelStage::SignedUp, FunnelEventKind::SignUpComplete);
    }

    public function intakeComplete(User $patient, ?string $serviceLine = null): void
    {
        $this->capture($patient, FunnelStage::IntakeComplete, FunnelEventKind::IntakeComplete, $serviceLine);
    }

    public function consultBooked(User $patient, ?string $serviceLine = null, ?int $appointmentId = null): void
    {
        $this->capture(
            $patient,
            FunnelStage::ConsultBooked,
            FunnelEventKind::ConsultBooked,
            $serviceLine,
            ['aggregate_type' => 'appointment', 'aggregate_id' => $appointmentId],
        );
    }

    public function consultComplete(User $patient, ?string $serviceLine = null, ?int $appointmentId = null): void
    {
        $this->capture(
            $patient,
            FunnelStage::ConsultComplete,
            FunnelEventKind::ConsultComplete,
            $serviceLine,
            ['aggregate_type' => 'appointment', 'aggregate_id' => $appointmentId],
        );
    }

    public function scriptIssued(User $patient, ?string $serviceLine = null, ?int $prescriptionId = null): void
    {
        // No dedicated analytics kind for "script issued"; advance the funnel and log a Custom event.
        $this->capture(
            $patient,
            FunnelStage::ScriptIssued,
            FunnelEventKind::Custom,
            $serviceLine,
            ['aggregate_type' => 'prescription', 'aggregate_id' => $prescriptionId],
            'script_issued',
        );
    }

    public function paid(User $patient, ?string $serviceLine = null): void
    {
        $this->capture($patient, FunnelStage::Paid, FunnelEventKind::FirstPayment, $serviceLine);
    }

    public function subscribed(User $patient, ?string $serviceLine = null): void
    {
        $this->capture($patient, FunnelStage::Subscribed, FunnelEventKind::Subscribed, $serviceLine);
    }

    /**
     * Core capture: ensure lead, advance stage (forward-only, non-fatal), append analytics event.
     *
     * @param  array{aggregate_type?:?string,aggregate_id?:?int}  $context
     */
    private function capture(
        User $patient,
        FunnelStage $stage,
        FunnelEventKind $eventKind,
        ?string $serviceLine = null,
        array $context = [],
        ?string $customLabel = null,
    ): void {
        try {
            $lead = $this->funnel->ensureLead($patient);

            // Only advance forwards along the happy path; never rewind a lead that is already further
            // along (e.g. a re-render or out-of-order hook). Same-stage is a no-op.
            if ($stage->order() > $lead->current_stage->order()) {
                $this->funnel->advance($lead, $stage, array_merge([
                    'actor' => 'system:journey',
                    'notes' => 'Auto-captured from live journey',
                ], $context));
            }

            $this->analytics->recordEvent(
                kind: $eventKind,
                principalId: $patient->id,
                serviceLine: $serviceLine,
                customLabel: $customLabel,
            );
        } catch (\Throwable $e) {
            // CRM capture must never break the journey.
            Log::warning('JourneyCapture failed', [
                'stage' => $stage->value,
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
