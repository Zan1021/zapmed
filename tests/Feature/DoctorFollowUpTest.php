<?php

namespace Tests\Feature;

use App\Enums\CycleStatus;
use App\Enums\UserRole;
use App\Livewire\Doctor\FollowUpPanel;
use App\Livewire\Doctor\FollowUps;
use App\Models\Appointment;
use App\Models\FollowUpRequest;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Doctor\FollowUpService;
use App\Services\Commerce\LiveOrderBridge;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 2.3 follow-up requests (Craig's guide 4.2, 7, 8.2): 7/14/20/30/60/90 days, the next repeat is blocked
 * while one is open, free first / paid later, patient prompt, and who may do what.
 */
class DoctorFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function svc(): FollowUpService
    {
        return app(FollowUpService::class);
    }

    /** @return array{0:User,1:User,2:Order} doctor, patient, order */
    private function setup3(string $orderStatus = 'Delivered'): array
    {
        $doctor = $this->user(UserRole::Doctor);
        $patient = $this->user(UserRole::Patient);
        Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);
        $order = Order::create(['patient_id' => $patient->id, 'status' => $orderStatus, 'total_minor' => 100, 'ordered_at' => now()]);

        return [$doctor, $patient, $order];
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

    public function test_a_doctor_requests_a_follow_up_and_a_delivered_order_waits(): void
    {
        [$doctor, $patient, $order] = $this->setup3('Delivered');

        $f = $this->svc()->request($doctor, $order, 30, 'Check the blood pressure');

        $this->assertSame(FollowUpRequest::REQUESTED, $f->status);
        $this->assertSame(30, $f->days);
        $this->assertSame('2026-12-25', $f->due_at->setTimezone('Africa/Johannesburg')->format('Y-m-d'));
        $this->assertTrue($f->is_free, 'The first follow-up on an order is free.');
        $this->assertSame('Check the blood pressure', $f->fresh()->note);
        $this->assertNotSame('Check the blood pressure', \DB::table('follow_up_requests')->value('note'), 'The note is encrypted.');

        $this->assertSame('PendingFollowUpRequest', $order->fresh()->status);
        $this->assertSame('Delivered', $f->order_status_before);
        $this->assertDatabaseHas('order_status_history', ['order_id' => $order->id, 'to_status' => 'PendingFollowUpRequest', 'trigger_type' => 'doctor']);
    }

    public function test_an_order_still_being_prepared_is_left_alone_but_the_repeat_is_still_blocked(): void
    {
        [$doctor, , $order] = $this->setup3('PharmacyProcessing');

        $f = $this->svc()->request($doctor, $order, 14);

        $this->assertSame('PharmacyProcessing', $order->fresh()->status, 'The script keeps moving.');
        $this->assertNull($f->order_status_before);
        $this->assertTrue($this->svc()->blocksRepeat($order));
    }

    public function test_only_the_allowed_days_are_accepted(): void
    {
        [$doctor, , $order] = $this->setup3();

        foreach ([7, 14, 20, 30, 60, 90] as $days) {
            $o = Order::create(['patient_id' => $order->patient_id, 'status' => 'Completed', 'total_minor' => 1, 'ordered_at' => now()]);
            $this->assertSame($days, $this->svc()->request($doctor, $o, $days)->days);
        }

        foreach ([0, 5, 15, 45, 100] as $bad) {
            $o = Order::create(['patient_id' => $order->patient_id, 'status' => 'Completed', 'total_minor' => 1, 'ordered_at' => now()]);
            try {
                $this->svc()->request($doctor, $o, $bad);
                $this->fail("$bad days should be refused");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('days', $e->errors());
            }
        }
    }

    public function test_only_a_doctor_who_treats_the_patient_can_request(): void
    {
        [$doctor, , $order] = $this->setup3();

        $this->forbidden(fn () => $this->svc()->request($this->user(UserRole::Doctor), $order, 7));
        $this->forbidden(fn () => $this->svc()->request($this->user(UserRole::Admin), $order, 7));
        $this->forbidden(fn () => $this->svc()->request($this->user(UserRole::HealthCoach), $order, 7));
        $this->assertSame(0, FollowUpRequest::count());
        $this->svc()->request($doctor, $order, 7);
        $this->assertSame(1, FollowUpRequest::count());
    }

    public function test_one_open_follow_up_per_order_and_not_for_paused_or_cancelled_orders(): void
    {
        [$doctor, $patient, $order] = $this->setup3();
        $this->svc()->request($doctor, $order, 7);

        try {
            $this->svc()->request($doctor, $order->fresh(), 14);
            $this->fail('Second open request');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order', $e->errors());
        }

        $cancelled = Order::create(['patient_id' => $patient->id, 'status' => 'Cancelled', 'total_minor' => 1, 'ordered_at' => now()]);
        $this->expectException(ValidationException::class);
        $this->svc()->request($doctor, $cancelled, 7);
    }

    public function test_booking_moves_the_order_to_profile_book_and_completing_sends_it_back_to_processing(): void
    {
        [$doctor, $patient, $order] = $this->setup3('Completed');
        $f = $this->svc()->request($doctor, $order, 20);

        $appt = Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'follow_up', 'status' => 'confirmed',
            'appointment_date' => now()->addDays(20)->toDateString(), 'start_time' => '10:00', 'end_time' => '10:15', 'fee_amount' => 0,
        ]);

        $f = $this->svc()->markBooked($f, $appt);
        $this->assertSame(FollowUpRequest::BOOKED, $f->status);
        $this->assertSame($appt->id, $f->appointment_id);
        $this->assertSame('PendingFollowUpProfileBook', $order->fresh()->status);
        $this->assertTrue($this->svc()->blocksRepeat($order), 'Still blocked once booked.');

        $f = $this->svc()->complete($doctor, $f);
        $this->assertSame(FollowUpRequest::COMPLETED, $f->status);
        $this->assertSame($doctor->id, $f->closed_by);
        $this->assertSame('Processing', $order->fresh()->status);
        $this->assertFalse($this->svc()->blocksRepeat($order), 'Repeats resume.');
    }

    public function test_booking_with_someone_elses_appointment_or_twice_is_refused(): void
    {
        [$doctor, , $order] = $this->setup3();
        $f = $this->svc()->request($doctor, $order, 20);
        $stranger = $this->user(UserRole::Patient);
        $theirs = Appointment::create([
            'patient_id' => $stranger->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'confirmed',
            'appointment_date' => now()->toDateString(), 'start_time' => '10:00', 'end_time' => '10:15', 'fee_amount' => 0,
        ]);

        try {
            $this->svc()->markBooked($f, $theirs);
            $this->fail('Wrong patient');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('appointment', $e->errors());
        }

        $this->svc()->markBooked($f);
        $this->expectException(ValidationException::class);
        $this->svc()->markBooked($f->fresh());
    }

    public function test_the_second_follow_up_on_an_order_is_paid(): void
    {
        [$doctor, $patient, $order] = $this->setup3('Completed');
        $first = $this->svc()->request($doctor, $order, 30);
        $this->assertTrue($first->is_free);
        $this->svc()->markBooked($first);
        $this->svc()->complete($doctor, $first->fresh());

        $order->fresh()->update(['status' => 'Completed']);
        $second = $this->svc()->request($doctor, $order->fresh(), 30);
        $this->assertFalse($second->is_free);
    }

    public function test_a_follow_up_that_was_cancelled_does_not_use_up_the_free_one(): void
    {
        [$doctor, , $order] = $this->setup3('Delivered');
        $first = $this->svc()->request($doctor, $order, 30);
        $this->svc()->cancel($doctor, $first, 'Asked by mistake');

        $again = $this->svc()->request($doctor, $order->fresh(), 30);
        $this->assertTrue($again->is_free);
    }

    public function test_cancelling_needs_a_reason_restores_the_order_and_is_limited(): void
    {
        [$doctor, , $order] = $this->setup3('Delivered');
        $f = $this->svc()->request($doctor, $order, 30);
        $ops = $this->user(UserRole::Admin);

        $this->forbidden(fn () => $this->svc()->cancel($this->user(UserRole::Doctor), $f, 'x'));
        $this->forbidden(fn () => $this->svc()->cancel($this->user(UserRole::HealthCoach), $f, 'x'));

        try {
            $this->svc()->cancel($doctor, $f, '  ');
            $this->fail('Reason required');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
        $this->assertSame('PendingFollowUpRequest', $order->fresh()->status);

        $f = $this->svc()->cancel($ops, $f, 'Patient is fine');
        $this->assertSame(FollowUpRequest::CANCELLED, $f->status);
        $this->assertSame('Patient is fine', $f->cancel_reason);
        $this->assertSame('Delivered', $order->fresh()->status, 'Back where it was.');
        $this->assertFalse($this->svc()->blocksRepeat($order));

        $this->expectException(ValidationException::class);
        $this->svc()->cancel($ops, $f, 'again');
    }

    public function test_only_a_doctor_who_treats_the_patient_can_complete(): void
    {
        [$doctor, , $order] = $this->setup3();
        $f = $this->svc()->request($doctor, $order, 30);

        $this->forbidden(fn () => $this->svc()->complete($this->user(UserRole::Doctor), $f));
        $this->forbidden(fn () => $this->svc()->complete($this->user(UserRole::Admin), $f));
        $this->assertTrue($f->fresh()->isOpen());
    }

    public function test_an_open_follow_up_holds_back_the_next_repeat_even_mid_delivery(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $patient = $this->user(UserRole::Patient);
        Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);
        $plan = SubscriptionPlan::create(['name' => 'Repeat plan', 'slug' => 'fu-'.uniqid(), 'price' => 29900, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $sub = Subscription::create(['user_id' => $patient->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'total_paid' => 0, 'payment_count' => 0]);
        $lifecycle = app(SubscriptionLifecycle::class);
        $cycle = $lifecycle->scheduleNextCycle($sub, now()->subHour());
        $order = app(LiveOrderBridge::class)->onSubscriptionStarted($sub);
        Order::whereKey($order->id)->update(['status' => 'PharmacyProcessing', 'patient_id' => $patient->id]);

        $f = $this->svc()->request($doctor, $order->fresh(), 14);

        $this->assertSame('PendingFollowUpRequest', $lifecycle->repeatBlockedBy($sub));
        $this->assertSame(0, $lifecycle->runDueCycles());
        $this->assertSame(CycleStatus::Scheduled, $cycle->fresh()->status, 'The repeat waits, it is not lost.');

        $this->svc()->complete($doctor, $f);
        $this->assertNull($lifecycle->repeatBlockedBy($sub));
        $this->assertSame(1, $lifecycle->runDueCycles());
    }

    public function test_the_patient_is_prompted_a_few_days_before_the_due_date_once(): void
    {
        config(['doctor.followups.notify_enabled' => true]);
        [$doctor, , $order] = $this->setup3('Completed');
        $soon = $this->svc()->request($doctor, $order, 7);

        $other = Order::create(['patient_id' => $order->patient_id, 'status' => 'Completed', 'total_minor' => 1, 'ordered_at' => now()]);
        $later = $this->svc()->request($doctor, $other, 60);

        $this->travel(5)->days();   // 2 days before the 7-day one is due, 55 days before the other
        $this->assertSame(1, $this->svc()->promptDue());
        $this->assertNotNull($soon->fresh()->reminded_at);
        $this->assertNull($later->fresh()->reminded_at);

        $this->assertSame(0, $this->svc()->promptDue(), 'Only once.');
    }

    public function test_a_booked_follow_up_is_not_prompted(): void
    {
        [$doctor, , $order] = $this->setup3('Completed');
        $f = $this->svc()->request($doctor, $order, 7);
        $this->svc()->markBooked($f);

        $this->travel(6)->days();
        $this->assertSame(0, $this->svc()->promptDue());
    }

    public function test_messages_are_off_by_default_and_carry_no_note_or_health_detail(): void
    {
        [$doctor, , $order] = $this->setup3('Completed');
        $this->svc()->request($doctor, $order, 7, 'Blood pressure medicine review');
        $this->assertSame(0, NotificationLog::count(), 'OFF by default.');

        config(['doctor.followups.notify_enabled' => true, 'doctor.followups.ops_emails' => ['ops@example.test']]);
        $o2 = Order::create(['patient_id' => $order->patient_id, 'status' => 'Completed', 'total_minor' => 1, 'ordered_at' => now()]);
        $this->svc()->request($doctor, $o2, 14, 'Blood pressure medicine review');

        $this->assertGreaterThanOrEqual(2, NotificationLog::count(), 'Patient and Operations are told.');
        foreach (NotificationLog::all() as $row) {
            $this->assertStringNotContainsStringIgnoringCase('pressure', json_encode($row->toArray()));
            $this->assertStringNotContainsStringIgnoringCase('medicine', json_encode($row->toArray()));
        }
    }

    public function test_the_command_runs_and_is_scheduled(): void
    {
        [$doctor, , $order] = $this->setup3('Completed');
        $f = $this->svc()->request($doctor, $order, 7);
        $this->travel(5)->days();

        $this->artisan('followups:prompt')->expectsOutput('Prompted 1 follow-up(s).')->assertSuccessful();
        $this->assertNotNull($f->fresh()->reminded_at);

        $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command->implode(' ');
        $this->assertStringContainsString('followups:prompt', $scheduled);
    }

    public function test_the_screens_request_list_complete_and_cancel(): void
    {
        [$doctor, $patient, $order] = $this->setup3('Delivered');

        Livewire::actingAs($doctor)->test(FollowUpPanel::class, ['patientId' => $patient->id])
            ->set('orderId', $order->id)->set('days', 45)->call('request')
            ->assertHasErrors('days')
            ->set('days', 30)->call('request')
            ->assertHasNoErrors();

        $f = FollowUpRequest::firstOrFail();

        Livewire::actingAs($doctor)->test(FollowUps::class)
            ->assertSee($order->reference)
            ->call('askCancel', $f->id)->set('reason', '')->call('cancel')->assertHasErrors('reason')
            ->set('reason', 'Not needed')->call('cancel')->assertHasNoErrors()
            ->set('filter', 'done')->assertSee('Not needed');

        $this->assertSame(FollowUpRequest::CANCELLED, $f->fresh()->status);

        // a doctor who does not treat the patient cannot even load the follow-up
        $f2 = $this->svc()->request($doctor, $order->fresh(), 7);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($this->user(UserRole::Doctor))->test(FollowUps::class)->call('complete', $f2->id);
    }

    public function test_the_follow_up_page_and_consultation_panel_render_for_the_doctor_only(): void
    {
        [$doctor, $patient] = $this->setup3();

        $this->actingAs($doctor)->get(route('doctor.follow-ups'))->assertOk();
        $this->actingAs($this->user(UserRole::Patient))->get(route('doctor.follow-ups'))->assertForbidden();
        $this->actingAs($this->user(UserRole::Admin))->get(route('doctor.follow-ups'))->assertForbidden();

        $appt = Appointment::where('doctor_id', $doctor->id)->firstOrFail();
        $this->actingAs($doctor)->get(route('doctor.consultation', $appt))->assertOk()->assertSee('data-panel="follow-up"', false);
    }
}
