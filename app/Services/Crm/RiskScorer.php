<?php

namespace App\Services\Crm;

use App\Enums\RiskBand;
use App\Models\Appointment;
use App\Models\CrmLead;
use App\Models\CrmRiskScore;
use App\Models\Order;
use App\Models\Payment;

/**
 * Rules-based patient risk scorer (parity with Mark's crm risk score + the AI risk rubric, minus the
 * LLM). Deterministic: reads real signals, sums weighted points (config('crm.risk.weights')), clamps
 * to 0–100 and bands via RiskBand::fromScore(). AI scoring (Task 8) is an optional enhancement that
 * uses the SAME rubric and writes the same crm_risk_scores row with computed_by='ai'.
 *
 * Each firing rule becomes a "factor" {label, impact, weight} — the exact shape the AI layer emits —
 * so the UI renders both identically.
 */
class RiskScorer
{
    /**
     * Compute (and return) a fresh score for a lead, persisting it to crm_risk_scores. One row per
     * lead (updateOrCreate). Does not mutate the lead itself.
     */
    public function score(CrmLead $lead): CrmRiskScore
    {
        $weights = config('crm.risk.weights');
        $patientId = $lead->patient_id;
        $factors = [];
        $score = 0;

        // --- Failed payments (capped) ---------------------------------------------------------
        $failedPayments = Payment::where('patient_id', $patientId)->where('status', 'failed')->count();
        if ($failedPayments > 0) {
            $pts = min($failedPayments * $weights['payment_failed_each'], $weights['payment_failed_cap']);
            $score += $pts;
            $factors[] = $this->factor("{$failedPayments} failed payment(s)", 'negative', $pts);
        }

        // --- Order in a repeat-failure terminal-ish state -------------------------------------
        $hasThreeFailures = Order::where('patient_id', $patientId)
            ->where('status', 'ThreeRepeatFailures')->exists();
        if ($hasThreeFailures) {
            $score += $weights['three_repeat_failures'];
            $factors[] = $this->factor('Order hit three repeat payment failures', 'negative', $weights['three_repeat_failures']);
        }

        // --- Inactivity (60 supersedes 30) ----------------------------------------------------
        $daysInactive = $lead->last_activity_at ? (int) $lead->last_activity_at->diffInDays(now()) : null;
        if ($daysInactive !== null && $daysInactive >= 60) {
            $score += $weights['days_inactive_60'];
            $factors[] = $this->factor("Inactive for {$daysInactive} days", 'negative', $weights['days_inactive_60']);
        } elseif ($daysInactive !== null && $daysInactive >= 30) {
            $score += $weights['days_inactive_30'];
            $factors[] = $this->factor("Inactive for {$daysInactive} days", 'warning', $weights['days_inactive_30']);
        }

        // --- Stalled in the same non-terminal stage -------------------------------------------
        if (! $lead->current_stage->isTerminal() && $lead->stage_entered_at) {
            $daysInStage = (int) $lead->stage_entered_at->diffInDays(now());
            if ($daysInStage >= 14) {
                $score += $weights['stalled_stage_14'];
                $factors[] = $this->factor(
                    "Stalled {$daysInStage} days at stage '{$lead->current_stage->label()}'",
                    'warning',
                    $weights['stalled_stage_14']
                );
            }
        }

        // --- No-show consults (capped) --------------------------------------------------------
        $noShows = Appointment::where('patient_id', $patientId)->where('status', 'no_show')->count();
        if ($noShows > 0) {
            $pts = min($noShows * $weights['no_show_each'], $weights['no_show_cap']);
            $score += $pts;
            $factors[] = $this->factor("{$noShows} no-show consult(s)", 'negative', $pts);
        }

        // --- Active flags ---------------------------------------------------------------------
        $activeFlagKinds = $lead->activeFlags()->pluck('kind')->map(fn ($k) => $k->value ?? $k)->all();
        foreach ([
            'at_risk' => 'flag_at_risk',
            'complaint_open' => 'flag_complaint_open',
            'fraud_suspected' => 'flag_fraud_suspected',
        ] as $flag => $weightKey) {
            if (in_array($flag, $activeFlagKinds, true)) {
                $score += $weights[$weightKey];
                $factors[] = $this->factor("Flag: {$flag}", 'negative', $weights[$weightKey]);
            }
        }

        // --- Already churned / dropped off ----------------------------------------------------
        if ($lead->current_stage->isTerminal()) {
            $score += $weights['churned'];
            $factors[] = $this->factor("Lifecycle stage is terminal ('{$lead->current_stage->label()}')", 'negative', $weights['churned']);
        }

        // --- A healthy patient earns a positive note ------------------------------------------
        if ($score === 0) {
            $factors[] = $this->factor('No friction signals detected', 'positive', 0);
        }

        $score = max(0, min(100, $score));
        $band = RiskBand::fromScore($score);

        return CrmRiskScore::updateOrCreate(
            ['crm_lead_id' => $lead->id],
            [
                'score' => $score,
                'band' => $band->value,
                'factors' => $factors,
                'reasoning' => $this->reasoning($band, $factors),
                'computed_by' => 'rules',
                'computed_at' => now(),
            ]
        );
    }

    /** @return array{label:string,impact:string,weight:int} */
    private function factor(string $label, string $impact, int $weight): array
    {
        // Normalise the raw points to the rubric's 1–10 relative weight for display parity.
        $display = $weight <= 0 ? 0 : max(1, min(10, (int) round($weight / 5)));

        return ['label' => $label, 'impact' => $impact, 'weight' => $display];
    }

    private function reasoning(RiskBand $band, array $factors): string
    {
        $negatives = array_filter($factors, fn ($f) => in_array($f['impact'], ['negative', 'warning'], true));

        return match ($band) {
            RiskBand::Low => 'Actively engaged with no material friction signals.',
            RiskBand::Medium => 'Minor friction signals present; a routine check-in is suggested.',
            RiskBand::High => 'Serious friction detected (' . count($negatives) . ' factor(s)); proactive outreach needed.',
            RiskBand::Critical => 'Imminent loss risk (' . count($negatives) . ' factor(s)); ops should intervene now.',
        };
    }
}
