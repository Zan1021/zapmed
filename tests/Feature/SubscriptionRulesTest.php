<?php

namespace Tests\Feature;

use App\Enums\CycleStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Commerce\LiveOrderBridge;
use App\Services\Orders\OrderStatusMachine;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Craig's build guide, phase 1 "money and lifecycle correctness": 09:30 retries on three successive days, the third
 * failure PAUSES, the first-repeat buffer, pause and cancel with a reason and initiator, 90 day continue window,
 * the four new statuses, repeats blocked while a follow-up is pending, second no-show cancels.
 *
 * The lifecycle service only records outcomes and schedules bookkeeping rows. None of this charges anything.
 */
class SubscriptionRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday 25 Nov 2026, 11:00 in South Africa.
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
    }

    private function svc(): SubscriptionLifecycle
    {
        return app(SubscriptionLifecycle::class);
    }

    private function subscription(string $status = 'active'): Subscription
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $plan = SubscriptionPlan::create([
            'name' => 'Repeat plan', 'slug' => 'rules-'.uniqid(), 'price' => 29900,
            'billing_cycle' => 'monthly', 'cycle_frequency' => 1,
        ]);

        return Subscription::create([
            'user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => $status, 'total_paid' => 0, 'payment_count' => 0,
        ]);
    }

    private function staffId(): int
    {
        return User::factory()->create(['role' => UserRole::Admin])->id;
    }

    /** Local (South African) wall clock of a stored UTC time. */
    private function sast($time): string
    {
        return CarbonImmutable::parse($time)->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i');
    }

    // --- Retries and the third failure ---------------------------------------------------------------------------

    public function test_a_failed_repeat_is_retried_at_0930_the_next_day_and_the_day_after(): void
    {
        $sub = $this->subscription();
        $c1 = $this->svc()->scheduleNextCycle($sub, now());

        $this->svc()->recordFailure($c1, 'declined');
        $retry1 = $sub->cycles()->where('status', 'scheduled')->firstOrFail();
        $this->assertSame('2026-11-26 09:30', $this->sast($retry1->scheduled_for), 'Day 2 at 09:30 SA');
        $this->assertSame(['retry_of' => $c1->id, 'attempt' => 2], $retry1->metadata);

        $this->travel(1)->days();
        $this->svc()->recordFailure($retry1, 'declined');
        $retry2 = $sub->cycles()->where('status', 'scheduled')->firstOrFail();
        $this->assertSame('2026-11-27 09:30', $this->sast($retry2->scheduled_for), 'Day 3 at 09:30 SA');
        $this->assertSame(3, $retry2->metadata['attempt']);
        $this->assertSame('payment_failed', $sub->fresh()->status);
    }

    public function test_the_third_failure_pauses_with_a_system_reason_and_stops_retrying(): void
    {
        $sub = $this->subscription();
        $cycle = $this->svc()->scheduleNextCycle($sub, now());

        foreach ([1, 2, 3] as $n) {
            $this->svc()->recordFailure($cycle, 'declined');
            $cycle = $sub->cycles()->where('status', 'scheduled')->first() ?? $cycle;
        }

        $sub->refresh();
        $this->assertSame('paused', $sub->status);
        $this->assertSame(3, $sub->consecutive_failures);
        $this->assertNull($sub->cancelled_at);
        $this->assertSame(0, $sub->cycles()->where('status', 'scheduled')->count(), 'No fourth attempt is scheduled.');

        $event = $sub->pauseEvents()->first();
        $this->assertSame('system', $event->initiator);
        $this->assertNull($event->paused_by);
        $this->assertStringContainsString('Three failed repeat payments', $event->paused_reason);
        $this->assertNull($event->resumed_at, 'Waiting for the patient to respond.');
    }

    public function test_the_old_cancel_rule_can_be_switched_back_on(): void
    {
        config(['subscriptions.after_max_failures' => 'cancel']);
        $sub = $this->subscription();
        $sub->forceFill(['consecutive_failures' => 2])->save();
        $cycle = $this->svc()->scheduleNextCycle($sub->fresh(), now());

        $this->svc()->recordFailure($cycle, 'declined');

        $sub->refresh();
        $this->assertSame('cancelled', $sub->status);
        $this->assertSame('system', $sub->cancelled_initiator);
    }

    public function test_a_success_after_a_failure_clears_the_strikes_and_nothing_pauses(): void
    {
        $sub = $this->subscription();
        $c1 = $this->svc()->scheduleNextCycle($sub, now());
        $this->svc()->recordFailure($c1);
        $retry = $sub->cycles()->where('status', 'scheduled')->firstOrFail();

        $this->svc()->recordSuccess($retry);

        $sub->refresh();
        $this->assertSame('active', $sub->status);
        $this->assertSame(0, $sub->consecutive_failures);
        $this->assertSame(0, $sub->pauseEvents()->count());
    }

    public function test_a_paused_order_is_never_charged_by_the_runner(): void
    {
        $sub = $this->subscription();
        $cycle = $this->svc()->scheduleNextCycle($sub, now()->subHour());
        $this->svc()->recordFailure($cycle);
        $sub->forceFill(['consecutive_failures' => 2])->save();
        $retry = $sub->cycles()->where('status', 'scheduled')->firstOrFail();
        $retry->update(['scheduled_for' => now()->subMinute()]);
        $this->svc()->recordFailure($retry);
        $third = $sub->cycles()->where('status', 'scheduled')->first();

        $this->assertSame('paused', $sub->fresh()->status);
        $this->assertSame(0, $this->svc()->runDueCycles());
        $this->assertNull($third);
    }

    public function test_the_runner_retries_a_payment_failed_order_when_the_retry_is_due(): void
    {
        $sub = $this->subscription();
        $c1 = $this->svc()->scheduleNextCycle($sub, now());
        $this->svc()->recordFailure($c1);
        $retry = $sub->cycles()->where('status', 'scheduled')->firstOrFail();

        $this->assertSame(0, $this->svc()->runDueCycles(), '09:30 tomorrow is not due yet.');

        $this->travelTo(CarbonImmutable::parse('2026-11-26 09:31', 'Africa/Johannesburg'));
        $this->assertSame(1, $this->svc()->runDueCycles());
        $this->assertSame(CycleStatus::Attempted, $retry->fresh()->status);
    }

    // --- Charge time and the first repeat --------------------------------------------------------------------------

    public function test_the_next_cycle_is_scheduled_at_0930_south_african_time(): void
    {
        $sub = $this->subscription();

        $cycle = $this->svc()->scheduleNextCycle($sub);

        $this->assertSame('2026-12-25 09:30', $this->sast($cycle->scheduled_for));   // plan: 1 month = 30 days from 25 Nov
    }

    public function test_a_28_day_pack_first_repeats_on_day_23_then_every_28_days(): void
    {
        $sub = $this->subscription();

        $first = $this->svc()->scheduleFirstRepeat($sub, CarbonImmutable::parse('2026-11-02 14:00', 'Africa/Johannesburg'), 28);

        $this->assertSame('2026-11-25 09:30', $this->sast($first->scheduled_for), '2 Nov + 23 days');
        $this->assertSame(28, $sub->fresh()->repeat_cycle_days);

        $this->svc()->recordSuccess($first);
        $next = $sub->cycles()->where('status', 'scheduled')->firstOrFail();
        $this->assertSame('2026-12-23 09:30', $this->sast($next->scheduled_for), 'Later repeats are every 28 days (from today).');
    }

    public function test_the_buffer_is_configurable_and_a_pack_must_have_days(): void
    {
        // With no repeat cycle set up for this pack length, the delivery buffer in config decides (Operations can set cycles instead).
        \App\Models\RepeatCycle::query()->delete();
        config(['subscriptions.first_repeat_buffer_days' => 3]);
        $sub = $this->subscription();

        $first = $this->svc()->scheduleFirstRepeat($sub, CarbonImmutable::parse('2026-11-02 08:00', 'Africa/Johannesburg'), 30);
        $this->assertSame('2026-11-29 09:30', $this->sast($first->scheduled_for), '30 - 3 = day 27');

        $this->expectException(RuntimeException::class);
        $this->svc()->scheduleFirstRepeat($this->subscription(), now(), 0);
    }

    public function test_the_runner_is_scheduled_for_0930_south_african_time(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'subscriptions:run-due'));

        $this->assertNotNull($event);
        $this->assertSame('30 9 * * *', $event->expression, 'Laravel keeps the time in the schedule timezone (07:30 UTC on the server).');
        $this->assertSame('Africa/Johannesburg', (string) $event->timezone);
    }

    // --- Pause, continue, cancel -----------------------------------------------------------------------------------

    public function test_a_patient_must_give_a_reason_to_pause_and_the_initiator_is_recorded(): void
    {
        $sub = $this->subscription();

        try {
            $this->svc()->pause($sub, null, '  ', null, 'patient');
            $this->fail('A patient pause needs a reason.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('why', $e->getMessage());
        }
        $this->assertSame('active', $sub->fresh()->status);

        $this->svc()->pause($sub, null, 'Going overseas', null, 'patient');
        $event = $sub->pauseEvents()->first();
        $this->assertSame(['patient', 'Going overseas'], [$event->initiator, $event->paused_reason]);
    }

    public function test_operations_and_system_initiators_are_told_apart(): void
    {
        $a = $this->subscription();
        $this->svc()->pause($a, null, 'Doctor asked', $this->staffId());
        $this->assertSame('operations', $a->pauseEvents()->first()->initiator, 'A staff user id means operations.');

        $b = $this->subscription();
        $this->svc()->pause($b, null, 'Nightly job');
        $this->assertSame('system', $b->pauseEvents()->first()->initiator);

        $this->expectException(RuntimeException::class);
        $this->svc()->pause($this->subscription(), null, 'x', null, 'robot');
    }

    public function test_pausing_cancels_uncharged_cycles_but_keeps_them_in_history(): void
    {
        $sub = $this->subscription();
        $open = $this->svc()->scheduleNextCycle($sub);

        $this->svc()->pause($sub, null, 'Away', $this->staffId());

        $open->refresh();
        $this->assertSame(CycleStatus::Cancelled, $open->status);
        $this->assertSame('paused', $open->metadata['cancelled_because']);
        $this->assertSame(1, $sub->cycles()->count(), 'Nothing is deleted.');
    }

    public function test_a_paused_order_can_continue_within_90_days_and_the_cycle_starts_again(): void
    {
        $sub = $this->subscription();
        $sub->forceFill(['repeat_cycle_days' => 28, 'consecutive_failures' => 3])->save();
        $this->svc()->scheduleNextCycle($sub->fresh());
        $this->svc()->pause($sub->fresh(), null, 'Away', null, 'patient');

        $this->travel(89)->days();
        $this->svc()->resume($sub->fresh(), $this->staffId());

        $sub->refresh();
        $this->assertSame('active', $sub->status);
        $this->assertSame(0, $sub->consecutive_failures, 'The patient responded: strikes cleared.');
        $this->assertNotNull($sub->pauseEvents()->first()->resumed_at);
        $this->assertSame(2, $sub->cycles()->count(), 'The old (cancelled) cycle stays in history, a new one is added.');
        $new = $sub->cycles()->where('status', 'scheduled')->firstOrFail();
        $this->assertSame('09:30', substr($this->sast($new->scheduled_for), 11), 'Like a first repeat: pack 28 - 5 buffer = 23 days later at 09:30.');
        $this->assertSame(now()->addDays(23)->setTimezone('Africa/Johannesburg')->format('Y-m-d'), substr($this->sast($new->scheduled_for), 0, 10));
    }

    public function test_after_90_days_a_paused_order_cannot_be_continued_without_an_override(): void
    {
        $sub = $this->subscription();
        $this->svc()->pause($sub, null, 'Away', null, 'patient');
        $this->travel(91)->days();

        try {
            $this->svc()->resume($sub->fresh());
            $this->fail('The 90 day window has passed.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('90 days', $e->getMessage());
        }
        $this->assertSame('paused', $sub->fresh()->status);

        $this->svc()->resume($sub->fresh(), $this->staffId(), true);   // Operations override
        $this->assertSame('active', $sub->fresh()->status);
    }

    public function test_a_cancel_needs_a_reason_records_who_and_never_runs_again(): void
    {
        $sub = $this->subscription();
        $open = $this->svc()->scheduleNextCycle($sub, now()->subHour());

        try {
            $this->svc()->cancel($sub, ' ', 'patient');
            $this->fail('A cancel needs a reason.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->svc()->cancel($sub, 'Too expensive', 'patient');

        $sub->refresh();
        $this->assertSame(['cancelled', 'patient', 'Too expensive'], [$sub->status, $sub->cancelled_initiator, $sub->cancellation_reason]);
        $this->assertSame(CycleStatus::Cancelled, $open->fresh()->status);
        $this->assertSame(0, $this->svc()->runDueCycles());

        $this->expectException(RuntimeException::class);
        $this->svc()->cancel($sub, 'Again', 'patient');
    }

    public function test_cancelling_a_paused_order_closes_the_open_pause(): void
    {
        $sub = $this->subscription();
        $staff = $this->staffId();
        $this->svc()->pause($sub, null, 'Away', $staff);

        $this->svc()->cancel($sub->fresh(), 'Not coming back', 'operations', $staff);

        $this->assertNotNull($sub->pauseEvents()->first()->resumed_at);
        $this->assertSame($staff, $sub->fresh()->cancelled_by);
    }

    public function test_the_membership_cancel_records_the_patient_as_initiator(): void
    {
        $sub = $this->subscription();

        $sub->cancel('Moving');

        $this->assertSame('patient', $sub->fresh()->cancelled_initiator);
        $this->assertSame('Moving', $sub->fresh()->cancellation_reason);
    }

    // --- Statuses, blocking and no-shows ---------------------------------------------------------------------------

    public function test_the_four_new_statuses_exist_sit_on_the_board_and_connect(): void
    {
        foreach (['PendingBloodTests', 'PendingReviewFeedback', 'PendingFollowUpRequest', 'PendingFollowUpProfileBook'] as $status) {
            $this->assertTrue(OrderStatusMachine::isValidStatus($status), $status);
            $this->assertNotNull(OrderStatusMachine::laneForStatus($status), "{$status} is on the board");
        }

        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('InReview', 'PendingBloodTests', 'doctor'));
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('PendingBloodTests', 'PendingReviewFeedback', 'patient'));
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('PendingReviewFeedback', 'InReview', 'doctor'));
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('Delivered', 'PendingFollowUpRequest', 'doctor'));
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('PendingFollowUpRequest', 'PendingFollowUpProfileBook', 'patient'));
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('PendingFollowUpProfileBook', 'PendingConsulation', 'calendar'));
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('PendingFollowUpRequest', 'Processing', 'doctor'));
        // A patient cannot clear a doctor's follow-up request.
        $this->assertFalse(OrderStatusMachine::isTransitionAllowed('PendingFollowUpRequest', 'Processing', 'patient'));
    }

    public function test_the_third_repeat_failure_leads_to_paused_and_a_patient_can_continue_or_cancel(): void
    {
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('ThreeRepeatFailures', 'Paused', 'system_timer'));
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('Paused', 'Processing', 'patient'));
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('Paused', 'Cancelled', 'patient'));
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('ThreeRepeatFailures', 'Cancelled', 'system_timer'), 'Kept so imported Contro history still validates.');
    }

    public function test_which_statuses_block_a_repeat(): void
    {
        foreach (['Paused', 'Cancelled', 'ThreeRepeatFailures', 'PendingFollowUp', 'PendingFollowUpBooking', 'PendingFollowUpRequest', 'PendingFollowUpProfileBook'] as $s) {
            $this->assertTrue(OrderStatusMachine::blocksRepeat($s), $s);
        }
        foreach (['Processing', 'Delivered', 'Completed', 'PendingBloodTests', 'PendingReviewFeedback', 'InReview'] as $s) {
            $this->assertFalse(OrderStatusMachine::blocksRepeat($s), $s);
        }
    }

    public function test_a_pending_follow_up_request_holds_back_a_due_repeat_until_the_doctor_submits(): void
    {
        $sub = $this->subscription();
        $cycle = $this->svc()->scheduleNextCycle($sub, now()->subHour());
        $order = app(LiveOrderBridge::class)->onSubscriptionStarted($sub);
        Order::whereKey($order->id)->update(['status' => 'PendingFollowUpRequest']);

        $this->assertSame('PendingFollowUpRequest', $this->svc()->repeatBlockedBy($sub));
        $this->assertSame(0, $this->svc()->runDueCycles());
        $this->assertSame(CycleStatus::Scheduled, $cycle->fresh()->status, 'The repeat waits, it is not lost.');

        Order::whereKey($order->id)->update(['status' => 'Processing']);   // the doctor reviewed and submitted
        $this->assertNull($this->svc()->repeatBlockedBy($sub));
        $this->assertSame(1, $this->svc()->runDueCycles());
        $this->assertSame(CycleStatus::Attempted, $cycle->fresh()->status);
    }

    public function test_a_second_missed_consultation_cancels_the_order(): void
    {
        $this->assertSame('NoShow', OrderStatusMachine::statusAfterNoShow(0), 'First miss: the patient may rebook once.');
        $this->assertSame('Cancelled', OrderStatusMachine::statusAfterNoShow(1), 'Second miss cancels.');
        $this->assertSame('Cancelled', OrderStatusMachine::statusAfterNoShow(5));
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('NoShow', 'Cancelled', 'system_timer'));
        $this->assertTrue(OrderStatusMachine::isTransitionAllowed('NoShow', 'PendingBooking', 'patient'));

        config(['orders.no_show.max_reschedules' => 2]);
        $this->assertSame('NoShow', OrderStatusMachine::statusAfterNoShow(1));
    }
}
