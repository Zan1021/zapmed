<?php

namespace Tests\Feature;

use App\Enums\FunnelEventKind;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;
use App\Services\Analytics\AnalyticsService as FunnelAnalytics;
use App\Services\Stats\StatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T7.2 — StatsService facade: canonical period windowing + correct delegation to the existing
 * business + funnel analytics services, exposed as one sectioned read surface for the Stats page.
 */
class StatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private StatsService $stats;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stats = app(StatsService::class);
    }

    public function test_window_resolves_named_periods(): void
    {
        [$from, $to] = $this->stats->window('month');
        $this->assertTrue($from->equalTo(CarbonImmutable::now()->startOfMonth()));
        $this->assertTrue($to->equalTo(CarbonImmutable::now()->endOfMonth()));

        [$from, $to] = $this->stats->window('today');
        $this->assertTrue($from->equalTo(CarbonImmutable::now()->startOfDay()));

        // Unknown period falls back to month.
        [$from] = $this->stats->window('nonsense');
        $this->assertTrue($from->equalTo(CarbonImmutable::now()->startOfMonth()));
    }

    public function test_overview_exposes_all_business_sections(): void
    {
        $p = User::factory()->create(['role' => UserRole::Patient]);
        Payment::create(['patient_id' => $p->id, 'provider' => 'payfast', 'amount' => 45000, 'currency' => 'ZAR', 'status' => 'completed', 'description' => 'Consultation']);

        $overview = $this->stats->overview('month');

        foreach (['revenue', 'revenue_by_type', 'profit', 'patients', 'consultations', 'prescriptions'] as $section) {
            $this->assertArrayHasKey($section, $overview);
        }
        $this->assertSame(45000, $overview['revenue']['total']);
    }

    public function test_acquisition_delegates_to_funnel_analytics(): void
    {
        $p = User::factory()->create(['role' => UserRole::Patient]);
        // Seed one funnel event so acquisition has data.
        app(FunnelAnalytics::class)->recordEvent(FunnelEventKind::SignUpComplete, principalId: $p->id);

        $acq = $this->stats->acquisition('month');

        foreach (['kpi_summary', 'funnel_counts', 'conversion_rates', 'cac_cents'] as $section) {
            $this->assertArrayHasKey($section, $acq);
        }
        $this->assertSame(1, $acq['funnel_counts'][FunnelEventKind::SignUpComplete->value]);
    }
}
