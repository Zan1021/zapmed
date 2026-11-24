<?php

namespace Tests\Feature;

use App\Enums\CycleStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\OrderBoard;
use App\Livewire\Admin\OrderControlsPanel;
use App\Models\Order;
use App\Models\OrderAuditEvent;
use App\Models\OrderDiscount;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Operations\OrderControls;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4.2 order controls (Craig's guide 6.2): a coupon on the monthly service fee (never the medication), a manual
 * payment record, and the audit trail. Admin only.
 */
class OperationsOrderControlsTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;
    private User $patient;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
        $this->patient = User::factory()->create(['role' => UserRole::Patient]);
        // service fee R300, medication R500
        $this->order = Order::create([
            'patient_id' => $this->patient->id, 'status' => 'PendingPayment', 'total_minor' => 80000,
            'service_fee_minor' => 30000, 'medication_fee_minor' => 50000, 'ordered_at' => now(), 'service_category' => 'Weight Loss',
        ]);
    }

    private function svc(): OrderControls
    {
        return app(OrderControls::class);
    }

    private function forbidden(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected 403');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_a_percent_coupon_reduces_only_the_service_fee(): void
    {
        $d = $this->svc()->addDiscount($this->ops, $this->order, 'percent', 20, 3, 'Loyal customer');

        $this->assertSame(2000, $d->value, 'Stored as basis points.');
        $this->assertSame('2026-11-25', $d->starts_on->format('Y-m-d'));
        $this->assertSame('2027-02-25', $d->ends_on->format('Y-m-d'));
        $this->assertSame(6000, $this->svc()->discountCents($this->order), '20% of R300 = R60, nothing off the R500 medication.');
    }

    public function test_a_fixed_coupon_is_capped_at_the_service_fee(): void
    {
        $this->svc()->addDiscount($this->ops, $this->order, 'fixed', 10000, 1, 'Goodwill');
        $this->assertSame(10000, $this->svc()->discountCents($this->order));

        OrderDiscount::query()->update(['is_active' => false]);
        try {
            $this->svc()->addDiscount($this->ops, $this->order, 'fixed', 30001, 1, 'R300.01 is more than the fee');
            $this->fail('More than the service fee');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('value', $e->errors());
        }

        $this->svc()->addDiscount($this->ops, $this->order, 'fixed', 30000, 1, 'Exactly the fee is fine');
        $this->assertSame(30000, $this->svc()->discountCents($this->order));
    }

    public function test_even_a_hundred_percent_never_goes_below_zero_service_fee(): void
    {
        $this->svc()->addDiscount($this->ops, $this->order, 'percent', 100, null, 'Free service');

        $this->assertSame(30000, $this->svc()->discountCents($this->order), 'The whole service fee, never the R500 medication.');
    }

    public function test_the_months_window_and_until_switched_off(): void
    {
        $this->svc()->addDiscount($this->ops, $this->order, 'percent', 10, 2, 'Two months');

        $this->assertSame(3000, $this->svc()->discountCents($this->order, CarbonImmutable::parse('2027-01-24', 'Africa/Johannesburg')));
        $this->assertSame(0, $this->svc()->discountCents($this->order, CarbonImmutable::parse('2027-01-25', 'Africa/Johannesburg')), 'Ends on the end date.');
        $this->assertSame(0, $this->svc()->discountCents($this->order, CarbonImmutable::parse('2026-11-24', 'Africa/Johannesburg')), 'Not before it starts.');

        OrderDiscount::query()->delete();
        $this->svc()->addDiscount($this->ops, $this->order, 'percent', 10, null, 'Open ended');
        $this->assertNull(OrderDiscount::first()->ends_on);
        $this->assertSame(3000, $this->svc()->discountCents($this->order, CarbonImmutable::parse('2030-01-01', 'Africa/Johannesburg')));
    }

    public function test_coupon_validation(): void
    {
        $noFee = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'service_fee_minor' => 0, 'ordered_at' => now()]);
        $cases = [
            'order' => fn () => $this->svc()->addDiscount($this->ops, $noFee, 'percent', 10, 1, 'x'),
            'type' => fn () => $this->svc()->addDiscount($this->ops, $this->order, 'bogus', 10, 1, 'x'),
            'value' => fn () => $this->svc()->addDiscount($this->ops, $this->order, 'percent', 0, 1, 'x'),
            'value ' => fn () => $this->svc()->addDiscount($this->ops, $this->order, 'percent', 101, 1, 'x'),
            'months' => fn () => $this->svc()->addDiscount($this->ops, $this->order, 'percent', 10, 7, 'x'),
            'months ' => fn () => $this->svc()->addDiscount($this->ops, $this->order, 'percent', 10, 0, 'x'),
            'reason' => fn () => $this->svc()->addDiscount($this->ops, $this->order, 'percent', 10, 1, '  '),
        ];
        foreach ($cases as $field => $try) {
            try {
                $try();
                $this->fail("Should be refused: $field");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(trim($field), $e->errors());
            }
        }
        $this->assertSame(0, OrderDiscount::count());
    }

    public function test_switching_a_coupon_off_needs_a_reason_and_stops_it(): void
    {
        $d = $this->svc()->addDiscount($this->ops, $this->order, 'percent', 20, null, 'Test');

        try {
            $this->svc()->disableDiscount($this->ops, $d, '');
            $this->fail('Reason');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $off = $this->svc()->disableDiscount($this->ops, $d, 'Customer left the programme');
        $this->assertFalse($off->is_active);
        $this->assertSame($this->ops->id, $off->disabled_by);
        $this->assertSame(0, $this->svc()->discountCents($this->order));

        $this->expectException(ValidationException::class);   // already off
        $this->svc()->disableDiscount($this->ops, $off, 'again');
        $this->svc()->addDiscount($this->ops, $this->order, 'percent', 5, 1, 'a new one after the old is off');
    }

    public function test_a_new_coupon_can_follow_an_old_one_that_was_switched_off(): void
    {
        $d = $this->svc()->addDiscount($this->ops, $this->order, 'percent', 20, null, 'First');
        $this->svc()->disableDiscount($this->ops, $d, 'Replacing it');

        $this->svc()->addDiscount($this->ops, $this->order, 'percent', 5, 1, 'Second');

        $this->assertSame(1500, $this->svc()->discountCents($this->order));
        $this->assertSame(2, OrderDiscount::count());
    }

    public function test_only_operations_can_use_the_controls(): void
    {
        foreach ([UserRole::Doctor, UserRole::HealthCoach, UserRole::Patient, UserRole::PharmacyStaff] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->forbidden(fn () => $this->svc()->addDiscount($u, $this->order, 'percent', 10, 1, 'x'));
            $this->forbidden(fn () => $this->svc()->recordManualPayment($u, $this->order, 'TX1', 100, '2026-11-25 09:00', 'ref', 'completed'));
        }
        $this->assertSame(0, OrderDiscount::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_every_change_leaves_an_audit_line(): void
    {
        $d = $this->svc()->addDiscount($this->ops, $this->order, 'percent', 20, 3, 'Loyal customer');
        $this->svc()->disableDiscount($this->ops, $d, 'Stopped');
        $this->svc()->recordManualPayment($this->ops, $this->order, 'TX-AUDIT', 29900, '2026-11-25 09:00', 'link 1', 'completed');

        $events = OrderAuditEvent::where('order_id', $this->order->id)->orderBy('id')->get();
        $this->assertSame(['discount_added', 'discount_disabled', 'manual_payment'], $events->pluck('event')->all());
        $this->assertSame([$this->ops->id], $events->pluck('user_id')->unique()->all());
        $this->assertStringContainsString('20%', $events[0]->summary);
        $this->assertStringContainsString('Loyal customer', $events[0]->summary);
        $this->assertStringContainsString('R299.00', $events[2]->summary);
    }

    public function test_a_completed_manual_payment_is_recorded_in_finance_and_moves_the_order(): void
    {
        $p = $this->svc()->recordManualPayment($this->ops, $this->order, 'PF-1234567', 29900, '2026-11-25 09:30', 'Link sent by phone', 'completed');

        $this->assertSame('completed', $p->status);
        $this->assertSame(29900, $p->amount);
        $this->assertSame($this->order->id, $p->order_id);
        $this->assertSame($this->patient->id, $p->patient_id);
        $this->assertSame('PF-1234567', $p->provider_reference);
        $this->assertSame('manual', $p->payment_type);
        $this->assertStringContainsString('Link sent by phone', $p->description);
        $this->assertSame('2026-11-25 07:30', $p->paid_at->utc()->format('Y-m-d H:i'), '09:30 South African time.');
        $this->assertDatabaseHas('finance_revenue_entries', ['payment_id' => $p->id]);
        $this->assertSame('PaymentReceived', $this->order->fresh()->status);
    }

    public function test_a_failed_manual_payment_is_recorded_but_not_as_money_in(): void
    {
        $p = $this->svc()->recordManualPayment($this->ops, $this->order, 'PF-FAIL', 29900, '2026-11-25 09:30', 'x', 'failed');

        $this->assertSame('failed', $p->status);
        $this->assertNull($p->paid_at);
        $this->assertDatabaseMissing('finance_revenue_entries', ['payment_id' => $p->id]);
        $this->assertSame('PendingPayment', $this->order->fresh()->status);
    }

    public function test_the_same_transaction_cannot_be_recorded_twice(): void
    {
        $this->svc()->recordManualPayment($this->ops, $this->order, 'PF-ONCE', 29900, '2026-11-25 09:30', 'x', 'completed');

        try {
            $this->svc()->recordManualPayment($this->ops, $this->order, 'PF-ONCE', 29900, '2026-11-25 09:30', 'x', 'completed');
            $this->fail('Duplicate');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('transaction', $e->errors());
        }
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, \DB::table('finance_revenue_entries')->count(), 'Cash counted once.');
    }

    public function test_manual_payment_validation(): void
    {
        $cases = [
            'transaction' => fn () => $this->svc()->recordManualPayment($this->ops, $this->order, ' ', 100, '2026-11-25 09:00', 'r', 'completed'),
            'amount' => fn () => $this->svc()->recordManualPayment($this->ops, $this->order, 'T', 0, '2026-11-25 09:00', 'r', 'completed'),
            'amount ' => fn () => $this->svc()->recordManualPayment($this->ops, $this->order, 'T', 99_999_999, '2026-11-25 09:00', 'r', 'completed'),
            'result' => fn () => $this->svc()->recordManualPayment($this->ops, $this->order, 'T', 100, '2026-11-25 09:00', 'r', 'maybe'),
            'paid_at' => fn () => $this->svc()->recordManualPayment($this->ops, $this->order, 'T', 100, 'not a time', 'r', 'completed'),
            'paid_at ' => fn () => $this->svc()->recordManualPayment($this->ops, $this->order, 'T', 100, '2026-11-26 09:00', 'r', 'completed'),
        ];
        foreach ($cases as $field => $try) {
            try {
                $try();
                $this->fail("Should be refused: $field");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(trim($field), $e->errors());
            }
        }
        $this->assertSame(0, Payment::count());
    }

    public function test_the_coupon_lowers_the_first_repeat_charge_but_never_the_medication_part(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Repeat plan', 'slug' => 'oc-'.uniqid(), 'price' => 80000, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $sub = Subscription::create(['user_id' => $this->patient->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'total_paid' => 0, 'payment_count' => 0, 'charge_mode' => 'adhoc', 'payfast_token' => 'tok']);
        $this->order->update(['source_type' => 'subscription', 'source_ref' => (string) $sub->id]);
        $this->svc()->addDiscount($this->ops, $this->order, 'percent', 50, 2, 'Half price service');

        $this->assertSame(15000, $this->svc()->discountCentsForSubscription($sub), 'Half of the R300 service fee.');

        $method = new \ReflectionMethod(\App\Services\Subscriptions\RepeatChargeService::class, 'amountCents');
        $method->setAccessible(true);
        $charge = app(\App\Services\Subscriptions\RepeatChargeService::class);

        $cycle = app(SubscriptionLifecycle::class)->scheduleNextCycle($sub, now());
        $this->assertSame(65000, $method->invoke($charge, $cycle, $sub), 'R800 - R150, the R500 medication is untouched.');

        // a retry keeps the amount it was first sent with: no second discount
        $cycle->update(['metadata' => ['retry_of' => 1, 'attempt' => 2, 'amount_cents' => 65000]]);
        $this->assertSame(65000, $method->invoke($charge, $cycle->fresh(), $sub));

        // an order with no coupon is charged the plan price as before
        $other = Subscription::create(['user_id' => $this->patient->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'total_paid' => 0, 'payment_count' => 0]);
        $c2 = app(SubscriptionLifecycle::class)->scheduleNextCycle($other, now());
        $this->assertSame(80000, $method->invoke($charge, $c2, $other));
    }

    public function test_a_coupon_that_has_ended_no_longer_lowers_the_charge(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Repeat plan', 'slug' => 'oc-'.uniqid(), 'price' => 80000, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $sub = Subscription::create(['user_id' => $this->patient->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'total_paid' => 0, 'payment_count' => 0]);
        $this->order->update(['source_type' => 'subscription', 'source_ref' => (string) $sub->id]);
        $this->svc()->addDiscount($this->ops, $this->order, 'percent', 50, 1, 'One month');

        $this->travelTo(CarbonImmutable::parse('2026-12-26 09:30', 'Africa/Johannesburg'));

        $this->assertSame(0, $this->svc()->discountCentsForSubscription($sub));
    }

    public function test_the_panel_in_the_order_board_drawer(): void
    {
        $this->actingAs($this->ops);

        Livewire::test(OrderBoard::class)->call('selectOrder', $this->order->id)->assertSeeHtml('data-panel="order-controls"');

        Livewire::test(OrderControlsPanel::class, ['orderId' => $this->order->id])
            ->set('type', 'percent')->set('value', '25')->set('months', '3')->set('reason', '')->call('addDiscount')->assertHasErrors('reason')
            ->set('reason', 'Thank you')->call('addDiscount')->assertHasNoErrors()->assertSeeHtml('data-active-coupon')->assertSee('R75.00 a month')
            ->set('disableReason', 'Done')->call('disableDiscount', OrderDiscount::first()->id)->assertHasNoErrors()->assertDontSeeHtml('data-active-coupon')
            ->set('transactionId', 'PF-UI')->set('amount', '299,00')->set('paidAt', '2026-11-25T09:00')->set('paymentReference', 'UI')->call('recordPayment')->assertHasNoErrors()
            ->set('transactionId', 'PF-UI2')->set('amount', 'abc')->call('recordPayment')->assertHasErrors('amount')
            ->assertSeeHtml('data-audit="manual_payment"')->assertSeeHtml('data-audit="discount_added"');

        $this->assertSame(29900, Payment::where('provider_reference', 'PF-UI')->value('amount'));
    }

    public function test_a_fixed_coupon_in_rand_is_turned_into_cents_on_the_screen(): void
    {
        $this->actingAs($this->ops);

        Livewire::test(OrderControlsPanel::class, ['orderId' => $this->order->id])
            ->set('type', 'fixed')->set('value', '50.50')->set('months', '')->set('reason', 'Goodwill')->call('addDiscount')->assertHasNoErrors();

        $this->assertSame(5050, OrderDiscount::first()->value);
    }

    public function test_the_panel_refuses_anyone_but_operations(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Doctor]));

        Livewire::test(OrderControlsPanel::class, ['orderId' => $this->order->id])->assertForbidden();
    }
}
