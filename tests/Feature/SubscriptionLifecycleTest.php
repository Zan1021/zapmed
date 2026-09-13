<?php

namespace Tests\Feature;

use App\Enums\CycleStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionFollowup;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 6 — Subscription repeat lifecycle. Behaviour, 3-strike, follow-up, and IMPORT-SAFETY.
 */
class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function svc(): SubscriptionLifecycle
    {
        return app(SubscriptionLifecycle::class);
    }

    private function subscription(string $status = 'active'): Subscription
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $plan = \App\Models\SubscriptionPlan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan-' . uniqid(),
            'price' => 29900,
            'billing_cycle' => 'monthly',
            'cycle_frequency' => 1,
        ]);

        return Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => $status,
            'total_paid' => 0,
            'payment_count' => 0,
        ]);
    }

    // ---- cycle scheduling + success ------------------------------------------------------------

    public function test_schedule_next_cycle_is_idempotent_while_one_is_open(): void
    {
        $sub = $this->subscription();

        $a = $this->svc()->scheduleNextCycle($sub);
        $b = $this->svc()->scheduleNextCycle($sub->fresh());

        $this->assertTrue($a->is($b));
        $this->assertSame(1, $sub->cycles()->count());
        $this->assertSame(CycleStatus::Scheduled, $a->status);
    }

    public function test_success_resets_failures_bumps_completed_and_schedules_next(): void
    {
        $sub = $this->subscription();
        $sub->forceFill(['consecutive_failures' => 2])->save();
        $cycle = $this->svc()->scheduleNextCycle($sub);

        $this->svc()->recordSuccess($cycle);

        $sub->refresh();
        $this->assertSame(0, $sub->consecutive_failures);
        $this->assertSame(1, $sub->completed_cycles);
        $this->assertSame(CycleStatus::Fulfilled, $cycle->fresh()->status);
        // A fresh scheduled cycle exists for next time.
        $this->assertSame(1, $sub->cycles()->where('status', CycleStatus::Scheduled->value)->count());
    }

    public function test_mark_placed_links_the_order(): void
    {
        $sub = $this->subscription();
        $cycle = $this->svc()->scheduleNextCycle($sub);
        $order = Order::create(['patient_id' => $sub->user_id, 'status' => 'PendingPayment', 'total_minor' => 100, 'ordered_at' => now()]);

        $this->svc()->markPlaced($cycle, $order);

        $this->assertSame(CycleStatus::Placed, $cycle->fresh()->status);
        $this->assertSame($order->id, $cycle->fresh()->order_id);
    }

    // ---- 3-strike ------------------------------------------------------------------------------

    public function test_three_consecutive_failures_auto_cancels(): void
    {
        $sub = $this->subscription();

        // Strike 1
        $c1 = $this->svc()->scheduleNextCycle($sub);
        $this->svc()->recordFailure($c1, 'card declined');
        $this->assertSame('payment_failed', $sub->fresh()->status);
        $this->assertSame(1, $sub->fresh()->consecutive_failures);

        // Strike 2
        $c2 = $this->svc()->scheduleNextCycle($sub->fresh());
        $this->svc()->recordFailure($c2);
        $this->assertSame('payment_failed', $sub->fresh()->status);
        $this->assertSame(2, $sub->fresh()->consecutive_failures);

        // Strike 3 → auto-cancel
        $c3 = $this->svc()->scheduleNextCycle($sub->fresh());
        $this->svc()->recordFailure($c3);
        $sub->refresh();
        $this->assertSame('cancelled', $sub->status);
        $this->assertSame(3, $sub->consecutive_failures);
        $this->assertNotNull($sub->cancelled_at);
    }

    public function test_success_after_failures_resets_the_strike_count(): void
    {
        $sub = $this->subscription();
        $c1 = $this->svc()->scheduleNextCycle($sub);
        $this->svc()->recordFailure($c1);
        $this->assertSame(1, $sub->fresh()->consecutive_failures);

        $c2 = $this->svc()->scheduleNextCycle($sub->fresh());
        $this->svc()->recordSuccess($c2);
        $this->assertSame(0, $sub->fresh()->consecutive_failures);
    }

    // ---- runner --------------------------------------------------------------------------------

    public function test_runner_only_advances_active_subscriptions(): void
    {
        $active = $this->subscription('active');
        $paused = $this->subscription('paused');

        $dueActive = $active->cycles()->create(['sequence_no' => 1, 'status' => 'scheduled', 'scheduled_for' => now()->subHour()]);
        $duePaused = $paused->cycles()->create(['sequence_no' => 1, 'status' => 'scheduled', 'scheduled_for' => now()->subHour()]);

        $moved = $this->svc()->runDueCycles();

        $this->assertSame(1, $moved);
        $this->assertSame(CycleStatus::Attempted, $dueActive->fresh()->status);
        $this->assertSame(CycleStatus::Scheduled, $duePaused->fresh()->status); // paused → untouched
    }

    public function test_future_cycles_are_not_due(): void
    {
        $sub = $this->subscription();
        $sub->cycles()->create(['sequence_no' => 1, 'status' => 'scheduled', 'scheduled_for' => now()->addWeek()]);

        $this->assertSame(0, $this->svc()->runDueCycles());
    }

    // ---- follow-up -----------------------------------------------------------------------------

    public function test_followup_scheduled_at_180_days_and_idempotent(): void
    {
        $order = Order::create(['patient_id' => User::factory()->create(['role' => UserRole::Patient])->id, 'status' => 'Completed', 'total_minor' => 100, 'ordered_at' => now()]);

        $f1 = $this->svc()->scheduleFollowup($order);
        $f2 = $this->svc()->scheduleFollowup($order);

        $this->assertTrue($f1->is($f2)); // idempotent per order
        $this->assertEqualsWithDelta(180, now()->diffInDays($f1->due_at), 1);
    }

    public function test_due_followups_get_notified(): void
    {
        $order = Order::create(['patient_id' => User::factory()->create(['role' => UserRole::Patient])->id, 'status' => 'Completed', 'total_minor' => 100, 'ordered_at' => now()]);
        $f = $this->svc()->scheduleFollowup($order);
        $f->forceFill(['due_at' => now()->subDay()])->save();

        $count = $this->svc()->markDueFollowupsNotified();

        $this->assertSame(1, $count);
        $this->assertNotNull($f->fresh()->notified_at);
    }

    // ---- pause / resume ------------------------------------------------------------------------

    public function test_pause_and_resume_with_audit_trail(): void
    {
        $sub = $this->subscription('active');

        $this->svc()->pause($sub, now()->addWeek(), 'patient travelling', 1);
        $this->assertSame('paused', $sub->fresh()->status);
        $this->assertSame(1, $sub->pauseEvents()->count());

        $this->svc()->resume($sub->fresh(), 1);
        $this->assertSame('active', $sub->fresh()->status);
        $this->assertNotNull($sub->pauseEvents()->first()->resumed_at);
    }

    public function test_cannot_pause_a_non_active_subscription(): void
    {
        $this->expectException(RuntimeException::class);
        $this->svc()->pause($this->subscription('cancelled'));
    }

    // ---- IMPORT SAFETY (the standing non-negotiable) -------------------------------------------

    public function test_imported_cycles_must_be_terminal_and_never_schedule_a_next(): void
    {
        $sub = $this->subscription('cancelled');

        $imported = $this->svc()->recordImportedCycle($sub, 1, CycleStatus::Fulfilled, [
            'fulfilled_at' => now()->subMonths(3),
        ]);

        $this->assertSame(CycleStatus::Fulfilled, $imported->status);
        // Reconstructing history must NOT create a live scheduled cycle.
        $this->assertSame(0, $sub->cycles()->where('status', CycleStatus::Scheduled->value)->count());
        // Nor wake the subscription up.
        $this->assertSame('cancelled', $sub->fresh()->status);
    }

    public function test_importing_a_non_terminal_cycle_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->svc()->recordImportedCycle($this->subscription(), 1, CycleStatus::Scheduled);
    }

    public function test_runner_never_touches_imported_cancelled_subscriptions(): void
    {
        // Simulate an imported subscription: cancelled, with a (defensively) scheduled-looking cycle.
        $sub = $this->subscription('cancelled');
        $cycle = $sub->cycles()->create(['sequence_no' => 1, 'status' => 'scheduled', 'scheduled_for' => now()->subDay()]);

        $moved = $this->svc()->runDueCycles();

        $this->assertSame(0, $moved);
        $this->assertSame(CycleStatus::Scheduled, $cycle->fresh()->status); // untouched — no charge path woken
    }
}
