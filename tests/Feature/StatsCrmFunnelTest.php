<?php

namespace Tests\Feature;

use App\Enums\FunnelStage;
use App\Enums\UserRole;
use App\Models\CrmFlag;
use App\Models\CrmLead;
use App\Models\CrmRiskScore;
use App\Models\User;
use App\Services\Crm\LeadFunnel;
use App\Services\Stats\StatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T7.6 — CRM funnel metrics: 14-stage lead counts (zero-filled), risk-band distribution,
 * open flags by kind, dropped/cold counts.
 */
class StatsCrmFunnelTest extends TestCase
{
    use RefreshDatabase;

    private StatsService $stats;
    private LeadFunnel $funnel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stats = app(StatsService::class);
        $this->funnel = app(LeadFunnel::class);
    }

    private function leadAtStage(FunnelStage $stage): CrmLead
    {
        $patient = User::factory()->create(['role' => UserRole::Patient]);
        $lead = $this->funnel->ensureLead($patient);
        if ($stage !== FunnelStage::Lead) {
            $this->funnel->advance($lead, $stage);
        }

        return $lead->fresh();
    }

    public function test_by_stage_zero_fills_all_14_stages(): void
    {
        $this->leadAtStage(FunnelStage::SignedUp);
        $this->leadAtStage(FunnelStage::Paid);

        $m = $this->stats->crmFunnel();

        $this->assertCount(14, $m['by_stage'], 'all 14 funnel stages must appear');
        $this->assertSame(1, $m['by_stage']['signed_up']);
        $this->assertSame(1, $m['by_stage']['paid']);
        $this->assertSame(0, $m['by_stage']['delivered']);
        $this->assertSame(2, $m['total_leads']);
    }

    public function test_risk_band_distribution_including_unscored(): void
    {
        $scored = $this->leadAtStage(FunnelStage::SignedUp);
        CrmRiskScore::create(['crm_lead_id' => $scored->id, 'score' => 80, 'band' => 'high', 'computed_by' => 'rules', 'computed_at' => now()]);
        // A second lead with no score -> unscored.
        $this->leadAtStage(FunnelStage::SignedUp);

        $m = $this->stats->crmFunnel();

        $this->assertSame(1, $m['by_risk_band']['high']);
        $this->assertSame(1, $m['by_risk_band']['unscored']);
    }

    public function test_open_flags_by_kind_excludes_cleared(): void
    {
        $lead = $this->leadAtStage(FunnelStage::SignedUp);
        CrmFlag::create(['crm_lead_id' => $lead->id, 'kind' => 'at_risk']);
        CrmFlag::create(['crm_lead_id' => $lead->id, 'kind' => 'vip']);
        // A cleared flag must NOT count.
        CrmFlag::create(['crm_lead_id' => $lead->id, 'kind' => 'complaint_open', 'cleared_at' => now()]);

        $m = $this->stats->crmFunnel();

        $this->assertSame(1, $m['open_flags_by_kind']['at_risk']);
        $this->assertSame(1, $m['open_flags_by_kind']['vip']);
        $this->assertArrayNotHasKey('complaint_open', $m['open_flags_by_kind']);
    }

    public function test_dropped_and_cold_counts(): void
    {
        $this->leadAtStage(FunnelStage::DroppedOff);

        // A cold lead: active stage but no activity for > threshold days.
        $coldLead = $this->leadAtStage(FunnelStage::ConsultBooked);
        $coldLead->forceFill(['last_activity_at' => now()->subDays(30)])->save();

        $m = $this->stats->crmFunnel(14);

        $this->assertSame(1, $m['dropped_off']);
        $this->assertSame(1, $m['cold']);
        $this->assertSame(14, $m['cold_threshold_days']);
    }
}
