<?php

namespace Tests\Feature;

use App\Enums\FunnelEventKind;
use App\Enums\ReconStatus;
use App\Enums\RevenueKind;
use App\Enums\UserRole;
use App\Models\AnalyticsAttribution;
use App\Models\FinanceReconEntry;
use App\Models\FinanceRevenueEntry;
use App\Models\Order;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Services\Finance\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 7 — Analytics + Finance service behaviour (parity with Mark's analytics + finance_reports).
 */
class AnalyticsFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function analytics(): AnalyticsService
    {
        return app(AnalyticsService::class);
    }

    private function finance(): FinanceService
    {
        return app(FinanceService::class);
    }

    private function patient(): User
    {
        return User::factory()->create(['role' => UserRole::Patient]);
    }

    private function order(User $patient): Order
    {
        return Order::create([
            'patient_id' => $patient->id,
            'status' => 'PendingPayment',
            'total_minor' => 50000,
            'ordered_at' => now(),
        ]);
    }

    // ---- analytics: funnel + conversion + CAC ---------------------------------------------------

    public function test_funnel_counts_tally_events_in_window(): void
    {
        $this->analytics()->recordEvent(FunnelEventKind::PageView, visitorId: 'v1');
        $this->analytics()->recordEvent(FunnelEventKind::SignUpComplete, principalId: $this->patient()->id);
        $this->analytics()->recordEvent(FunnelEventKind::SignUpComplete, principalId: $this->patient()->id);

        $counts = $this->analytics()->funnelCounts(now()->subDay(), now()->addDay());

        $this->assertSame(1, $counts['page_view']);
        $this->assertSame(2, $counts['sign_up_complete']);
        $this->assertSame(0, $counts['first_payment']);
    }

    public function test_conversion_rate_is_step_over_prior_step(): void
    {
        // 4 signups complete, 1 first payment → 25% signup→...→first_payment adjacent chain still 0
        // unless intermediate steps exist. Test the direct adjacent ratio we control:
        foreach (range(1, 4) as $i) {
            $this->analytics()->recordEvent(FunnelEventKind::SignUpStarted, visitorId: "v{$i}");
        }
        $this->analytics()->recordEvent(FunnelEventKind::SignUpComplete, principalId: $this->patient()->id);

        $rates = $this->analytics()->conversionRates(now()->subDay(), now()->addDay());

        $this->assertSame(0.25, $rates['sign_up_started→sign_up_complete']);
    }

    public function test_cac_is_spend_over_new_patients_and_null_when_no_acquisitions(): void
    {
        $this->assertNull($this->analytics()->cac(now()->subDays(2), now()));

        $this->analytics()->recordSpend(now()->subDay(), now(), 'google_ads', 100000); // R1000
        $this->analytics()->recordEvent(FunnelEventKind::SignUpComplete, principalId: $this->patient()->id);
        $this->analytics()->recordEvent(FunnelEventKind::SignUpComplete, principalId: $this->patient()->id);

        // R1000 / 2 patients = R500 = 50000 cents
        $this->assertSame(50000, $this->analytics()->cac(now()->subDays(2), now()->addDay()));
    }

    public function test_attribution_preserves_first_touch_and_updates_last_touch(): void
    {
        $patient = $this->patient();

        $this->analytics()->captureAttribution($patient->id, [
            'source' => 'google', 'medium' => 'cpc', 'campaign' => 'launch',
        ]);
        $this->analytics()->captureAttribution($patient->id, [
            'source' => 'newsletter', 'medium' => 'email', 'campaign' => 'reactivate',
        ]);

        $row = AnalyticsAttribution::find($patient->id);
        $this->assertSame('google', $row->first_source);      // first-touch preserved
        $this->assertSame('newsletter', $row->last_source);   // last-touch updated
    }

    public function test_snapshot_is_idempotent_per_day(): void
    {
        $this->analytics()->recordEvent(FunnelEventKind::SignUpComplete, principalId: $this->patient()->id, occurredAt: now());

        $a = $this->analytics()->snapshot(now());
        $b = $this->analytics()->snapshot(now());

        $this->assertSame($a->snapshot_date->toDateString(), $b->snapshot_date->toDateString());
        $this->assertSame(1, \App\Models\AnalyticsKpiSnapshot::count());
        $this->assertSame(1, $b->metrics['new_patients']);
    }

    // ---- finance: revenue signing + summary -----------------------------------------------------

    public function test_refund_and_discount_are_forced_negative(): void
    {
        $refund = $this->finance()->recordRevenue(RevenueKind::Refund, 5000, now());
        $discount = $this->finance()->recordRevenue(RevenueKind::Discount, 1500, now());
        $cash = $this->finance()->recordRevenue(RevenueKind::CashCollected, 9900, now());

        $this->assertSame(-5000, $refund->amount_cents);
        $this->assertSame(-1500, $discount->amount_cents);
        $this->assertSame(9900, $cash->amount_cents);
    }

    public function test_revenue_summary_groups_by_kind(): void
    {
        $this->finance()->recordRevenue(RevenueKind::CashCollected, 10000, now());
        $this->finance()->recordRevenue(RevenueKind::CashCollected, 5000, now());
        $this->finance()->recordRevenue(RevenueKind::Refund, 2000, now());

        $summary = $this->finance()->revenueSummary(now()->subDay(), now()->addDay());

        $this->assertSame(15000, $summary['cash_collected']['amount_cents']);
        $this->assertSame(2, $summary['cash_collected']['entry_count']);
        $this->assertSame(-2000, $summary['refund']['amount_cents']);
    }

    public function test_cash_from_payment_is_idempotent(): void
    {
        $patient = $this->patient();
        $payment = \App\Models\Payment::create([
            'patient_id' => $patient->id,
            'amount' => 29900,
            'currency' => 'ZAR',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => 'subscription',
        ]);

        $a = $this->finance()->recordCashFromPayment($payment);
        $b = $this->finance()->recordCashFromPayment($payment);

        $this->assertTrue($a->is($b));
        $this->assertSame(1, FinanceRevenueEntry::where('payment_id', $payment->id)->count());
        $this->assertSame(29900, $a->amount_cents);
    }

    // ---- finance: reconciliation ----------------------------------------------------------------

    public function test_recon_delta_is_computed_pharmacy_minus_payment(): void
    {
        $order = $this->order($this->patient());
        $recon = $this->finance()->upsertRecon($order, pharmacyAmountCents: 52000, paymentAmountCents: 50000);

        $this->assertSame(2000, $recon->delta_cents);
    }

    public function test_match_within_tolerance_is_matched_else_partial(): void
    {
        config(['analytics.finance.recon_auto_match_tolerance_cents' => 100]);
        $order = $this->order($this->patient());

        $within = $this->finance()->upsertRecon($order, 50050, 50000, 'INV-1');
        $this->assertSame(ReconStatus::Matched, $this->finance()->match($within));
        $this->assertSame(ReconStatus::Matched, $within->fresh()->status);

        $order2 = $this->order($this->patient());
        $outside = $this->finance()->upsertRecon($order2, 55000, 50000, 'INV-2');
        $this->assertSame(ReconStatus::Partial, $this->finance()->match($outside));
    }

    public function test_upsert_recon_is_idempotent_on_order_and_invoice(): void
    {
        $order = $this->order($this->patient());

        $a = $this->finance()->upsertRecon($order, 50000, 50000, 'INV-9');
        $b = $this->finance()->upsertRecon($order, 51000, 50000, 'INV-9');

        $this->assertTrue($a->is($b));
        $this->assertSame(1, FinanceReconEntry::where('order_id', $order->id)->count());
        $this->assertSame(51000, $b->fresh()->pharmacy_amount_cents);
    }

    public function test_cannot_write_off_a_matched_entry(): void
    {
        config(['analytics.finance.recon_auto_match_tolerance_cents' => 100]);
        $order = $this->order($this->patient());
        $recon = $this->finance()->upsertRecon($order, 50000, 50000, 'INV-3');
        $this->finance()->match($recon);

        $this->expectException(RuntimeException::class);
        $this->finance()->writeOff($recon->fresh(), 'should fail');
    }

    public function test_export_rows_carry_rand_and_cents(): void
    {
        $this->finance()->recordRevenue(RevenueKind::CashCollected, 12345, now(), 'medication', 'weight_loss');

        $rows = $this->finance()->revenueExportRows(now()->subDay(), now()->addDay());

        $this->assertCount(1, $rows);
        $this->assertSame(12345, $rows[0]['amount_cents']);
        $this->assertSame('123.45', $rows[0]['amount_rand']);
        $this->assertSame('weight_loss', $rows[0]['service_line']);
    }
}
