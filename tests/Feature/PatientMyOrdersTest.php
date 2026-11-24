<?php

namespace Tests\Feature;

use App\Enums\CycleStatus;
use App\Enums\UserRole;
use App\Livewire\Patient\MyOrders;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\PatientProfile;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Commerce\LiveOrderBridge;
use App\Services\Doctor\AuthorisationService;
use App\Services\Doctor\FollowUpService;
use App\Services\Patient\PatientOrders;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3.1 My Orders tab (Craig's guide 3.1, 3.2): plain status, repeats left, next payment, renewal, pause and
 * cancel with a reason, continue within 90 days, archive, resume checkout. A patient only ever sees their own orders.
 */
class PatientMyOrdersTest extends TestCase
{
    use RefreshDatabase;

    private User $patient;
    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));

        $this->patient = $this->patient();
        $this->doctor = User::factory()->create(['role' => UserRole::Doctor]);
        Appointment::create([
            'patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);
    }

    private function patient(): User
    {
        $p = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $p->id, 'onboarding_complete' => true]);

        return $p;
    }

    private function svc(): PatientOrders
    {
        return app(PatientOrders::class);
    }

    /** A repeat order for a patient: subscription, order linked to it, first repeat scheduled. */
    private function repeatOrder(?User $patient = null, string $status = 'Processing', int $packDays = 28): array
    {
        $patient ??= $this->patient;
        $plan = SubscriptionPlan::create(['name' => 'Repeat plan', 'slug' => 'mo-'.uniqid(), 'price' => 29900, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $sub = Subscription::create(['user_id' => $patient->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'total_paid' => 0, 'payment_count' => 0, 'repeat_cycle_days' => $packDays]);
        $order = app(LiveOrderBridge::class)->onSubscriptionStarted($sub);
        $order->update(['status' => $status]);
        app(SubscriptionLifecycle::class)->scheduleFirstRepeat($sub, now(), $packDays);

        return [$order->fresh(), $sub->fresh()];
    }

    private function card(Order $order): array
    {
        return $this->svc()->card($order->fresh());
    }

    public function test_status_is_shown_in_plain_words(): void
    {
        [$order] = $this->repeatOrder(null, 'Despatched');

        $this->assertSame('On its way', $this->card($order)['status_label']);
        $this->assertSame('In progress', $this->svc()->label('SomethingNew'));
        foreach (config('orders.statuses') as $s) {
            $this->assertNotNull(config("patient.orders.status_labels.$s"), "No plain label for $s");
        }
    }

    public function test_repeats_left_is_worked_out_from_the_authorisation_and_the_pack(): void
    {
        [$order, $sub] = $this->repeatOrder(null, 'Delivered', 28);
        app(AuthorisationService::class)->grant($this->doctor, $order);   // six months from 25 Nov 2026 = 181 days = 6 packs

        $this->assertSame(['used' => 0, 'total' => 6, 'left' => 6], $this->card($order)['repeats']);

        foreach ([CycleStatus::Fulfilled, CycleStatus::Placed] as $i => $st) {
            SubscriptionCycle::create(['subscription_id' => $sub->id, 'sequence_no' => 10 + $i, 'status' => $st->value, 'scheduled_for' => now()]);
        }
        SubscriptionCycle::create(['subscription_id' => $sub->id, 'sequence_no' => 20, 'status' => CycleStatus::Cancelled->value, 'scheduled_for' => now()]);

        $this->assertSame(['used' => 2, 'total' => 6, 'left' => 4], $this->card($order)['repeats'], '4 of 6');
    }

    public function test_no_repeat_count_without_an_authorisation_or_a_pack_length(): void
    {
        [$order] = $this->repeatOrder();
        $this->assertNull($this->card($order)['repeats']);

        [$order2, $sub2] = $this->repeatOrder();
        $sub2->update(['repeat_cycle_days' => null]);
        app(AuthorisationService::class)->grant($this->doctor, $order2);
        $this->assertNull($this->card($order2)['repeats']);
    }

    public function test_next_payment_shows_date_and_amount_and_whether_it_is_on_hold(): void
    {
        [$order, $sub] = $this->repeatOrder(null, 'Delivered', 28);

        $next = $this->card($order)['next_payment'];
        $this->assertSame(29900, $next['amount_cents']);
        $this->assertSame('2026-12-18', $next['date']->setTimezone('Africa/Johannesburg')->format('Y-m-d'), 'Day 23.');
        $this->assertFalse($next['on_hold']);
        $this->assertNull($this->card($order)['hold']);

        app(FollowUpService::class)->request($this->doctor, $order->fresh(), 14);
        $card = $this->card($order);
        $this->assertTrue($card['next_payment']['on_hold']);
        $this->assertStringContainsString('follow-up', $card['hold']);
        $this->assertSame('requested', $card['follow_up']['status']);
    }

    public function test_an_ended_authorisation_says_so_and_offers_a_check_in(): void
    {
        [$order] = $this->repeatOrder(null, 'Delivered');
        app(AuthorisationService::class)->grant($this->doctor, $order, '2026-01-01');   // ended 1 Jul 2026

        $card = $this->card($order);
        $this->assertTrue($card['renewal']['due']);
        $this->assertStringContainsString('renew', $card['hold']);
    }

    public function test_renewal_due_shows_only_inside_the_review_window(): void
    {
        [$order] = $this->repeatOrder(null, 'Delivered');
        app(AuthorisationService::class)->grant($this->doctor, $order);

        $this->assertFalse($this->card($order)['renewal']['due']);
        $this->travelTo(CarbonImmutable::parse('2027-04-30 10:00', 'Africa/Johannesburg'));
        $this->assertTrue($this->card($order)['renewal']['due']);
    }

    public function test_pausing_needs_a_reason_records_the_patient_and_stops_repeats(): void
    {
        [$order, $sub] = $this->repeatOrder();

        try {
            $this->svc()->pause($this->patient, $order->id, '  ');
            $this->fail('Reason needed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
        $this->assertSame('active', $sub->fresh()->status);

        $this->svc()->pause($this->patient, $order->id, 'Going overseas');

        $this->assertSame('paused', $sub->fresh()->status);
        $this->assertDatabaseHas('subscription_pause_events', ['subscription_id' => $sub->id, 'initiator' => 'patient', 'paused_by' => $this->patient->id, 'paused_reason' => 'Going overseas']);
        $this->assertSame('Paused', $order->fresh()->status);
        $this->assertSame(0, $sub->cycles()->where('status', 'scheduled')->count(), 'Nothing left to charge.');

        $card = $this->card($order);
        $this->assertSame('Paused', $card['status_label']);
        $this->assertNull($card['next_payment']);
        $this->assertTrue($card['can_continue']);
        $this->assertFalse($card['can_pause']);
    }

    public function test_continuing_works_within_90_days_and_restarts_the_cycle(): void
    {
        [$order, $sub] = $this->repeatOrder();
        $this->svc()->pause($this->patient, $order->id, 'Holiday');
        $this->travel(30)->days();

        $this->svc()->continue($this->patient, $order->id);

        $this->assertSame('active', $sub->fresh()->status);
        $this->assertSame('Processing', $order->fresh()->status);
        $this->assertSame(1, $sub->cycles()->where('status', 'scheduled')->count());
        $this->assertNotNull($this->card($order)['next_payment']);
    }

    public function test_after_90_days_the_patient_cannot_continue_and_is_told_why(): void
    {
        [$order, $sub] = $this->repeatOrder();
        $this->svc()->pause($this->patient, $order->id, 'Holiday');
        $this->travel(91)->days();

        $this->assertFalse($this->card($order)['can_continue']);
        try {
            $this->svc()->continue($this->patient, $order->id);
            $this->fail('Too late');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('90 days', $e->errors()['order'][0]);
        }
        $this->assertSame('paused', $sub->fresh()->status);
    }

    public function test_cancelling_needs_a_reason_and_is_recorded_as_the_patient(): void
    {
        [$order, $sub] = $this->repeatOrder();

        try {
            $this->svc()->cancel($this->patient, $order->id, '');
            $this->fail('Reason needed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $this->svc()->cancel($this->patient, $order->id, 'Too expensive');

        $sub = $sub->fresh();
        $this->assertSame('cancelled', $sub->status);
        $this->assertSame('patient', $sub->cancelled_initiator);
        $this->assertSame($this->patient->id, (int) $sub->cancelled_by);
        $this->assertSame('Too expensive', $sub->cancellation_reason);
        $this->assertSame(0, $sub->cycles()->where('status', 'scheduled')->count());
        $this->assertSame('Cancelled', $this->card($order)['status_label']);
        $this->assertTrue($this->card($order)['can_archive']);

        $this->expectException(ValidationException::class);   // already cancelled
        $this->svc()->cancel($this->patient, $order->id, 'again');
    }

    public function test_a_paused_order_can_be_cancelled(): void
    {
        [$order, $sub] = $this->repeatOrder();
        $this->svc()->pause($this->patient, $order->id, 'Holiday');

        $this->svc()->cancel($this->patient, $order->id, 'Changed my mind');

        $this->assertSame('cancelled', $sub->fresh()->status);
        $this->assertSame('Cancelled', $order->fresh()->status);
    }

    public function test_a_one_off_order_cannot_be_paused_or_cancelled(): void
    {
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 100, 'ordered_at' => now()]);

        foreach (['pause', 'cancel'] as $action) {
            try {
                $this->svc()->$action($this->patient, $order->id, 'x');
                $this->fail('Not a repeat order');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('order', $e->errors());
            }
        }
        $card = $this->card($order);
        $this->assertFalse($card['can_pause']);
        $this->assertFalse($card['can_cancel']);
    }

    public function test_nobody_can_touch_someone_elses_order(): void
    {
        [$order, $sub] = $this->repeatOrder();
        $other = $this->patient();

        foreach ([fn () => $this->svc()->pause($other, $order->id, 'x'), fn () => $this->svc()->cancel($other, $order->id, 'x'),
            fn () => $this->svc()->continue($other, $order->id), fn () => $this->svc()->archive($other, $order->id), fn () => $this->svc()->unarchive($other, $order->id)] as $try) {
            try {
                $try();
                $this->fail('Should be a 404');
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame('active', $sub->fresh()->status);
        $this->assertSame([], $this->svc()->cards($other)->all());
    }

    public function test_archiving_cancelled_ended_and_unfinished_orders_only(): void
    {
        [$running] = $this->repeatOrder(null, 'Delivered');
        $this->assertFalse($this->card($running)['can_archive']);
        try {
            $this->svc()->archive($this->patient, $running->id);
            $this->fail('Still running');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order', $e->errors());
        }

        $unfinished = Order::create(['patient_id' => $this->patient->id, 'status' => 'PendingPayment', 'total_minor' => 1, 'ordered_at' => now()]);
        $cancelled = Order::create(['patient_id' => $this->patient->id, 'status' => 'Cancelled', 'total_minor' => 1, 'ordered_at' => now()]);
        [$ended] = $this->repeatOrder(null, 'Delivered');
        Subscription::where('id', $ended->source_ref)->update(['status' => 'cancelled']);
        $expired = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);
        app(AuthorisationService::class)->grant($this->doctor, $expired, '2026-01-01');

        foreach ([$unfinished, $cancelled, $ended, $expired] as $o) {
            $this->assertTrue($this->card($o)['can_archive'], $o->status);
            $this->svc()->archive($this->patient, $o->id);
        }

        $this->assertSame([$running->id], $this->svc()->cards($this->patient)->pluck('id')->all(), 'Archived orders leave the main list.');
        $this->assertCount(4, $this->svc()->cards($this->patient, true));
        $this->assertSame('Cancelled', $cancelled->fresh()->status, 'Archiving changes no status.');

        $this->svc()->unarchive($this->patient, $cancelled->id);
        $this->assertContains($cancelled->id, $this->svc()->cards($this->patient)->pluck('id')->all());
    }

    public function test_an_unfinished_card_payment_can_be_resumed(): void
    {
        $appointment = Appointment::where('patient_id', $this->patient->id)->first();
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'PendingPayment', 'total_minor' => 70000, 'ordered_at' => now(), 'source_type' => 'appointment', 'source_ref' => (string) $appointment->id]);
        Payment::create(['reference' => 'PAY-RESUME1', 'patient_id' => $this->patient->id, 'appointment_id' => $appointment->id, 'order_id' => $order->id, 'provider' => 'payfast', 'amount' => 70000, 'currency' => 'ZAR', 'status' => 'pending']);

        $card = $this->card($order);
        $this->assertSame('payment', $card['resume']['type']);
        $this->assertSame(route('payment.checkout', 'PAY-RESUME1'), $card['resume']['url']);

        Payment::where('reference', 'PAY-RESUME1')->update(['status' => 'completed']);
        $this->assertNull($this->card($order)['resume'], 'Nothing left to pay.');
    }

    public function test_an_unpaid_repeat_order_restarts_its_checkout(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Repeat plan', 'slug' => 'mo-'.uniqid(), 'price' => 29900, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $sub = Subscription::create(['user_id' => $this->patient->id, 'subscription_plan_id' => $plan->id, 'status' => 'pending', 'total_paid' => 0, 'payment_count' => 0]);
        $order = app(LiveOrderBridge::class)->onSubscriptionStarted($sub);

        $this->assertSame(['type' => 'subscription'], $this->card($order)['resume']);
        $this->assertFalse($this->card($order)['can_cancel'], 'Not started yet: archive instead.');

        Livewire::actingAs($this->patient)->test(MyOrders::class)
            ->call('resumeCheckout', $order->id)->assertRedirect(route('subscription.checkout'));
        $this->assertSame(1, Subscription::where('user_id', $this->patient->id)->count(), 'The same order is reused, not duplicated.');
    }

    public function test_tracking_link_shows_when_the_order_has_one(): void
    {
        [$order] = $this->repeatOrder(null, 'Despatched');
        $this->assertNull($this->card($order)['tracking_url']);

        $order->update(['metadata' => ['tracking_url' => 'https://track.example.test/ABC']]);
        $this->assertSame('https://track.example.test/ABC', $this->card($order)['tracking_url']);
    }

    public function test_the_screen_works_end_to_end_and_hides_other_peoples_orders(): void
    {
        [$mine] = $this->repeatOrder();
        [$theirs] = $this->repeatOrder($this->patient());

        $this->actingAs(User::factory()->create(['role' => UserRole::Doctor]))->get(route('patient.orders'))->assertForbidden();
        $this->actingAs($this->patient)->get(route('patient.orders'))->assertOk()->assertSee('data-patient-tabs', false);

        Livewire::actingAs($this->patient)->test(MyOrders::class)
            ->assertSeeHtml('data-order="'.$mine->id.'"')
            ->assertDontSeeHtml('data-order="'.$theirs->id.'"')
            ->call('ask', $mine->id, 'pause')->call('confirm')->assertHasErrors('reason')
            ->set('reason', 'Travelling')->call('confirm')->assertHasNoErrors()
            ->assertSee('Paused')
            ->call('continueOrder', $mine->id)->assertHasNoErrors()
            ->call('ask', $mine->id, 'cancel')->set('reason', 'Done')->call('confirm')->assertHasNoErrors()
            ->call('archive', $mine->id)->assertHasNoErrors()
            ->assertDontSeeHtml('data-order="'.$mine->id.'"')
            ->set('showArchived', true)->assertSeeHtml('data-order="'.$mine->id.'"');

        $this->assertSame('cancelled', Subscription::find($mine->source_ref)->status);
        $this->assertSame('active', Subscription::find($theirs->source_ref)->status);
    }

    public function test_a_screen_action_on_another_patients_order_is_refused(): void
    {
        [$theirs] = $this->repeatOrder($this->patient());

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($this->patient)->test(MyOrders::class)->call('archive', $theirs->id);
    }
}
