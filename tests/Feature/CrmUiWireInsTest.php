<?php

namespace Tests\Feature;

use App\Enums\ConsentPurpose;
use App\Enums\ConsentState;
use App\Enums\UserRole;
use App\Livewire\Admin\AlertsBoard;
use App\Livewire\Admin\ComplianceConsole;
use App\Livewire\Admin\PatientProfile360;
use App\Models\Alert;
use App\Models\ComplianceConsent;
use App\Models\User;
use App\Services\Crm\LeadFunnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 8/9 UI wire-ins: AI patient summary (Patient 360), AI suggested action (Alerts board), and the
 * consent viewer/editor (Compliance console). No OpenAI key → deterministic fallbacks.
 */
class CrmUiWireInsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai.api_key' => '']); // force fallback paths
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function patient(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => UserRole::Patient], $attrs));
    }

    // ---- Patient 360 AI summary -----------------------------------------------------------------

    public function test_patient_360_generates_ai_summary_fallback(): void
    {
        $patient = $this->patient(['first_name' => 'Sipho']);
        app(LeadFunnel::class)->ensureLead($patient);

        Livewire::actingAs($this->admin())
            ->test(PatientProfile360::class)
            ->set('patientId', $patient->id)
            ->call('summarise')
            ->assertSet('aiSummaryBy', 'rules')
            ->assertSee('Sipho'); // summary rendered in the component state
    }

    // ---- Alerts board AI suggested action -------------------------------------------------------

    public function test_alerts_board_suggests_action_fallback(): void
    {
        $alert = Alert::create([
            'definition_code' => 'payments.failed',
            'severity' => 'warning', 'status' => 'open', 'title' => 'Payment failed',
            'subject_type' => 'payment', 'subject_id' => '1', 'dedupe_key' => 'payments.failed:1', 'raised_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(AlertsBoard::class)
            ->call('select', $alert->id)
            ->call('suggestAction')
            ->assertSet('aiActionBy', 'rules');
    }

    // ---- Compliance consent viewer/editor -------------------------------------------------------

    public function test_compliance_console_finds_patient_and_sets_consent(): void
    {
        $patient = $this->patient(['first_name' => 'Nomsa', 'email' => 'nomsa@example.test']);

        Livewire::actingAs($this->admin())
            ->test(ComplianceConsole::class)
            ->set('tab', 'consent')
            ->set('consentQuery', 'nomsa@example.test')
            ->call('findConsentPatient')
            ->assertSet('consentPatientId', $patient->id)
            ->call('grantConsent', ConsentPurpose::Marketing->value);

        $consent = ComplianceConsent::where('principal_id', $patient->id)->where('purpose', 'marketing')->first();
        $this->assertNotNull($consent);
        $this->assertSame(ConsentState::Granted, $consent->state);

        // Withdraw path
        Livewire::actingAs($this->admin())
            ->test(ComplianceConsole::class)
            ->set('consentPatientId', $patient->id)
            ->call('withdrawConsent', ConsentPurpose::Marketing->value);

        $this->assertSame(ConsentState::Withdrawn, $consent->fresh()->state);
    }
}
