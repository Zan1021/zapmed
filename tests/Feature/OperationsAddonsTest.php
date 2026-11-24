<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\AddonSettings;
use App\Livewire\Admin\OrderControlsPanel;
use App\Livewire\Patient\MyOrders;
use App\Models\Addon;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderAddon;
use App\Models\OrderAuditEvent;
use App\Models\PatientProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Operations\Addons;
use App\Services\Operations\OperationsReports;
use App\Services\Patient\PatientOrders;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4 leftover: add-ons on an order (Craig's guide 6.2). Operations offer, add, pause and cancel them; a patient can pause
 * or cancel their own with a reason; active ones are part of the repeat charge.
 */
class OperationsAddonsTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;
    private User $patient;
    private Order $order;
    private Addon $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
        $this->patient = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $this->patient->id, 'onboarding_complete' => true]);
        $this->order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 80000, 'service_fee_minor' => 30000, 'ordered_at' => now(), 'service_category' => 'Weight Loss']);
        $this->coach = Addon::create(['name' => 'Health coach support', 'price_cents' => 25000, 'categories' => ['weight'], 'is_active' => true]);
    }

    private function svc(): Addons
    {
        return app(Addons::class);
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

    public function test_the_list_is_saved_checked_and_only_operations_can_change_it(): void
    {
        $a = $this->svc()->save($this->ops, null, '  Priority review ', 15000, ['hair', ' ', 'hair', 'acne'], true);
        $this->assertSame('Priority review', $a->name);
        $this->assertSame(['hair', 'acne'], $a->categories, 'Blanks and repeats dropped.');

        $open = $this->svc()->save($this->ops, null, 'Everywhere', 0, [], true);
        $this->assertNull($open->categories);

        foreach (['name' => fn () => $this->svc()->save($this->ops, null, ' ', 100, [], true),
            'price' => fn () => $this->svc()->save($this->ops, null, 'x', -1, [], true),
            'price ' => fn () => $this->svc()->save($this->ops, null, 'x', 2_000_000, [], true),
            'categories' => fn () => $this->svc()->save($this->ops, null, 'x', 100, [str_repeat('a', 41)], true)] as $field => $try) {
            try {
                $try();
                $this->fail("Should be refused: $field");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(trim($field), $e->errors());
            }
        }

        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach] as $role) {
            $this->forbidden(fn () => $this->svc()->save(User::factory()->create(['role' => $role]), null, 'x', 100, [], true));
        }
    }

    public function test_an_add_on_is_only_offered_for_matching_categories_and_while_it_is_on(): void
    {
        $hair = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now(), 'service_category' => 'Hair Loss']);
        $everywhere = Addon::create(['name' => 'Everywhere', 'price_cents' => 100, 'categories' => null, 'is_active' => true]);
        $off = Addon::create(['name' => 'Off', 'price_cents' => 100, 'is_active' => false]);

        $this->assertSame(['Everywhere', 'Health coach support'], $this->svc()->offeredFor($this->order)->pluck('name')->sort()->values()->all());
        $this->assertSame(['Everywhere'], $this->svc()->offeredFor($hair)->pluck('name')->all(), 'Coach support is for weight orders only.');

        try {
            $this->svc()->add($this->ops, $hair, $this->coach);
            $this->fail('Not offered');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('addon', $e->errors());
        }
        $this->assertFalse($this->svc()->isOffered($off, $this->order));
    }

    public function test_adding_copies_the_price_audits_and_blocks_a_second_copy(): void
    {
        $oa = $this->svc()->add($this->ops, $this->order, $this->coach);

        $this->assertSame('active', $oa->status);
        $this->assertSame(25000, $oa->price_cents);
        $this->assertSame('2026-11-25', $oa->started_on->format('Y-m-d'));
        $this->assertSame($this->ops->id, $oa->added_by);
        $this->assertSame(1, OrderAuditEvent::where('order_id', $this->order->id)->where('event', 'addon_added')->count());

        $this->coach->update(['price_cents' => 99900]);
        $this->assertSame(25000, $oa->fresh()->price_cents, 'A later price change never alters an existing order.');
        $this->assertSame([], $this->svc()->offeredFor($this->order)->pluck('id')->intersect([$this->coach->id])->all(), 'Already on the order.');

        $this->expectException(ValidationException::class);
        $this->svc()->add($this->ops, $this->order, $this->coach);
    }

    public function test_a_cancelled_order_takes_no_add_on_and_a_cancelled_add_on_can_be_added_again(): void
    {
        $oa = $this->svc()->add($this->ops, $this->order, $this->coach);
        $this->svc()->change($this->ops, $oa, 'cancelled', 'No longer needed');
        $again = $this->svc()->add($this->ops, $this->order, $this->coach);
        $this->assertNotSame($oa->id, $again->id);

        $this->order->update(['status' => 'Cancelled']);
        $this->expectException(ValidationException::class);
        $this->svc()->add($this->ops, $this->order, Addon::create(['name' => 'Another', 'price_cents' => 1, 'is_active' => true]));
    }

    public function test_operations_pause_continue_and_cancel_with_the_rules(): void
    {
        $oa = $this->svc()->add($this->ops, $this->order, $this->coach);

        try {
            $this->svc()->change($this->ops, $oa, 'paused', ' ');
            $this->fail('Reason');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $paused = $this->svc()->change($this->ops, $oa, 'paused', 'Patient on holiday');
        $this->assertSame(['paused', 'operations', 'Patient on holiday'], [$paused->status, $paused->initiator, $paused->reason]);
        $this->assertNotNull($paused->paused_at);

        $back = $this->svc()->change($this->ops, $paused, 'active');
        $this->assertSame('active', $back->status);
        $this->assertNull($back->reason);

        $gone = $this->svc()->change($this->ops, $back, 'cancelled', 'Cancelled by phone');
        $this->assertSame('cancelled', $gone->status);
        $this->assertNotNull($gone->cancelled_at);

        foreach (['paused', 'active', 'cancelled'] as $to) {
            try {
                $this->svc()->change($this->ops, $gone, $to, 'x');
                $this->fail('A cancelled add-on stays cancelled');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('status', $e->errors());
            }
        }
        $this->assertSame(['addon_added', 'addon_paused', 'addon_active', 'addon_cancelled'], OrderAuditEvent::where('order_id', $this->order->id)->orderBy('id')->pluck('event')->all());
    }

    public function test_pausing_a_paused_add_on_or_continuing_an_active_one_is_refused(): void
    {
        $oa = $this->svc()->add($this->ops, $this->order, $this->coach);

        try {
            $this->svc()->change($this->ops, $oa, 'active');
            $this->fail('Already active');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $paused = $this->svc()->change($this->ops, $oa, 'paused', 'x');
        try {
            $this->svc()->change($this->ops, $paused, 'paused', 'x');
            $this->fail('Already paused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->expectException(ValidationException::class);
        $this->svc()->change($this->ops, $paused, 'bogus', 'x');
    }

    public function test_a_patient_can_pause_or_cancel_their_own_add_on_with_a_reason_but_not_continue_or_touch_others(): void
    {
        $oa = $this->svc()->add($this->ops, $this->order, $this->coach);

        try {
            app(PatientOrders::class)->changeAddon($this->patient, $oa->id, 'paused', '');
            $this->fail('Reason');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        app(PatientOrders::class)->changeAddon($this->patient, $oa->id, 'paused', 'Too busy right now');
        $this->assertSame(['paused', 'patient'], [$oa->fresh()->status, $oa->fresh()->initiator]);

        try {
            app(PatientOrders::class)->changeAddon($this->patient, $oa->id, 'active', '');
            $this->fail('Patients cannot continue');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        app(PatientOrders::class)->changeAddon($this->patient, $oa->id, 'cancelled', 'Not needed');
        $this->assertSame('cancelled', $oa->fresh()->status);

        $other = User::factory()->create(['role' => UserRole::Patient]);
        $mine = $this->svc()->add($this->ops, Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now(), 'service_category' => 'Weight']), $this->coach);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(PatientOrders::class)->changeAddon($other, $mine->id, 'cancelled', 'x');
    }

    public function test_a_patient_cannot_add_an_add_on_and_staff_who_are_not_operations_cannot_change_one(): void
    {
        $this->forbidden(fn () => $this->svc()->add($this->patient, $this->order, $this->coach));
        $oa = $this->svc()->add($this->ops, $this->order, $this->coach);
        foreach ([UserRole::Doctor, UserRole::HealthCoach] as $role) {
            $this->forbidden(fn () => $this->svc()->change(User::factory()->create(['role' => $role]), $oa, 'cancelled', 'x'));
        }
        $other = User::factory()->create(['role' => UserRole::Patient]);
        $this->forbidden(fn () => $this->svc()->change($other, $oa, 'cancelled', 'x'));
        $this->assertSame('active', $oa->fresh()->status);
    }

    public function test_active_add_ons_are_added_to_the_repeat_charge_and_paused_or_cancelled_ones_are_not(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Repeat plan', 'slug' => 'ad-'.uniqid(), 'price' => 80000, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $sub = Subscription::create(['user_id' => $this->patient->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'total_paid' => 0, 'payment_count' => 0, 'charge_mode' => 'adhoc']);
        $this->order->update(['source_type' => 'subscription', 'source_ref' => (string) $sub->id]);
        $oa = $this->svc()->add($this->ops, $this->order, $this->coach);

        $method = new \ReflectionMethod(\App\Services\Subscriptions\RepeatChargeService::class, 'amountCents');
        $method->setAccessible(true);
        $charge = app(\App\Services\Subscriptions\RepeatChargeService::class);
        $cycle = app(SubscriptionLifecycle::class)->scheduleNextCycle($sub, now());

        $this->assertSame(105000, $method->invoke($charge, $cycle, $sub), 'R800 plus the R250 add-on.');

        $this->svc()->change($this->ops, $oa, 'paused', 'x');
        $this->assertSame(80000, $method->invoke($charge, $cycle->fresh(), $sub));

        $this->svc()->change($this->ops, $oa->fresh(), 'active');
        $cycle->update(['metadata' => ['retry_of' => 1, 'amount_cents' => 105000]]);
        $this->assertSame(105000, $method->invoke($charge, $cycle->fresh(), $sub), 'A retry keeps the amount it was sent with.');
    }

    public function test_messages_are_off_by_default_and_never_name_the_treatment(): void
    {
        $this->svc()->add($this->ops, $this->order, $this->coach);
        $this->assertSame(0, NotificationLog::count());

        config(['operations.addons.notify_enabled' => true]);
        $other = Addon::create(['name' => 'Priority review', 'price_cents' => 1, 'is_active' => true]);
        $oa = $this->svc()->add($this->ops, $this->order, $other);
        $this->svc()->change($this->ops, $oa, 'paused', 'x');

        $this->assertSame(2, NotificationLog::count());
        $this->assertStringNotContainsString('Weight', json_encode(NotificationLog::all()->toArray()));

        NotificationLog::query()->delete();
        app(PatientOrders::class)->changeAddon($this->patient, $oa->id, 'cancelled', 'x');
        $this->assertSame(0, NotificationLog::count(), 'The patient does not need telling about what they just did.');
    }

    public function test_the_add_on_client_report_includes_order_add_ons(): void
    {
        $oa = $this->svc()->add($this->ops, $this->order, $this->coach);

        $r = app(OperationsReports::class)->run($this->ops, 'addon_clients', ['month' => '2026-11']);

        $this->assertCount(1, $r['rows']);
        $this->assertSame('Health coach support', $r['rows'][0][5]);
        $this->assertSame('R250.00', $r['rows'][0][6]);
        $this->assertSame(1, $r['summary']['Active now']);
        $this->assertSame('R250.00', $r['summary']['Monthly value of the active ones']);

        $this->svc()->change($this->ops, $oa, 'cancelled', 'x');
        $this->assertSame(0, app(OperationsReports::class)->run($this->ops, 'addon_clients', ['month' => '2026-11'])['summary']['Active now']);
    }

    public function test_the_settings_page_the_order_panel_and_my_orders(): void
    {
        $this->actingAs($this->ops)->get(route('admin.addons'))->assertOk()->assertSee('Health coach support');
        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.addons'))->assertForbidden();
        }

        $this->actingAs($this->ops);
        Livewire::test(AddonSettings::class)
            ->set('name', 'New extra')->set('price', 'cheap')->call('save')->assertHasErrors('price')
            ->set('price', '99,50')->set('categories', 'hair, acne')->call('save')->assertHasNoErrors()->assertSee('New extra');
        $this->assertSame(9950, Addon::where('name', 'New extra')->value('price_cents'));

        Livewire::test(OrderControlsPanel::class, ['orderId' => $this->order->id])
            ->assertSeeHtml('data-section="addons"')
            ->call('addAddon')->assertHasErrors('addon')
            ->set('addonId', (string) $this->coach->id)->call('addAddon')->assertHasNoErrors()->assertSeeHtml('data-order-addon')
            ->call('changeAddon', OrderAddon::first()->id, 'paused')->assertHasErrors('reason')
            ->set('addonReason', 'Holiday')->call('changeAddon', OrderAddon::first()->id, 'paused')->assertHasNoErrors()->assertSee('paused');

        $this->actingAs($this->patient);
        Livewire::test(MyOrders::class)
            ->assertSeeHtml('data-addon-row="'.OrderAddon::first()->id.'"')->assertSee('Health coach support')->assertSee('Paused')
            ->call('askAddon', OrderAddon::first()->id, 'cancelled')->call('confirmAddon')->assertHasErrors('reason')
            ->set('addonReason', 'No longer needed')->call('confirmAddon')->assertHasNoErrors()
            ->assertDontSeeHtml('data-addon-row');

        $this->assertSame('cancelled', OrderAddon::first()->status);
    }
}
