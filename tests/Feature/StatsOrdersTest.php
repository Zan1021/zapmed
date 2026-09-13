<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Stats\StatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T7.4 — order metrics: counts across all 25 statuses (zero-filled), board-lane rollup,
 * fulfilment funnel, and PendingPayment aging.
 */
class StatsOrdersTest extends TestCase
{
    use RefreshDatabase;

    private StatsService $stats;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stats = app(StatsService::class);
    }

    private function order(string $status, array $attr = []): Order
    {
        $patient = User::factory()->create(['role' => UserRole::Patient]);

        return Order::create(array_merge([
            'source_type' => 'test', 'source_ref' => uniqid('', true),
            'patient_id' => $patient->id, 'status' => $status,
            'total_minor' => 45000, 'service_category' => 'weight-loss', 'ordered_at' => now(),
        ], $attr));
    }

    public function test_by_status_zero_fills_all_25_statuses(): void
    {
        $this->order('Delivered');

        $m = $this->stats->orders('month');

        $this->assertCount(25, $m['by_status'], 'every one of the 25 statuses must appear');
        $this->assertSame(1, $m['by_status']['Delivered']);
        $this->assertSame(0, $m['by_status']['PendingPayment']);
        $this->assertSame(1, $m['total']);
    }

    public function test_lane_rollup_groups_statuses(): void
    {
        $this->order('PendingPayment');
        $this->order('PaymentFailed');
        $this->order('Delivered');

        $m = $this->stats->orders('month');

        $this->assertSame(2, $m['by_lane']['awaiting_payment']['count']); // PendingPayment + PaymentFailed
        $this->assertSame(1, $m['by_lane']['fulfilment']['count']);       // Delivered
    }

    public function test_fulfilment_funnel_is_cumulative(): void
    {
        $this->order('PaymentReceived');
        $this->order('Despatched');
        $this->order('Delivered');

        $m = $this->stats->orders('month');
        $f = $m['fulfilment_funnel'];

        // payment_received counts everyone who reached payment or beyond (3);
        // despatched counts despatched+delivered (2); delivered counts delivered (1).
        $this->assertSame(3, $f['payment_received']);
        $this->assertSame(2, $f['despatched']);
        $this->assertSame(1, $f['delivered']);
    }

    public function test_pending_payment_aging(): void
    {
        // Fresh pending — not aged.
        $this->order('PendingPayment', ['ordered_at' => now()->subHour()]);
        // Old pending — aged past the 24h default.
        $this->order('PendingPayment', ['ordered_at' => now()->subHours(48)]);

        $m = $this->stats->orders('month');

        $this->assertSame(2, $m['pending_payment']['total']);
        $this->assertSame(1, $m['pending_payment']['aged_over_threshold']);
        $this->assertSame(24, $m['pending_payment']['threshold_hours']);
    }
}
