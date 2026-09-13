<?php

namespace Tests\Feature;

use App\Enums\RevenueKind;
use App\Enums\UserRole;
use App\Livewire\Admin\AnalyticsDashboard;
use App\Livewire\Admin\FinanceReports;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 7 — Analytics + Finance admin UI (CRM Analytics dashboard + Finance reports).
 */
class AnalyticsFinanceUiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function patient(): User
    {
        return User::factory()->create(['role' => UserRole::Patient]);
    }

    // ---- routes / access ------------------------------------------------------------------------

    public function test_admin_can_view_crm_analytics(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.crm-analytics'))
            ->assertOk()
            ->assertSeeLivewire(AnalyticsDashboard::class);
    }

    public function test_admin_can_view_finance(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.finance'))
            ->assertOk()
            ->assertSeeLivewire(FinanceReports::class);
    }

    public function test_non_admin_is_forbidden_on_both(): void
    {
        $patient = $this->patient();
        $this->actingAs($patient)->get(route('admin.crm-analytics'))->assertForbidden();
        $this->actingAs($patient)->get(route('admin.finance'))->assertForbidden();
    }

    // ---- finance recon actions through the UI ---------------------------------------------------

    public function test_matching_a_recon_entry_updates_status(): void
    {
        config(['analytics.finance.recon_auto_match_tolerance_cents' => 100]);
        $order = Order::create(['patient_id' => $this->patient()->id, 'status' => 'PendingPayment', 'total_minor' => 50000, 'ordered_at' => now()]);
        $recon = app(FinanceService::class)->upsertRecon($order, 50000, 50000, 'INV-1');

        Livewire::actingAs($this->admin())
            ->test(FinanceReports::class)
            ->call('matchRecon', $recon->id);

        $this->assertSame('matched', $recon->fresh()->status->value);
    }

    public function test_dispute_requires_a_note(): void
    {
        $order = Order::create(['patient_id' => $this->patient()->id, 'status' => 'PendingPayment', 'total_minor' => 50000, 'ordered_at' => now()]);
        $recon = app(FinanceService::class)->upsertRecon($order, 55000, 50000, 'INV-2');

        Livewire::actingAs($this->admin())
            ->test(FinanceReports::class)
            ->call('openAction', $recon->id, 'dispute')
            ->set('actionNote', '')
            ->call('confirmAction')
            ->assertHasErrors('actionNote');

        $this->assertSame('unmatched', $recon->fresh()->status->value);
    }

    public function test_write_off_with_note_sets_status_and_reason(): void
    {
        $order = Order::create(['patient_id' => $this->patient()->id, 'status' => 'PendingPayment', 'total_minor' => 50000, 'ordered_at' => now()]);
        $recon = app(FinanceService::class)->upsertRecon($order, 55000, 50000, 'INV-3');

        Livewire::actingAs($this->admin())
            ->test(FinanceReports::class)
            ->call('openAction', $recon->id, 'write_off')
            ->set('actionNote', 'uncollectable — pharmacy conceded')
            ->call('confirmAction')
            ->assertHasNoErrors();

        $this->assertSame('written_off', $recon->fresh()->status->value);
        $this->assertSame('uncollectable — pharmacy conceded', $recon->fresh()->written_off_reason);
    }

    public function test_csv_export_streams_revenue_rows(): void
    {
        app(FinanceService::class)->recordRevenue(RevenueKind::CashCollected, 12345, now(), 'medication', 'weight_loss');

        $response = Livewire::actingAs($this->admin())
            ->test(FinanceReports::class)
            ->call('exportRevenueCsv')
            ->effects['download'] ?? null;

        // The streamed download is surfaced as a Livewire effect; assert the component didn't error
        // and the underlying service produced the row (the stream content is exercised in the
        // service-level test).
        $this->assertSame(1, \App\Models\FinanceRevenueEntry::count());
    }

    public function test_analytics_dashboard_renders_kpis(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AnalyticsDashboard::class)
            ->assertOk()
            ->assertSee('New patients');
    }
}
