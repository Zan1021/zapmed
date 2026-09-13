<?php

namespace Tests\Feature;

use App\Enums\RevenueKind;
use App\Enums\UserRole;
use App\Models\FinanceReconEntry;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\FinanceService;
use App\Services\Stats\StatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T7.5 — finance metrics: revenue by kind + by service line, net, refunds, reconciliation status
 * counts, and outstanding (uncaptured aged) payments.
 */
class StatsFinanceTest extends TestCase
{
    use RefreshDatabase;

    private StatsService $stats;
    private FinanceService $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stats = app(StatsService::class);
        $this->finance = app(FinanceService::class);
    }

    public function test_revenue_by_service_line_and_net_and_refunds(): void
    {
        $this->finance->recordRevenue(RevenueKind::CashCollected, 45000, now(), 'consult', 'weight-loss');
        $this->finance->recordRevenue(RevenueKind::CashCollected, 30000, now(), 'consult', 'ed');
        // A refund (passed positive; service forces negative).
        $this->finance->recordRevenue(RevenueKind::Refund, 5000, now(), 'consult', 'weight-loss');

        $m = $this->stats->finance('month');

        $this->assertSame(45000 - 5000, $m['by_service_line']['weight-loss']['amount_cents']);
        $this->assertSame(30000, $m['by_service_line']['ed']['amount_cents']);
        // Net = 45000 + 30000 - 5000.
        $this->assertSame(70000, $m['net_cents']);
        // Refunds reported as positive magnitude.
        $this->assertSame(5000, $m['refunds_cents']);
        // by_kind delegated to FinanceService.
        $this->assertArrayHasKey('cash_collected', $m['by_kind']);
    }

    public function test_reconciliation_status_counts(): void
    {
        $o1 = $this->reconOrder();
        $o2 = $this->reconOrder();
        $o3 = $this->reconOrder();
        FinanceReconEntry::create(['order_id' => $o1, 'status' => 'matched', 'pharmacy_amount_cents' => 1000, 'payment_amount_cents' => 1000, 'pharmacy_invoice_ref' => 'INV1']);
        FinanceReconEntry::create(['order_id' => $o2, 'status' => 'unmatched', 'pharmacy_amount_cents' => 2000, 'payment_amount_cents' => 0, 'pharmacy_invoice_ref' => 'INV2']);
        FinanceReconEntry::create(['order_id' => $o3, 'status' => 'unmatched', 'pharmacy_amount_cents' => 3000, 'payment_amount_cents' => 0, 'pharmacy_invoice_ref' => 'INV3']);

        $m = $this->stats->finance('month');

        $this->assertSame(1, $m['recon_by_status']['matched']);
        $this->assertSame(2, $m['recon_by_status']['unmatched']);
    }

    private function reconOrder(): int
    {
        return \App\Models\Order::create([
            'source_type' => 'test', 'source_ref' => uniqid('', true),
            'patient_id' => User::factory()->create(['role' => UserRole::Patient])->id,
            'status' => 'PaymentReceived', 'total_minor' => 1000, 'service_category' => 'x', 'ordered_at' => now(),
        ])->id;
    }

    public function test_outstanding_aged_pending_payments(): void
    {
        $p = User::factory()->create(['role' => UserRole::Patient]);
        // Fresh pending — counted in amount but not aged.
        Payment::create(['patient_id' => $p->id, 'provider' => 'payfast', 'amount' => 10000, 'currency' => 'ZAR', 'status' => 'pending', 'description' => 'fresh']);
        // Aged pending.
        $old = Payment::create(['patient_id' => $p->id, 'provider' => 'payfast', 'amount' => 20000, 'currency' => 'ZAR', 'status' => 'pending', 'description' => 'old']);
        $old->forceFill(['created_at' => now()->subHours(48)])->save();

        $m = $this->stats->finance('month');

        $this->assertSame(30000, $m['outstanding']['pending_amount_cents']);
        $this->assertSame(1, $m['outstanding']['aged_over_threshold']);
    }
}
