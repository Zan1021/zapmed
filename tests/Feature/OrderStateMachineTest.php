<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Orders\OrderStatusMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 2 — Order aggregate + 25-status state machine + immutable history.
 * specs/contro-rebuild/01-system-design-dossier.md §4, 03-contro-import-blueprint.md §2.4/2.5.
 */
class OrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed the transition rules into the (in-memory) test DB.
        $this->seed(\Database\Seeders\OrderStatusTransitionSeeder::class);
    }

    // ---- config / state-machine definition ------------------------------------------------------

    public function test_there_are_exactly_25_statuses_with_verbatim_contro_spelling(): void
    {
        $statuses = OrderStatusMachine::statuses();
        $this->assertCount(25, $statuses);
        $this->assertContains('PendingConsulation', $statuses); // sic — Contro misspelling preserved
        $this->assertContains('PaymentReceived', $statuses);    // restored
        $this->assertContains('TwoRepeatFailures', $statuses);  // restored
        // Ensure we did NOT "helpfully" correct the spelling.
        $this->assertNotContains('PendingConsultation', $statuses);
    }

    public function test_rxhub_event_map_has_five_codes(): void
    {
        $this->assertSame('PharmacyProcessing', OrderStatusMachine::statusForRxhubEvent('New'));
        $this->assertSame('PreparingMedication', OrderStatusMachine::statusForRxhubEvent('ScriptProcessed'));
        $this->assertSame('ClaimRejected', OrderStatusMachine::statusForRxhubEvent('ScriptRejected'));
        $this->assertSame('Despatched', OrderStatusMachine::statusForRxhubEvent('ScriptDispatched'));
        $this->assertSame('Delivered', OrderStatusMachine::statusForRxhubEvent('ScriptDelivered'));
        $this->assertNull(OrderStatusMachine::statusForRxhubEvent('Nonsense'));
        $this->assertCount(5, config('orders.rxhub_event_map'));
    }

    public function test_transition_validation_allows_known_and_rejects_unknown(): void
    {
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('PendingBooking', 'PendingConsulation', 'calendar'));
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('InReview', 'Processing', 'doctor'));
        // Right pair, wrong trigger.
        $this->assertFalse(OrderStatusMachine::isTransitionAllowed('InReview', 'Processing', 'patient'));
        // Nonsense pair.
        $this->assertFalse(OrderStatusMachine::isTransitionAllowed('Delivered', 'PendingPayment', 'admin'));
    }

    public function test_allowed_next_statuses_lists_reachable_states(): void
    {
        $next = OrderStatusMachine::allowedNextStatuses('InReview');
        $this->assertContains('AwaitingInformation', $next);
        $this->assertContains('Processing', $next);
        $this->assertContains('Cancelled', $next);
    }

    // ---- seeded rules as data -------------------------------------------------------------------

    public function test_transition_rules_are_seeded_as_data(): void
    {
        $this->assertSame(
            count(config('orders.transitions')),
            DB::table('order_status_transitions')->count()
        );
        $this->assertSame(5, DB::table('order_rxhub_event_map')->count());
    }

    // ---- Order model behaviour ------------------------------------------------------------------

    public function test_order_gets_zm_reference_on_create(): void
    {
        $order = Order::create(['patient_id' => User::factory()->create()->id]);
        $this->assertMatchesRegularExpression('/^ZM-\d{4}-\d{6}$/', $order->reference);
        $this->assertSame('PendingPayment', $order->status); // default
    }

    public function test_guarded_transition_updates_status_and_writes_history(): void
    {
        $order = Order::create(['patient_id' => User::factory()->create()->id]);

        $history = $order->transitionTo('PendingBooking', 'payment', ['notes' => 'paid']);

        $this->assertSame('PendingBooking', $order->fresh()->status);
        $this->assertSame('PendingPayment', $history->from_status);
        $this->assertSame('PendingBooking', $history->to_status);
        $this->assertSame('payment', $history->trigger_type);
        $this->assertSame(1, $order->statusHistory()->count());
    }

    public function test_guarded_transition_rejects_illegal_step(): void
    {
        $order = Order::create(['patient_id' => User::factory()->create()->id]);

        $this->expectException(\RuntimeException::class);
        $order->transitionTo('Delivered', 'rxhub'); // not reachable from PendingPayment
    }

    public function test_status_history_is_immutable(): void
    {
        $order = Order::create(['patient_id' => User::factory()->create()->id]);
        $history = $order->transitionTo('PendingBooking', 'payment');

        $this->expectException(\RuntimeException::class);
        $history->update(['notes' => 'tampered']);
    }

    public function test_imported_status_bypasses_validation_for_verbatim_contro_history(): void
    {
        $order = Order::create(['patient_id' => User::factory()->create()->id]);

        // A Contro step that would be illegal under current rules must still land (quarantine is the
        // reconciler's concern, not a hard reject).
        $history = $order->recordImportedStatus('SomeOldControStatus', 'Delivered', 'rxhub', [
            'upstream_id' => 'osh_1',
            'occurred_at' => now()->subMonths(3),
        ]);

        $this->assertSame('Delivered', $order->fresh()->status);
        $this->assertSame('osh_1', $history->upstream_id);
    }

    // ---- Task 1 crosswalk tripwire: orders tables must carry crosswalk columns ------------------

    public function test_order_tables_have_crosswalk_columns(): void
    {
        foreach (['orders', 'order_items', 'order_status_history'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertTrue(
                Schema::hasColumns($table, ['upstream_id', 'upstream_source', 'upstream_synced_at']),
                "{$table} missing crosswalk columns"
            );
        }
    }
}
