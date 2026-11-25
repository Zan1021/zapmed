<?php

namespace Tests\Feature;

use App\Enums\CycleStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Orders\OrderStatusMachine;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A failed repeat payment moves the order on the board, step by step, with a history row for each step (Craig's guide 3.2). */
class RepeatFailureOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 09:31', 'Africa/Johannesburg'));
    }

    private function lifecycle(): SubscriptionLifecycle
    {
        return app(SubscriptionLifecycle::class);
    }

    /** @return array{0: Subscription, 1: Order} */
    private function repeatOrder(string $orderStatus = 'PharmacyProcessing'): array
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $plan = SubscriptionPlan::create(['name' => 'Repeat', 'slug' => 'rep-'.uniqid(), 'price' => 45000, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $sub = Subscription::create(['user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'charge_mode' => 'adhoc', 'payfast_token' => 't']);
        $order = Order::create(['patient_id' => $user->id, 'status' => $orderStatus, 'total_minor' => 45000, 'ordered_at' => now(), 'source_type' => 'subscription', 'source_ref' => (string) $sub->id]);

        return [$sub, $order];
    }

    /** A due repeat that is then refused by the card. */
    private function declineRepeat(Subscription $sub): void
    {
        $cycle = $sub->cycles()->whereIn('status', ['scheduled', 'attempted'])->first() ?? $this->lifecycle()->scheduleNextCycle($sub, now()->subMinute());
        $cycle->update(['status' => CycleStatus::Attempted->value]);
        $this->lifecycle()->recordFailure($cycle->fresh(), 'card declined');
    }

    private function trail(Order $order): array
    {
        return $order->statusHistory()->pluck('to_status')->all();
    }

    public function test_each_failed_repeat_moves_the_order_and_the_third_pauses_it(): void
    {
        [$sub, $order] = $this->repeatOrder();

        $this->declineRepeat($sub->fresh());
        $this->assertSame('RepeatPaymentFailed', $order->fresh()->status);

        $this->declineRepeat($sub->fresh());
        $this->assertSame('TwoRepeatFailures', $order->fresh()->status);

        $this->declineRepeat($sub->fresh());
        $this->assertSame('Paused', $order->fresh()->status);
        $this->assertSame('paused', $sub->fresh()->status);
        $this->assertSame(['RepeatPaymentFailed', 'TwoRepeatFailures', 'ThreeRepeatFailures', 'Paused'], $this->trail($order));
        $this->assertTrue(OrderStatusMachine::blocksRepeat('Paused'), 'A paused order blocks the next repeat.');
        $this->assertSame('Paused', $this->lifecycle()->repeatBlockedBy($sub->fresh()));
    }

    public function test_the_history_says_what_happened(): void
    {
        [$sub, $order] = $this->repeatOrder();

        $this->declineRepeat($sub->fresh());

        $row = $order->statusHistory()->firstOrFail();
        $this->assertSame(['PharmacyProcessing', 'RepeatPaymentFailed', 'payment'], [$row->from_status, $row->to_status, $row->trigger_type]);
        $this->assertStringContainsString('attempt 1 of 3', $row->notes);
    }

    public function test_the_old_cancel_rule_cancels_the_order_instead(): void
    {
        config()->set('subscriptions.after_max_failures', 'cancel');
        [$sub, $order] = $this->repeatOrder();

        foreach (range(1, 3) as $i) {
            $this->declineRepeat($sub->fresh());
        }

        $this->assertSame('Cancelled', $order->fresh()->status);
        $this->assertSame('cancelled', $sub->fresh()->status);
    }

    public function test_a_subscription_with_no_order_is_unaffected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $plan = SubscriptionPlan::create(['name' => 'Repeat', 'slug' => 'rep-x', 'price' => 45000, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $sub = Subscription::create(['user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => 'active']);

        $this->declineRepeat($sub->fresh());

        $this->assertSame('payment_failed', $sub->fresh()->status);
        $this->assertSame(0, Order::count());
    }

    public function test_a_cancelled_order_is_never_moved_again(): void
    {
        [$sub, $order] = $this->repeatOrder('Cancelled');

        $this->declineRepeat($sub->fresh());

        $this->assertSame('Cancelled', $order->fresh()->status);
        $this->assertSame([], $this->trail($order));
    }

    public function test_a_valid_step_uses_the_normal_guarded_transition(): void
    {
        [, $order] = $this->repeatOrder('RepeatPaymentFailed');

        $order->recordSystemStatus('TwoRepeatFailures', 'payment', 'second failure');

        $this->assertSame('TwoRepeatFailures', $order->fresh()->status);
        $this->assertSame('second failure', $order->statusHistory()->firstOrFail()->notes, 'A step the machine lists keeps its own note.');
        $this->assertSame(0, $order->statusHistory()->count() - 1);
    }
}