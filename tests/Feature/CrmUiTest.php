<?php

namespace Tests\Feature;

use App\Enums\FunnelStage;
use App\Enums\UserRole;
use App\Livewire\Admin\LeadsFunnel;
use App\Livewire\Admin\PatientProfile360;
use App\Models\CrmLead;
use App\Models\User;
use App\Services\Crm\LeadFunnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 3 — CRM admin UI (Leads/Funnel board + Patient 360).
 */
class CrmUiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function patient(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => UserRole::Patient], $attrs));
    }

    // ---- routes / access -----------------------------------------------------------------------

    public function test_admin_can_view_leads_funnel(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.leads-funnel'))
            ->assertOk()
            ->assertSeeLivewire(LeadsFunnel::class);
    }

    public function test_admin_can_view_patient_360(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.patient-360'))
            ->assertOk()
            ->assertSeeLivewire(PatientProfile360::class);
    }

    public function test_non_admin_is_forbidden_on_both(): void
    {
        $patient = $this->patient();
        $this->actingAs($patient)->get(route('admin.leads-funnel'))->assertForbidden();
        $this->actingAs($patient)->get(route('admin.patient-360'))->assertForbidden();
    }

    // ---- Leads/Funnel board --------------------------------------------------------------------

    public function test_leads_are_grouped_by_stage(): void
    {
        $funnel = app(LeadFunnel::class);
        $funnel->ensureLead($this->patient());                                   // lead
        $paid = $funnel->ensureLead($this->patient());
        $funnel->advance($paid, FunnelStage::Paid);                              // paid

        Livewire::actingAs($this->admin())
            ->test(LeadsFunnel::class)
            ->tap(function ($t) {
                $cols = $t->instance()->columns();
                $this->assertCount(1, $cols['lead']);
                $this->assertCount(1, $cols['paid']);
                $this->assertCount(0, $cols['delivered']);
            });
    }

    public function test_moving_a_lead_records_the_transition(): void
    {
        $lead = app(LeadFunnel::class)->ensureLead($this->patient());

        Livewire::actingAs($this->admin())
            ->test(LeadsFunnel::class)
            ->call('selectLead', $lead->id)
            ->set('moveTo', FunnelStage::SignedUp->value)
            ->set('moveNotes', 'account made')
            ->call('moveStage');

        $lead->refresh();
        $this->assertSame(FunnelStage::SignedUp, $lead->current_stage);
        $this->assertSame(2, $lead->funnelEvents()->count());
    }

    public function test_raising_and_clearing_a_flag_through_the_ui(): void
    {
        $lead = app(LeadFunnel::class)->ensureLead($this->patient());

        $component = Livewire::actingAs($this->admin())
            ->test(LeadsFunnel::class)
            ->call('selectLead', $lead->id)
            ->set('flagKind', 'at_risk')
            ->call('raiseFlag');

        $this->assertSame(1, $lead->activeFlags()->count());

        $flagId = $lead->activeFlags()->first()->id;
        $component->call('clearFlag', $flagId);
        $this->assertSame(0, $lead->fresh()->activeFlags()->count());
    }

    public function test_adding_a_note_through_the_ui(): void
    {
        $lead = app(LeadFunnel::class)->ensureLead($this->patient());

        Livewire::actingAs($this->admin())
            ->test(LeadsFunnel::class)
            ->call('selectLead', $lead->id)
            ->set('noteBody', 'called patient, will retry card')
            ->set('notePinned', true)
            ->call('addNote');

        $this->assertSame(1, $lead->notes()->count());
        $this->assertTrue($lead->notes()->first()->pinned);
    }

    public function test_recompute_risk_through_the_ui(): void
    {
        $lead = app(LeadFunnel::class)->ensureLead($this->patient());

        Livewire::actingAs($this->admin())
            ->test(LeadsFunnel::class)
            ->call('selectLead', $lead->id)
            ->call('recomputeRisk');

        $this->assertSame(1, $lead->riskScore()->count());
    }

    // ---- Patient 360 ---------------------------------------------------------------------------

    public function test_patient_360_search_then_view(): void
    {
        $patient = $this->patient(['first_name' => 'Katherine', 'last_name' => 'Johnson']);
        app(LeadFunnel::class)->ensureLead($patient);

        Livewire::actingAs($this->admin())
            ->test(PatientProfile360::class)
            ->set('q', 'Johnson')
            ->assertSee('Katherine')
            ->call('view', $patient->id)
            ->assertSet('patientId', $patient->id)
            ->assertSee('Katherine Johnson');
    }
}
