<?php

namespace Tests\Feature;

use App\Enums\CycleStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\OrderControlsPanel;
use App\Livewire\Admin\RepeatCycleSettings;
use App\Models\OrderAuditEvent;
use App\Models\RepeatCycle;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Commerce\LiveOrderBridge;
use App\Services\Operations\OrderControls;
use App\Services\Operations\RepeatCycles;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4.3 repeat cycles (Craig's guide 6.4): the cycles Operations set drive the first-repeat day, and Operations can
 * reset a running order's repeat with an audit line.
 */
class OperationsRepeatCyclesTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
    }

    private function svc(): RepeatCycles
    {
        return app(RepeatCycles::class);
    }

    private function sast($t): string
    {
        return CarbonImmutable::parse($t)->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i');
    }

    /** @return array{0:\App\Models\Order,1:Subscription} */
    private function repeatOrder(string $status = 'active'): array
    {
        $patient = User::factory()->create(['role' => UserRole::Patient]);
        $plan = SubscriptionPlan::create(['name' => 'Repeat plan', 'slug' => 'rc-'.uniqid(), 'price' => 29900, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $sub = Subscription::create(['user_id' => $patient->id, 'subscription_plan_id' => $plan->id, 'status' => $status, 'total_paid' => 0, 'payment_count' => 0, 'repeat_cycle_days' => 28]);
        $order = app(LiveOrderBridge::class)->onSubscriptionStarted($sub);

        return [$order->fresh(), $sub];
    }

    public function test_the_guides_examples_are_set_up(): void
    {
        $by = RepeatCycle::orderBy('id')->get()->map(fn ($c) => [$c->days, $c->first_repeat_days, $c->same_day])->all();

        $this->assertContains([28, 23, false], $by, '28 days first repeats on day 23');
        $this->assertContains([30, 25, false], $by);
        $this->assertContains([30, 30, true], $by, 'Same-day: no buffer.');
        foreach ([21, 24, 45, 60] as $days) {
            $this->assertNotEmpty(array_filter($by, fn ($r) => $r[0] === $days), "$days day pack");
        }
    }

    public function test_the_first_repeat_day_comes_from_the_cycle_and_falls_back_to_the_buffer(): void
    {
        $this->assertSame(23, $this->svc()->firstRepeatDays(28));
        $this->assertSame(25, $this->svc()->firstRepeatDays(30), 'The normal 30 day cycle, not the same-day one.');
        $this->assertSame(55, $this->svc()->firstRepeatDays(60));
        $this->assertSame(35, $this->svc()->firstRepeatDays(40), 'No 40 day cycle: pack days minus the 5 day buffer.');
        $this->assertSame(1, $this->svc()->firstRepeatDays(3), 'Never below day 1.');
    }

    public function test_a_switched_off_cycle_is_ignored(): void
    {
        RepeatCycle::where('days', 28)->update(['is_enabled' => false]);

        $this->assertSame(23, $this->svc()->firstRepeatDays(28), 'Falls back to 28 - 5 = 23.');

        config(['subscriptions.first_repeat_buffer_days' => 2]);
        $this->assertSame(26, $this->svc()->firstRepeatDays(28));
    }

    public function test_the_scheduler_uses_the_cycle_operations_set(): void
    {
        [, $sub] = $this->repeatOrder();
        $this->svc()->save($this->ops, RepeatCycle::where('days', 28)->first(), '28 day pack (weight-loss pens)', 28, 20, false, true);

        $first = app(SubscriptionLifecycle::class)->scheduleFirstRepeat($sub, CarbonImmutable::parse('2026-11-02 08:00', 'Africa/Johannesburg'), 28);

        $this->assertSame('2026-11-22 09:30', $this->sast($first->scheduled_for), 'Day 20 now.');
    }

    public function test_adding_and_editing_a_cycle_is_checked(): void
    {
        $cases = [
            'description' => fn () => $this->svc()->save($this->ops, null, ' ', 14, 9, false, true),
            'days' => fn () => $this->svc()->save($this->ops, null, 'x', 0, 1, false, true),
            'days ' => fn () => $this->svc()->save($this->ops, null, 'x', 366, 100, false, true),
            'first_repeat_days' => fn () => $this->svc()->save($this->ops, null, 'x', 14, 0, false, true),
            'first_repeat_days ' => fn () => $this->svc()->save($this->ops, null, 'x', 14, 15, false, true),
            'first_repeat_days  ' => fn () => $this->svc()->save($this->ops, null, 'x', 14, 10, true, true),
            'days  ' => fn () => $this->svc()->save($this->ops, null, 'a second 28 day cycle', 28, 22, false, true),
        ];
        foreach ($cases as $field => $try) {
            try {
                $try();
                $this->fail("Should be refused: $field");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(trim($field), $e->errors());
            }
        }

        $new = $this->svc()->save($this->ops, null, '14 day pack', 14, 9, false, true);
        $this->assertSame(9, $this->svc()->firstRepeatDays(14));

        $edited = $this->svc()->save($this->ops, $new, '14 day pack', 14, 10, false, true);
        $this->assertSame(10, $edited->first_repeat_days);
        $this->assertSame($this->ops->id, $edited->updated_by);
    }

    public function test_toggling_off_then_on_and_the_clash_rule(): void
    {
        $c = RepeatCycle::where('days', 28)->first();
        $this->assertFalse($this->svc()->toggle($this->ops, $c)->is_enabled);

        $this->svc()->save($this->ops, null, 'A newer 28 day cycle', 28, 21, false, true);
        $this->assertSame(21, $this->svc()->firstRepeatDays(28));

        $this->expectException(ValidationException::class);   // switching the old one back on would clash with the new one
        $this->svc()->toggle($this->ops, $c->fresh());
    }

    public function test_only_operations_can_change_cycles(): void
    {
        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach] as $role) {
            try {
                $this->svc()->save(User::factory()->create(['role' => $role]), null, 'x', 14, 9, false, true);
                $this->fail('Should be forbidden');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertSame(0, RepeatCycle::where('days', 14)->count());
    }

    public function test_resetting_a_repeat_cancels_waiting_cycles_keeps_history_clears_strikes_and_audits(): void
    {
        [$order, $sub] = $this->repeatOrder();
        $lifecycle = app(SubscriptionLifecycle::class);
        $old = $lifecycle->scheduleNextCycle($sub, CarbonImmutable::parse('2026-12-10 09:30', 'Africa/Johannesburg'));
        $sub->update(['consecutive_failures' => 2, 'status' => 'payment_failed']);

        $new = app(OrderControls::class)->resetRepeat($this->ops, $order, 'Patient changed address and pack', 28);

        $this->assertSame(CycleStatus::Cancelled, $old->fresh()->status, 'Old repeat kept as history, never run.');
        $this->assertSame(CycleStatus::Scheduled, $new->fresh()->status);
        $this->assertSame('2026-12-18 09:30', $this->sast($new->scheduled_for), 'Day 23 from today (25 Nov).');
        $sub = $sub->fresh();
        $this->assertSame('active', $sub->status);
        $this->assertSame(0, (int) $sub->consecutive_failures);
        $this->assertSame(1, $sub->cycles()->where('status', 'scheduled')->count());

        $event = OrderAuditEvent::where('order_id', $order->id)->where('event', 'repeat_reset')->firstOrFail();
        $this->assertSame($this->ops->id, $event->user_id);
        $this->assertStringContainsString('2026-12-18', $event->summary);
        $this->assertStringContainsString('was 2026-12-10', $event->summary);
        $this->assertStringContainsString('Patient changed address', $event->summary);
    }

    public function test_a_reset_needs_a_reason_a_running_repeat_order_and_a_pack_length(): void
    {
        [$order, $sub] = $this->repeatOrder();
        $oneOff = \App\Models\Order::create(['patient_id' => $order->patient_id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);
        [$pausedOrder, $paused] = $this->repeatOrder('paused');
        [$cancelledOrder] = $this->repeatOrder('cancelled');
        [$noPackOrder, $noPack] = $this->repeatOrder();
        $noPack->update(['repeat_cycle_days' => null]);

        $cases = [
            'reason' => fn () => app(OrderControls::class)->resetRepeat($this->ops, $order, ' '),
            'order' => fn () => app(OrderControls::class)->resetRepeat($this->ops, $oneOff, 'x'),
            'order ' => fn () => app(OrderControls::class)->resetRepeat($this->ops, $pausedOrder, 'x'),
            'order  ' => fn () => app(OrderControls::class)->resetRepeat($this->ops, $cancelledOrder, 'x'),
            'order   ' => fn () => app(OrderControls::class)->resetRepeat($this->ops, $noPackOrder, 'x'),
        ];
        foreach ($cases as $field => $try) {
            try {
                $try();
                $this->fail("Should be refused: $field");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(trim($field), $e->errors());
            }
        }
        $this->assertSame(0, OrderAuditEvent::where('event', 'repeat_reset')->count());

        // giving a pack length makes the last one possible
        app(OrderControls::class)->resetRepeat($this->ops, $noPackOrder, 'Now with a length', 30);
        $this->assertSame(30, (int) $noPack->fresh()->repeat_cycle_days);
    }

    public function test_only_operations_can_reset_a_repeat(): void
    {
        [$order] = $this->repeatOrder();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(OrderControls::class)->resetRepeat(User::factory()->create(['role' => UserRole::Doctor]), $order, 'x');
    }

    public function test_the_settings_screen_lists_edits_and_toggles(): void
    {
        $this->actingAs($this->ops)->get(route('admin.repeat-cycles'))->assertOk()->assertSee('Repeat cycles');
        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.repeat-cycles'))->assertForbidden();
        }

        $this->actingAs($this->ops);
        $c28 = RepeatCycle::where('days', 28)->first();

        Livewire::test(RepeatCycleSettings::class)
            ->assertSeeHtml('data-cycle="'.$c28->id.'"')
            ->call('edit', $c28->id)->assertSet('firstRepeatDays', '23')
            ->set('firstRepeatDays', '99')->call('save')->assertHasErrors('first_repeat_days')
            ->set('firstRepeatDays', '22')->call('save')->assertHasNoErrors()->assertSet('editing', null)
            ->set('description', '10 day pack')->set('days', '10')->set('firstRepeatDays', '5')->call('save')->assertHasNoErrors()
            ->call('toggle', $c28->id)->assertHasNoErrors();

        $this->assertSame(22, $c28->fresh()->first_repeat_days);
        $this->assertFalse($c28->fresh()->is_enabled);
        $this->assertSame(5, $this->svc()->firstRepeatDays(10));
    }

    public function test_the_reset_button_in_the_order_panel(): void
    {
        [$order, $sub] = $this->repeatOrder();
        $this->actingAs($this->ops);

        Livewire::test(OrderControlsPanel::class, ['orderId' => $order->id])
            ->assertSeeHtml('data-section="repeat-reset"')
            ->call('resetRepeat')->assertHasErrors('reason')
            ->set('resetReason', 'Address changed')->call('resetRepeat')->assertHasNoErrors()
            ->assertSeeHtml('data-audit="repeat_reset"');

        $oneOff = \App\Models\Order::create(['patient_id' => $order->patient_id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);
        Livewire::test(OrderControlsPanel::class, ['orderId' => $oneOff->id])->assertDontSeeHtml('data-section="repeat-reset"');
    }
}
