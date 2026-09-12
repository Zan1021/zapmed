<?php

namespace Tests\Feature;

use App\Models\UpstreamIngestedRow;
use App\Services\Contro\ControParityReport;
use App\Services\Contro\ControReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 8 — reconcile verification / parity report (blueprint §4 step 2).
 */
class ControParityReportTest extends TestCase
{
    use RefreshDatabase;

    private function stage(string $entitySet, string $id, array $payload): void
    {
        UpstreamIngestedRow::create(['entity_set' => $entitySet, 'upstream_id' => $id, 'payload' => $payload]);
    }

    public function test_parity_counts_staged_reconciled_and_clean(): void
    {
        $this->stage('products', 'p1', ['id' => 'p1', 'productName' => 'A', 'price' => 10.0, 'isEnabled' => true]);
        $this->stage('products', 'p2', ['id' => 'p2', 'productName' => 'B', 'price' => 20.0, 'isEnabled' => true]);

        (new ControReconciler())->reconcileProducts();

        $report = new ControParityReport();
        $counts = $report->build();

        $this->assertSame(2, $counts['products']['staged']);
        $this->assertSame(2, $counts['products']['reconciled']);
        $this->assertSame(0, $counts['products']['quarantined']);
        $this->assertSame(0, $counts['products']['unaccounted']);
    }

    public function test_parity_reflects_quarantined_rows(): void
    {
        // A status-history row with no matching order gets quarantined, not reconciled.
        $this->stage('order_status_history', 'osh_1', ['id' => 'osh_1', 'orderNumber' => 'NONE', 'status' => 'Delivered']);

        (new ControReconciler())->reconcileOrderStatusHistory();

        $counts = (new ControParityReport())->build();
        $this->assertSame(1, $counts['order_status_history']['staged']);
        $this->assertSame(0, $counts['order_status_history']['reconciled']);
        $this->assertSame(1, $counts['order_status_history']['quarantined']);
        $this->assertSame(0, $counts['order_status_history']['unaccounted']); // 1 - 0 - 1 = 0
    }

    public function test_unaccounted_detected_when_nothing_reconciled(): void
    {
        // Staged but never reconciled -> unaccounted -> not clean.
        $this->stage('products', 'p9', ['id' => 'p9', 'productName' => 'Z', 'price' => 5.0]);

        $report = new ControParityReport();
        $this->assertSame(1, $report->build()['products']['unaccounted']);
        $this->assertFalse($report->isClean());
    }

    public function test_quarantine_summary_groups_by_entity_and_reason(): void
    {
        $this->stage('orders', 'o_bad1', ['id' => 'o_bad1', 'orderNumber' => 'B1', 'status' => 'MadeUp']);
        $this->stage('orders', 'o_bad2', ['id' => 'o_bad2', 'orderNumber' => 'B2', 'status' => 'AlsoMadeUp']);

        (new ControReconciler())->reconcileOrders();

        $summary = (new ControParityReport())->quarantineSummary();
        $this->assertNotEmpty($summary);
        $row = collect($summary)->firstWhere('reason', 'unknown_status');
        $this->assertNotNull($row);
        $this->assertSame('orders', $row->entity_set);
        $this->assertSame(2, (int) $row->total);
    }

    public function test_clean_report_when_all_reconciled(): void
    {
        $this->stage('patients', 'h1', ['userHash' => 'h1', 'firstName' => 'A', 'email' => 'a@example.com']);
        (new ControReconciler())->reconcilePatients();

        $this->assertTrue((new ControParityReport())->isClean());
    }
}
