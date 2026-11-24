<?php

namespace Tests\Feature;

use App\Enums\CycleStatus;
use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PayFastService;
use App\Services\Subscriptions\RepeatChargeService;
use App\Services\Subscriptions\SubscriptionCheckout;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Charging repeats ourselves on a saved PayFast token (ad hoc). OFF by default, only for charge_mode 'adhoc'.
 * Every PayFast call is faked: nothing here talks to PayFast.
 */
class RepeatChargeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday 25 Nov 2026, 09:31 in South Africa: just after the daily run.
        $this->travelTo(CarbonImmutable::parse('2026-11-25 09:31', 'Africa/Johannesburg'));
        config(['subscriptions.charging.enabled' => true]);
    }

    private function charger(): RepeatChargeService
    {
        return app(RepeatChargeService::class);
    }

    private function lifecycle(): SubscriptionLifecycle
    {
        return app(SubscriptionLifecycle::class);
    }

    private function repeat(string $mode = 'adhoc', ?string $token = 'tok-123', string $status = 'active', int $price = 45000): Subscription
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $plan = SubscriptionPlan::create([
            'name' => 'Repeat', 'slug' => 'repeat-'.uniqid(), 'price' => $price, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1,
        ]);

        return Subscription::create([
            'user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => $status, 'payfast_token' => $token,
            'charge_mode' => $mode, 'total_paid' => 0, 'payment_count' => 0,
        ]);
    }

    /** A due cycle that the runner has already handed over ("attempted"). */
    private function attempted(Subscription $sub, array $meta = []): SubscriptionCycle
    {
        $cycle = $this->lifecycle()->scheduleNextCycle($sub, now()->subMinutes(5));
        $cycle->update(['status' => CycleStatus::Attempted->value, 'metadata' => $meta ?: null]);

        return $cycle->fresh();
    }

    private function accept(): void
    {
        Http::fake(['api.payfast.co.za/*' => Http::response(['code' => 200, 'status' => 'success', 'data' => ['response' => true, 'message' => 'Success']], 200)]);
    }

    // --- Safety switches -------------------------------------------------------------------------------------------

    public function test_nothing_is_charged_when_the_switch_is_off(): void
    {
        config(['subscriptions.charging.enabled' => false]);
        Http::fake();
        $cycle = $this->attempted($this->repeat());

        $this->assertFalse($this->charger()->enabled());
        $this->assertSame(['submitted' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0], $this->charger()->chargeAttempted());
        $this->assertSame('skipped', $this->charger()->charge($cycle));
        Http::assertNothingSent();
        $this->assertSame(CycleStatus::Attempted, $cycle->fresh()->status);
    }

    public function test_the_switch_is_off_by_default(): void
    {
        // The shipped default (no env value) must be OFF: charging real money is always a deliberate step.
        $source = file_get_contents(base_path('config/subscriptions.php'));

        $this->assertMatchesRegularExpression('/SUBSCRIPTIONS_CHARGE_ENABLED.{1,8}false/', $source);
    }

    public function test_memberships_that_payfast_bills_itself_are_never_charged_by_us(): void
    {
        Http::fake();
        $cycle = $this->attempted($this->repeat('payfast_recurring'));

        $this->assertSame('skipped', $this->charger()->charge($cycle));
        Http::assertNothingSent();
    }

    public function test_paused_cancelled_and_token_less_subscriptions_are_skipped(): void
    {
        Http::fake();

        foreach ([$this->repeat('adhoc', 'tok', 'paused'), $this->repeat('adhoc', 'tok', 'cancelled'), $this->repeat('adhoc', null)] as $sub) {
            $this->assertSame('skipped', $this->charger()->charge($this->attempted($sub)));
        }

        Http::assertNothingSent();
    }

    // --- The charge ------------------------------------------------------------------------------------------------

    public function test_an_accepted_charge_is_sent_once_with_the_right_amount_and_headers_and_waits_for_the_itn(): void
    {
        $this->accept();
        $sub = $this->repeat('adhoc', 'tok-123', 'active', 45000);
        $cycle = $this->attempted($sub);

        $this->assertSame('submitted', $this->charger()->charge($cycle));

        Http::assertSentCount(1);
        Http::assertSent(function (Request $r) use ($cycle) {
            return $r->method() === 'POST'
                && str_starts_with($r->url(), 'https://api.payfast.co.za/subscriptions/tok-123/adhoc')
                && str_contains($r->url(), 'testing=true')
                && $r['amount'] === 45000
                && $r['m_payment_id'] === "RPT-{$cycle->id}-1"
                && $r->hasHeader('merchant-id') && $r->hasHeader('timestamp') && $r->hasHeader('signature')
                && $r->header('version')[0] === 'v1';
        });

        $cycle->refresh();
        $this->assertSame(CycleStatus::Placed, $cycle->status, 'Placed, not paid: PayFast confirms by ITN.');
        $this->assertSame("RPT-{$cycle->id}-1", $cycle->metadata['charge_reference']);
        $this->assertSame(45000, $cycle->metadata['amount_cents']);
        $this->assertSame(0, $sub->fresh()->payment_count);
    }

    public function test_a_cycle_is_never_submitted_twice(): void
    {
        $this->accept();
        $cycle = $this->attempted($this->repeat());

        $this->assertSame('submitted', $this->charger()->charge($cycle));
        $cycle->update(['status' => CycleStatus::Attempted->value]);   // even if something put it back
        $this->assertSame('skipped', $this->charger()->charge($cycle->fresh()));

        Http::assertSentCount(1);
    }

    public function test_a_repeat_can_carry_its_own_amount(): void
    {
        $this->accept();
        $cycle = $this->attempted($this->repeat('adhoc', 'tok-123', 'active', 45000), ['amount_cents' => 129900]);

        $this->charger()->charge($cycle);

        Http::assertSent(fn (Request $r) => $r['amount'] === 129900);
    }

    public function test_a_refused_charge_is_a_failed_payment_and_is_retried_tomorrow_at_0930(): void
    {
        Http::fake(['api.payfast.co.za/*' => Http::response(['code' => 400, 'status' => 'failed', 'data' => ['response' => false, 'message' => 'Card declined']], 400)]);
        $sub = $this->repeat();
        $cycle = $this->attempted($sub, ['amount_cents' => 129900]);

        $this->assertSame('failed', $this->charger()->charge($cycle));

        $this->assertSame(CycleStatus::PaymentFailed, $cycle->fresh()->status);
        $this->assertSame('payment_failed', $sub->fresh()->status);
        $retry = $sub->cycles()->where('status', 'scheduled')->firstOrFail();
        $this->assertSame('2026-11-26 09:30', CarbonImmutable::parse($retry->scheduled_for)->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i'));
        $this->assertSame(129900, $retry->metadata['amount_cents'], 'The retry charges the same amount.');
    }

    public function test_when_payfast_does_not_answer_nothing_is_recorded_as_failed_and_nothing_is_retried(): void
    {
        Http::fake(['api.payfast.co.za/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);
        $sub = $this->repeat();
        $cycle = $this->attempted($sub);

        $this->assertSame('unknown', $this->charger()->charge($cycle));

        $cycle->refresh();
        $this->assertSame(CycleStatus::Placed, $cycle->status);
        $this->assertTrue($cycle->metadata['charge_unknown']);
        $this->assertSame(0, $sub->fresh()->consecutive_failures, 'We do not know it failed.');
        $this->assertSame(0, $sub->cycles()->where('status', 'scheduled')->count(), 'No automatic retry that could double charge.');
        $this->assertSame('skipped', $this->charger()->charge($cycle->fresh()));
    }

    public function test_a_payfast_server_error_is_also_unknown(): void
    {
        Http::fake(['api.payfast.co.za/*' => Http::response('boom', 502)]);
        $cycle = $this->attempted($this->repeat());

        $this->assertSame('unknown', $this->charger()->charge($cycle));
        $this->assertSame(CycleStatus::Placed, $cycle->fresh()->status);
    }

    public function test_the_live_gateway_is_used_when_test_mode_is_off(): void
    {
        $this->accept();
        $cycle = $this->attempted($this->repeat());
        $this->app->bind(PayFastService::class, function () {
            config(['payfast.test_mode' => false]);

            return new PayFastService();
        });

        app(RepeatChargeService::class)->charge($cycle);

        Http::assertSent(fn (Request $r) => ! str_contains($r->url(), 'testing=true'));
    }

    // --- PayFast tells us the result (ITN) -------------------------------------------------------------------------

    public function test_a_complete_itn_records_the_payment_and_schedules_the_next_repeat(): void
    {
        $this->accept();
        $sub = $this->repeat();
        $sub->forceFill(['repeat_cycle_days' => 28])->save();
        $cycle = $this->attempted($sub);
        $this->charger()->charge($cycle);

        $this->assertTrue($this->charger()->recordItn("RPT-{$cycle->id}-1", 'COMPLETE', 45000));

        $this->assertSame(CycleStatus::Fulfilled, $cycle->fresh()->status);
        $sub->refresh();
        $this->assertSame([1, 45000, 1], [$sub->payment_count, $sub->total_paid, $sub->completed_cycles]);
        $next = $sub->cycles()->where('status', 'scheduled')->firstOrFail();
        $this->assertSame('2026-12-23 09:30', CarbonImmutable::parse($next->scheduled_for)->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i'), '28 days later');
    }

    public function test_a_duplicate_itn_changes_nothing(): void
    {
        $this->accept();
        $sub = $this->repeat();
        $cycle = $this->attempted($sub);
        $this->charger()->charge($cycle);

        $this->charger()->recordItn("RPT-{$cycle->id}-1", 'COMPLETE', 45000);
        $this->charger()->recordItn("RPT-{$cycle->id}-1", 'COMPLETE', 45000);

        $this->assertSame(1, $sub->fresh()->payment_count);
        $this->assertSame(1, $sub->cycles()->where('status', 'scheduled')->count());
    }

    public function test_a_failed_itn_counts_as_a_failure_and_the_third_pauses_the_order(): void
    {
        $this->accept();
        $sub = $this->repeat();
        $cycle = $this->attempted($sub);

        foreach ([1, 2, 3] as $n) {
            $this->charger()->charge($cycle);
            $this->assertTrue($this->charger()->recordItn($cycle->fresh()->metadata['charge_reference'], 'FAILED', 45000));

            if ($n < 3) {
                $cycle = $sub->cycles()->where('status', 'scheduled')->firstOrFail();
                $cycle->update(['status' => CycleStatus::Attempted->value]);
                $this->assertSame('payment_failed', $sub->fresh()->status);
            }
        }

        $this->assertSame('paused', $sub->fresh()->status);
        $this->assertSame('system', $sub->pauseEvents()->first()->initiator);
        $this->assertSame(0, $sub->cycles()->where('status', 'scheduled')->count(), 'Charges stop until the patient responds.');
    }

    public function test_an_itn_for_an_unknown_reference_is_not_found(): void
    {
        $this->assertFalse($this->charger()->recordItn('RPT-9999-1', 'COMPLETE', 100));
    }

    public function test_the_itn_endpoint_routes_repeat_charges_to_the_cycle(): void
    {
        $this->accept();
        $sub = $this->repeat();
        $cycle = $this->attempted($sub);
        $this->charger()->charge($cycle);
        $this->mock(PayFastService::class, fn ($m) => $m->shouldReceive('validateItn')->andReturn(true));

        $this->post(route('subscription.notify'), ['m_payment_id' => "RPT-{$cycle->id}-1", 'payment_status' => 'COMPLETE', 'amount_gross' => '450.00', 'token' => 'tok-123'])->assertOk();
        $this->assertSame(CycleStatus::Fulfilled, $cycle->fresh()->status);

        $this->post(route('subscription.notify'), ['m_payment_id' => 'RPT-9999-1', 'payment_status' => 'COMPLETE', 'amount_gross' => '1.00'])->assertNotFound();
    }

    // --- End to end through the daily runner ---------------------------------------------------------------------

    public function test_the_daily_command_hands_over_and_charges_a_due_repeat(): void
    {
        $this->accept();
        $sub = $this->repeat();
        $this->lifecycle()->scheduleNextCycle($sub, now()->subMinutes(5));

        $this->artisan('subscriptions:run-due')
            ->expectsOutputToContain('1 cycle(s) advanced')
            ->expectsOutputToContain('1 submitted')
            ->assertExitCode(0);

        Http::assertSentCount(1);
        $this->assertSame(CycleStatus::Placed, $sub->cycles()->first()->status);
    }

    public function test_the_daily_command_charges_nothing_while_the_switch_is_off(): void
    {
        config(['subscriptions.charging.enabled' => false]);
        Http::fake();
        $sub = $this->repeat();
        $this->lifecycle()->scheduleNextCycle($sub, now()->subMinutes(5));

        $this->artisan('subscriptions:run-due')->expectsOutputToContain('1 cycle(s) advanced')->doesntExpectOutputToContain('Repeat charges')->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame(CycleStatus::Attempted, $sub->cycles()->first()->status);
    }

    public function test_a_blocked_follow_up_stops_the_charge_too(): void
    {
        $this->accept();
        $sub = $this->repeat();
        $this->lifecycle()->scheduleNextCycle($sub, now()->subMinutes(5));
        $order = app(\App\Services\Commerce\LiveOrderBridge::class)->onSubscriptionStarted($sub);
        \App\Models\Order::whereKey($order->id)->update(['status' => 'PendingFollowUpRequest']);

        $this->artisan('subscriptions:run-due')->expectsOutputToContain('0 cycle(s) advanced')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_unconfirmed_charges_are_reported_after_three_days(): void
    {
        Http::fake(['api.payfast.co.za/*' => Http::response('boom', 502)]);
        $cycle = $this->attempted($this->repeat());
        $this->charger()->charge($cycle);
        $this->assertCount(0, $this->charger()->stalePlaced());

        $this->travel(4)->days();

        $this->assertCount(1, $this->charger()->stalePlaced());
        $this->artisan('subscriptions:run-due')->expectsOutputToContain('no confirmation from PayFast')->assertExitCode(0);
    }

    // --- Checkout for a repeat order ---------------------------------------------------------------------------------

    public function test_a_repeat_order_checkout_asks_payfast_to_save_the_card_and_marks_the_subscription_adhoc(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $plan = SubscriptionPlan::create(['name' => 'Repeat', 'slug' => 'tok-plan', 'price' => 99900, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);

        $sub = app(SubscriptionCheckout::class)->begin($user, $plan, null, true);

        $data = session('subscription_checkout.payment_data');
        $this->assertSame('2', $data['subscription_type']);
        foreach (['billing_date', 'recurring_amount', 'frequency', 'cycles'] as $key) {
            $this->assertArrayNotHasKey($key, $data, "{$key} is PayFast-managed billing and must not be sent");
        }
        $this->assertNotEmpty($data['signature']);
        $this->assertSame('adhoc', $sub->fresh()->charge_mode);
    }

    public function test_a_normal_membership_checkout_is_unchanged(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $plan = SubscriptionPlan::create(['name' => 'Member', 'slug' => 'mem-plan', 'price' => 45000, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);

        $sub = app(SubscriptionCheckout::class)->begin($user, $plan);

        $data = session('subscription_checkout.payment_data');
        $this->assertSame('1', $data['subscription_type']);
        $this->assertSame('450.00', $data['recurring_amount']);
        $this->assertSame('payfast_recurring', $sub->fresh()->charge_mode);
    }
}
