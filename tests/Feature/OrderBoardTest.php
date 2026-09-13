<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\OrderBoard;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\Orders\OrderStatusMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 2 — Order Board / Kanban (Livewire admin component).
 */
class OrderBoardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function makeOrder(string $status, array $attrs = []): Order
    {
        return Order::create(array_merge([
            'patient_id' => User::factory()->create(['role' => UserRole::Patient])->id,
            'status' => $status,
            'total_minor' => 12345,
            'ordered_at' => now(),
        ], $attrs));
    }

    public function test_admin_can_view_the_order_board(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.order-board'))
            ->assertOk()
            ->assertSeeLivewire(OrderBoard::class);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $patient = User::factory()->create(['role' => UserRole::Patient]);
        $this->actingAs($patient)
            ->get(route('admin.order-board'))
            ->assertForbidden();
    }

    public function test_every_status_maps_to_exactly_one_lane(): void
    {
        // Board coverage guard: a status that isn't in a lane would silently vanish from the board.
        $lanes = OrderStatusMachine::boardLanes();
        $mapped = [];
        foreach ($lanes as $lane) {
            foreach ($lane['statuses'] as $status) {
                $this->assertArrayNotHasKey($status, $mapped, "Status {$status} is in more than one lane.");
                $mapped[$status] = true;
            }
        }

        foreach (OrderStatusMachine::statuses() as $status) {
            $this->assertArrayHasKey($status, $mapped, "Status {$status} is not assigned to any board lane.");
        }

        // And no lane references a status the machine doesn't know about.
        foreach (array_keys($mapped) as $status) {
            $this->assertTrue(OrderStatusMachine::isValidStatus($status), "Lane references unknown status {$status}.");
        }
    }

    public function test_orders_are_grouped_into_the_correct_lanes(): void
    {
        $this->makeOrder('PendingPayment');       // awaiting_payment
        $this->makeOrder('InReview');             // clinical_review
        $this->makeOrder('Despatched');           // fulfilment

        Livewire::actingAs($this->admin())
            ->test(OrderBoard::class)
            ->assertSet('selectedOrderId', null)
            ->tap(function ($t) {
                $lanes = $t->instance()->lanes();
                $this->assertCount(1, $lanes['awaiting_payment']);
                $this->assertCount(1, $lanes['clinical_review']);
                $this->assertCount(1, $lanes['fulfilment']);
                $this->assertCount(0, $lanes['pharmacy']);
            });
    }

    public function test_lane_counts_reflect_totals(): void
    {
        $this->makeOrder('PendingPayment');
        $this->makeOrder('PaymentFailed');   // same lane (awaiting_payment)

        Livewire::actingAs($this->admin())
            ->test(OrderBoard::class)
            ->tap(fn ($t) => $this->assertSame(2, $t->instance()->laneCounts()['awaiting_payment']));
    }

    public function test_service_line_filter_narrows_the_board(): void
    {
        $this->makeOrder('PendingPayment', ['service_category' => 'weight-loss']);
        $this->makeOrder('PendingPayment', ['service_category' => 'hair']);

        Livewire::actingAs($this->admin())
            ->test(OrderBoard::class)
            ->set('serviceLine', 'weight-loss')
            ->tap(fn ($t) => $this->assertSame(1, $t->instance()->laneCounts()['awaiting_payment']));
    }

    public function test_assignee_filter_narrows_by_doctor(): void
    {
        $doc = User::factory()->create(['role' => UserRole::Doctor]);
        $this->makeOrder('InReview', ['doctor_id' => $doc->id]);
        $this->makeOrder('InReview'); // no doctor

        Livewire::actingAs($this->admin())
            ->test(OrderBoard::class)
            ->set('assignee', (string) $doc->id)
            ->tap(fn ($t) => $this->assertSame(1, $t->instance()->laneCounts()['clinical_review']));
    }

    public function test_selecting_an_order_loads_its_detail_with_relations(): void
    {
        $order = $this->makeOrder('InReview', ['pharmacy_script_ref' => null]);
        OrderItem::create([
            'order_id' => $order->id, 'description' => 'Semaglutide 0.25mg',
            'quantity' => 1, 'unit_price_minor' => 99900, 'line_total_minor' => 99900,
        ]);
        Payment::create([
            'patient_id' => $order->patient_id, 'order_id' => $order->id,
            'provider' => 'payfast', 'amount' => 99900, 'status' => 'completed',
        ]);

        Livewire::actingAs($this->admin())
            ->test(OrderBoard::class)
            ->call('selectOrder', $order->id)
            ->assertSet('selectedOrderId', $order->id)
            ->assertSee('Semaglutide 0.25mg')
            ->assertSee('Status history');
    }

    public function test_drawer_offers_only_legal_next_statuses(): void
    {
        $order = $this->makeOrder('InReview');

        Livewire::actingAs($this->admin())
            ->test(OrderBoard::class)
            ->call('selectOrder', $order->id)
            ->tap(function ($t) {
                $allowed = $t->instance()->allowedNextStatuses();
                // From InReview the machine allows AwaitingInformation, Processing, Cancelled.
                sort($allowed);
                $this->assertSame(['AwaitingInformation', 'Cancelled', 'Processing'], $allowed);
            });
    }

    public function test_applying_a_legal_transition_moves_the_order_and_records_history(): void
    {
        $order = $this->makeOrder('InReview');

        Livewire::actingAs($this->admin())
            ->test(OrderBoard::class)
            ->call('selectOrder', $order->id)
            ->set('transitionTo', 'Processing')
            ->set('transitionNotes', 'Approved on board')
            ->call('applyTransition');

        $order->refresh();
        $this->assertSame('Processing', $order->status);
        $this->assertSame(1, $order->statusHistory()->count());
        $history = $order->statusHistory()->first();
        $this->assertSame('InReview', $history->from_status);
        $this->assertSame('Processing', $history->to_status);
        $this->assertSame('doctor', $history->trigger_type); // resolved from the rules for this pair
        $this->assertSame('Approved on board', $history->notes);
    }

    public function test_admin_wildcard_transition_uses_admin_trigger(): void
    {
        $order = $this->makeOrder('Processing');

        Livewire::actingAs($this->admin())
            ->test(OrderBoard::class)
            ->call('selectOrder', $order->id)
            ->set('transitionTo', 'Paused')
            ->call('applyTransition');

        $order->refresh();
        $this->assertSame('Paused', $order->status);
        $this->assertSame('admin', $order->statusHistory()->first()->trigger_type);
    }

    public function test_illegal_transition_is_refused_and_order_unchanged(): void
    {
        $order = $this->makeOrder('PendingPayment');

        Livewire::actingAs($this->admin())
            ->test(OrderBoard::class)
            ->call('selectOrder', $order->id)
            // Force an illegal target that the picker would never offer.
            ->set('transitionTo', 'Delivered')
            ->call('applyTransition');

        $order->refresh();
        $this->assertSame('PendingPayment', $order->status);
        $this->assertSame(0, $order->statusHistory()->count());
    }
}
