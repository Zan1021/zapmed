<?php

namespace App\Services\Crm;

use App\Enums\NudgeStatus;
use App\Enums\RiskBand;
use App\Models\Alert;
use App\Models\CrmLead;
use App\Models\CrmNudge;
use App\Models\CrmRiskScore;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * CRM AI-assist service (Task 8, specs/contro-rebuild/08 §2.7) — optional intelligence layer.
 *
 * Uses the SAME OpenAI credentials as AiAssistantService (config('services.openai')). Mirrors that
 * class's client shape deliberately: isConfigured() gate, Http::withToken → /chat/completions with a
 * JSON response_format, try/catch that logs and falls back. EVERY feature degrades gracefully:
 *   - scoreRisk        → falls back to the deterministic RiskScorer (rules engine).
 *   - summarisePatient → falls back to a templated digest from Patient360 data.
 *   - suggestAlertAction → falls back to a rule-based next-action per alert definition.
 *   - draftNudge       → falls back to a templated outreach draft.
 *
 * ⚠️ The AI NEVER sends anything. draftNudge only ever writes a DRAFT crm_nudge; sending requires
 * explicit ops approval (approveNudge → markNudgeSent). This preserves the import/live-flow boundary.
 */
class CrmAiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct(
        private readonly RiskScorer $riskScorer,
        private readonly Patient360 $patient360,
    ) {
        $this->apiKey = (string) config('services.openai.api_key', '');
        $this->model = (string) config('services.openai.model', 'gpt-4o-mini');
    }

    /** AI is usable only when a key exists AND the CRM AI switch is on. */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && (bool) config('crm.ai.enabled', true);
    }

    // ---- 1. Risk scoring --------------------------------------------------------------------------

    /**
     * Produce a risk score for a lead. With AI available, ask the model to weigh the same signals and
     * emit the rubric's {label,impact,weight} factors; persist with computed_by='ai'. Without AI (or on
     * any failure), fall back to the deterministic RiskScorer (computed_by='rules'). Same table, same
     * shape — the UI renders both identically.
     */
    public function scoreRisk(CrmLead $lead): CrmRiskScore
    {
        if (! $this->isConfigured()) {
            return $this->riskScorer->score($lead);
        }

        try {
            $signals = $this->riskSignals($lead);

            $result = $this->chat(
                system: 'You are a retention-risk analyst for a telehealth CRM. Given patient signals, '
                    . 'return a JSON risk assessment. score is an integer 0-100 (higher = more likely to '
                    . 'churn or fail to convert). band is one of low|medium|high|critical. factors is an '
                    . 'array of {label, impact (positive|warning|negative), weight (1-10)}. reasoning is '
                    . 'one or two sentences. Respond ONLY with JSON: '
                    . '{"score":int,"band":str,"factors":[...],"reasoning":str}',
                user: 'Patient signals: ' . json_encode($signals),
            );

            if (! is_array($result) || ! isset($result['score'])) {
                return $this->riskScorer->score($lead);
            }

            $score = max(0, min(100, (int) $result['score']));
            $band = $this->bandFrom($result['band'] ?? null, $score);

            return CrmRiskScore::updateOrCreate(
                ['crm_lead_id' => $lead->id],
                [
                    'score' => $score,
                    'band' => $band->value,
                    'factors' => $this->sanitiseFactors($result['factors'] ?? []),
                    'reasoning' => (string) ($result['reasoning'] ?? ''),
                    'computed_by' => 'ai',
                    'computed_at' => now(),
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('CRM AI scoreRisk fell back to rules', ['lead' => $lead->id, 'error' => $e->getMessage()]);

            return $this->riskScorer->score($lead);
        }
    }

    // ---- 2. Patient summary -----------------------------------------------------------------------

    /**
     * A short ops-facing narrative summary of a patient. Falls back to a deterministic templated
     * digest built from the Patient360 aggregate.
     *
     * @return array{summary:string, generated_by:string}
     */
    public function summarisePatient(User $patient): array
    {
        $data = $this->patient360->assemble($patient);
        if ($data === null) {
            return ['summary' => 'Not a patient record.', 'generated_by' => 'rules'];
        }

        if (! $this->isConfigured()) {
            return ['summary' => $this->templatedSummary($data), 'generated_by' => 'rules'];
        }

        try {
            $result = $this->chat(
                system: 'You are a CRM assistant. Summarise this patient for an ops agent in 2-3 short '
                    . 'sentences: lifecycle stage, risk, notable order/payment history, and a suggested '
                    . 'next step. Do NOT invent facts. Respond ONLY with JSON: {"summary":str}',
                user: json_encode($this->summaryFacts($data)),
            );

            $summary = is_array($result) ? trim((string) ($result['summary'] ?? '')) : '';

            return $summary !== ''
                ? ['summary' => $summary, 'generated_by' => 'ai']
                : ['summary' => $this->templatedSummary($data), 'generated_by' => 'rules'];
        } catch (\Throwable $e) {
            Log::warning('CRM AI summarisePatient fell back', ['patient' => $patient->id, 'error' => $e->getMessage()]);

            return ['summary' => $this->templatedSummary($data), 'generated_by' => 'rules'];
        }
    }

    // ---- 3. Alert suggested action ---------------------------------------------------------------

    /**
     * Suggest a next action for an alert. Falls back to a rule-based suggestion keyed on the alert's
     * definition key / severity.
     *
     * @return array{action:string, generated_by:string}
     */
    public function suggestAlertAction(Alert $alert): array
    {
        if (! $this->isConfigured()) {
            return ['action' => $this->ruleBasedAlertAction($alert), 'generated_by' => 'rules'];
        }

        try {
            $result = $this->chat(
                system: 'You are an ops triage assistant. Given an alert, suggest a single concrete next '
                    . 'action (one sentence, imperative). Respond ONLY with JSON: {"action":str}',
                user: json_encode([
                    'definition' => $alert->definition_code,
                    'severity' => $alert->severity instanceof \BackedEnum ? $alert->severity->value : $alert->severity,
                    'title' => $alert->title,
                    'detail' => $alert->detail,
                    'context' => $alert->metadata,
                ]),
            );

            $action = is_array($result) ? trim((string) ($result['action'] ?? '')) : '';

            return $action !== ''
                ? ['action' => $action, 'generated_by' => 'ai']
                : ['action' => $this->ruleBasedAlertAction($alert), 'generated_by' => 'rules'];
        } catch (\Throwable $e) {
            Log::warning('CRM AI suggestAlertAction fell back', ['alert' => $alert->id, 'error' => $e->getMessage()]);

            return ['action' => $this->ruleBasedAlertAction($alert), 'generated_by' => 'rules'];
        }
    }

    // ---- 4. Nudge drafting (draft only — never sends) --------------------------------------------

    /**
     * Draft an outreach nudge for a lead. Creates a crm_nudges row in DRAFT status. With AI, the body
     * is model-written; otherwise a templated draft. NEVER sends — approval is a separate ops step.
     */
    public function draftNudge(CrmLead $lead, string $kind = 'reengagement', ?string $channel = null): CrmNudge
    {
        $channel ??= (string) config('crm.ai.default_nudge_channel', 'email');
        $patient = $lead->patient;
        $firstName = $patient?->first_name ?? 'there';

        $body = null;
        $generatedBy = 'rules';

        if ($this->isConfigured()) {
            try {
                $result = $this->chat(
                    system: 'You draft short, warm re-engagement messages for a South African telehealth '
                        . 'CRM. One short paragraph, friendly, no medical advice, no diagnosis, include a '
                        . 'clear call to action to book/continue. Respond ONLY with JSON: {"body":str}',
                    user: json_encode([
                        'first_name' => $firstName,
                        'stage' => $lead->current_stage instanceof \BackedEnum ? $lead->current_stage->value : $lead->current_stage,
                        'kind' => $kind,
                        'channel' => $channel,
                    ]),
                );
                $aiBody = is_array($result) ? trim((string) ($result['body'] ?? '')) : '';
                if ($aiBody !== '') {
                    $body = $aiBody;
                    $generatedBy = 'ai';
                }
            } catch (\Throwable $e) {
                Log::warning('CRM AI draftNudge fell back', ['lead' => $lead->id, 'error' => $e->getMessage()]);
            }
        }

        $body ??= $this->templatedNudge($firstName, $kind);

        return CrmNudge::create([
            'crm_lead_id' => $lead->id,
            'patient_id' => $lead->patient_id,
            'kind' => $kind,
            'channel' => $channel,
            'title' => ucfirst(str_replace('_', ' ', $kind)) . ' outreach',
            'draft_body' => $body,
            'generated_by' => $generatedBy,
            'status' => NudgeStatus::Draft->value,
            'context' => ['stage' => $lead->current_stage instanceof \BackedEnum ? $lead->current_stage->value : (string) $lead->current_stage],
        ]);
    }

    // ---- nudge lifecycle (ops-gated; no real send here) ------------------------------------------

    public function approveNudge(CrmNudge $nudge, ?int $actorId = null): CrmNudge
    {
        if ($nudge->status !== NudgeStatus::Draft) {
            throw new RuntimeException("Only a draft nudge can be approved (was {$nudge->status->value}).");
        }
        $nudge->fill(['status' => NudgeStatus::Approved->value, 'approved_by' => $actorId, 'approved_at' => now()])->save();

        return $nudge;
    }

    /**
     * Mark an approved nudge as sent. This records the fact; the actual dispatch is the notifications
     * layer's job (live flow only). Import/backfill must never call this.
     */
    public function markNudgeSent(CrmNudge $nudge): CrmNudge
    {
        if ($nudge->status !== NudgeStatus::Approved) {
            throw new RuntimeException("Only an approved nudge can be sent (was {$nudge->status->value}).");
        }
        $nudge->fill(['status' => NudgeStatus::Sent->value, 'sent_at' => now()])->save();

        return $nudge;
    }

    public function dismissNudge(CrmNudge $nudge, ?string $reason = null, ?int $actorId = null): CrmNudge
    {
        if ($nudge->status->isTerminal()) {
            throw new RuntimeException("Nudge is already {$nudge->status->value}.");
        }
        $nudge->fill([
            'status' => NudgeStatus::Dismissed->value,
            'dismissed_by' => $actorId,
            'dismissed_at' => now(),
            'dismissed_reason' => $reason,
        ])->save();

        return $nudge;
    }

    // ---- OpenAI client (mirrors AiAssistantService) ----------------------------------------------

    /**
     * Single JSON chat call. Returns the decoded assistant JSON, or null on any non-2xx / parse issue
     * so callers can fall back deterministically.
     *
     * @return array<string,mixed>|null
     */
    private function chat(string $system, string $user): ?array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0.4,
                'max_tokens' => (int) config('crm.ai.max_tokens', 400),
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            Log::error('CRM AI OpenAI error', ['status' => $response->status()]);

            return null;
        }

        $decoded = json_decode((string) $response->json('choices.0.message.content'), true);

        return is_array($decoded) ? $decoded : null;
    }

    // ---- deterministic fallbacks + helpers -------------------------------------------------------

    /** @return array<string,mixed> */
    private function riskSignals(CrmLead $lead): array
    {
        // Reuse the rules engine's computation as the factual signal set fed to the model.
        $rulesScore = $this->riskScorer->score($lead);

        return [
            'current_stage' => $lead->current_stage instanceof \BackedEnum ? $lead->current_stage->value : (string) $lead->current_stage,
            'rules_score' => $rulesScore->score,
            'rules_band' => $rulesScore->band->value,
            'rules_factors' => $rulesScore->factors,
        ];
    }

    private function bandFrom(?string $band, int $score): RiskBand
    {
        $band = strtolower((string) $band);

        return match ($band) {
            'low' => RiskBand::Low,
            'medium' => RiskBand::Medium,
            'high' => RiskBand::High,
            'critical' => RiskBand::Critical,
            default => RiskBand::fromScore($score),
        };
    }

    /**
     * Keep only well-formed factor rows so a malformed AI response can't corrupt the stored shape.
     *
     * @param  mixed  $factors
     * @return array<int,array{label:string,impact:string,weight:int}>
     */
    private function sanitiseFactors($factors): array
    {
        if (! is_array($factors)) {
            return [];
        }

        $clean = [];
        foreach ($factors as $f) {
            if (! is_array($f) || ! isset($f['label'])) {
                continue;
            }
            $impact = in_array($f['impact'] ?? '', ['positive', 'warning', 'negative'], true) ? $f['impact'] : 'warning';
            $clean[] = [
                'label' => (string) $f['label'],
                'impact' => $impact,
                'weight' => max(0, min(10, (int) ($f['weight'] ?? 0))),
            ];
        }

        return $clean;
    }

    /** @param  array<string,mixed>  $data */
    private function summaryFacts(array $data): array
    {
        return [
            'name' => trim(($data['patient']->first_name ?? '') . ' ' . ($data['patient']->last_name ?? '')),
            'stage' => $data['stage'] instanceof \BackedEnum ? $data['stage']->value : (string) $data['stage'],
            'risk_band' => $data['risk']?->band?->value,
            'risk_score' => $data['risk']?->score,
            'ltv_rand' => round(($data['ltv_minor'] ?? 0) / 100, 2),
            'counts' => $data['counts'] ?? [],
            'active_flags' => $data['flags']->pluck('kind')->map(fn ($k) => $k instanceof \BackedEnum ? $k->value : $k)->all(),
        ];
    }

    /** @param  array<string,mixed>  $data */
    private function templatedSummary(array $data): string
    {
        $name = trim(($data['patient']->first_name ?? '') . ' ' . ($data['patient']->last_name ?? '')) ?: 'This patient';
        $stage = $data['stage'] instanceof \BackedEnum ? $data['stage']->label() : (string) $data['stage'];
        $band = $data['risk']?->band?->value ?? 'unknown';
        $ltv = number_format(($data['ltv_minor'] ?? 0) / 100, 2);
        $orders = $data['counts']['orders'] ?? 0;
        $flags = $data['flags']->count();

        $flagText = $flags > 0 ? " Has {$flags} active flag(s) — review before contact." : '';

        return "{$name} is at the '{$stage}' stage with a {$band} risk band. "
            . "Lifetime value R{$ltv} across {$orders} order(s).{$flagText} "
            . 'Suggested next step: a check-in aligned to the current stage.';
    }

    private function ruleBasedAlertAction(Alert $alert): string
    {
        $key = (string) $alert->definition_code;

        return match (true) {
            str_contains($key, 'payment') => 'Contact the patient to update their payment method and retry the charge.',
            str_contains($key, 'stale') || str_contains($key, 'order') => 'Chase the pharmacy/logistics owner for a status update on this order.',
            str_contains($key, 'no_show') => 'Reach out to reschedule the missed consultation.',
            str_contains($key, 'abandoned') || str_contains($key, 'cart') => 'Send a gentle reminder to complete the assessment.',
            str_contains($key, 'churn') => 'Trigger a re-engagement nudge and review the risk factors.',
            str_contains($key, 'booking') => 'Confirm the pending booking or offer alternative slots.',
            default => 'Review the alert details and assign an owner for follow-up.',
        };
    }

    private function templatedNudge(string $firstName, string $kind): string
    {
        return match ($kind) {
            'cross_sell' => "Hi {$firstName}, based on your care with Zapmed there may be complementary treatments that suit you. Book a quick consult and our doctors can advise — all from home.",
            'winback' => "Hi {$firstName}, we've missed you at Zapmed. Whenever you're ready to pick things back up, our SA-licensed doctors are one click away and medication is delivered to your door.",
            'checkin' => "Hi {$firstName}, just checking in on how you're doing with your treatment. If anything's changed or you have questions, a Zapmed doctor is happy to help.",
            default => "Hi {$firstName}, ready to continue your Zapmed journey? Start your assessment and one of our doctors will guide the next step — quick, private, and delivered to your door.",
        };
    }
}
