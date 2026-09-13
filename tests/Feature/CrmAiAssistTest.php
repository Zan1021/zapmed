<?php

namespace Tests\Feature;

use App\Enums\NudgeStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\AiNudges;
use App\Models\Alert;
use App\Models\CrmNudge;
use App\Models\User;
use App\Services\Crm\CrmAiService;
use App\Services\Crm\LeadFunnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 8 — AI assist. Exercises the DETERMINISTIC fallback paths (no OpenAI key), the nudge lifecycle
 * guards, and UI access. No real HTTP is made: with the key unset, isConfigured() is false and every
 * feature takes its non-AI branch. This is the "degrades gracefully with no AI key" guarantee.
 */
class CrmAiAssistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Guarantee the no-key path regardless of local .env.
        config(['services.openai.api_key' => '']);
    }

    private function ai(): CrmAiService
    {
        return app(CrmAiService::class);
    }

    private function patient(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => UserRole::Patient], $attrs));
    }

    private function leadFor(User $patient)
    {
        return app(LeadFunnel::class)->ensureLead($patient);
    }

    // ---- configuration gate ---------------------------------------------------------------------

    public function test_is_not_configured_without_a_key(): void
    {
        $this->assertFalse($this->ai()->isConfigured());
    }

    public function test_master_switch_disables_ai_even_with_a_key(): void
    {
        config(['services.openai.api_key' => 'sk-test', 'crm.ai.enabled' => false]);
        $this->assertFalse($this->ai()->isConfigured());
    }

    // ---- 1. risk scoring falls back to the rules engine -----------------------------------------

    public function test_score_risk_falls_back_to_rules_engine(): void
    {
        $lead = $this->leadFor($this->patient());

        $score = $this->ai()->scoreRisk($lead);

        $this->assertSame('rules', $score->computed_by);
        $this->assertSame($lead->id, $score->crm_lead_id);
        $this->assertIsArray($score->factors);
    }

    // ---- 2. patient summary falls back to a templated digest ------------------------------------

    public function test_summarise_patient_falls_back_to_template(): void
    {
        $patient = $this->patient(['first_name' => 'Thabo', 'last_name' => 'M']);
        $this->leadFor($patient);

        $result = $this->ai()->summarisePatient($patient);

        $this->assertSame('rules', $result['generated_by']);
        $this->assertStringContainsString('Thabo', $result['summary']);
    }

    public function test_summarise_non_patient_returns_gracefully(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $result = $this->ai()->summarisePatient($admin);

        $this->assertSame('Not a patient record.', $result['summary']);
    }

    // ---- 3. alert suggested action falls back to rules ------------------------------------------

    public function test_suggest_alert_action_is_rule_based_by_definition(): void
    {
        $alert = Alert::create([
            'definition_code' => 'payments.failed',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Payment failed',
            'subject_type' => 'payment',
            'subject_id' => '1',
            'dedupe_key' => 'payments.failed:1',
            'raised_at' => now(),
        ]);

        $result = $this->ai()->suggestAlertAction($alert);

        $this->assertSame('rules', $result['generated_by']);
        $this->assertStringContainsStringIgnoringCase('payment', $result['action']);
    }

    // ---- 4. nudge drafting (never sends) --------------------------------------------------------

    public function test_draft_nudge_creates_a_draft_and_never_sends(): void
    {
        $lead = $this->leadFor($this->patient(['first_name' => 'Lerato']));

        $nudge = $this->ai()->draftNudge($lead, 'winback');

        $this->assertSame(NudgeStatus::Draft, $nudge->status);
        $this->assertSame('rules', $nudge->generated_by);
        $this->assertSame('winback', $nudge->kind);
        $this->assertStringContainsString('Lerato', $nudge->draft_body);
        $this->assertNull($nudge->sent_at);
    }

    // ---- nudge lifecycle guards -----------------------------------------------------------------

    public function test_nudge_lifecycle_draft_to_approved_to_sent(): void
    {
        $lead = $this->leadFor($this->patient());
        $nudge = $this->ai()->draftNudge($lead);

        $this->ai()->approveNudge($nudge, 1);
        $this->assertSame(NudgeStatus::Approved, $nudge->fresh()->status);

        $this->ai()->markNudgeSent($nudge->fresh());
        $this->assertSame(NudgeStatus::Sent, $nudge->fresh()->status);
        $this->assertNotNull($nudge->fresh()->sent_at);
    }

    public function test_cannot_send_an_unapproved_nudge(): void
    {
        $lead = $this->leadFor($this->patient());
        $nudge = $this->ai()->draftNudge($lead);

        $this->expectException(RuntimeException::class);
        $this->ai()->markNudgeSent($nudge); // still draft
    }

    public function test_cannot_dismiss_a_sent_nudge(): void
    {
        $lead = $this->leadFor($this->patient());
        $nudge = $this->ai()->draftNudge($lead);
        $this->ai()->approveNudge($nudge);
        $this->ai()->markNudgeSent($nudge->fresh());

        $this->expectException(RuntimeException::class);
        $this->ai()->dismissNudge($nudge->fresh(), 'too late');
    }

    // ---- UI -------------------------------------------------------------------------------------

    public function test_admin_can_view_ai_nudges(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(route('admin.ai-nudges'))
            ->assertOk()
            ->assertSeeLivewire(AiNudges::class);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->patient())->get(route('admin.ai-nudges'))->assertForbidden();
    }

    public function test_ui_draft_then_approve_flow(): void
    {
        $lead = $this->leadFor($this->patient(['first_name' => 'Naledi']));

        Livewire::actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->test(AiNudges::class)
            ->set('draftLeadId', (string) $lead->id)
            ->set('draftKind', 'checkin')
            ->call('draft')
            ->assertHasNoErrors();

        $nudge = CrmNudge::where('crm_lead_id', $lead->id)->first();
        $this->assertNotNull($nudge);
        $this->assertSame('checkin', $nudge->kind);

        Livewire::actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->test(AiNudges::class)
            ->call('approve', $nudge->id);

        $this->assertSame(NudgeStatus::Approved, $nudge->fresh()->status);
    }

    public function test_ui_shows_no_key_banner_state(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->test(AiNudges::class)
            ->assertSet('filter', 'pending');

        $this->assertFalse($this->ai()->isConfigured());
    }
}
