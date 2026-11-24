<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Doctor\AuthorisationPanel;
use App\Livewire\Doctor\Authorisations;
use App\Models\Appointment;
use App\Models\FollowUpRequest;
use App\Models\Order;
use App\Models\OrderAuthorisation;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Commerce\LiveOrderBridge;
use App\Services\Doctor\AuthorisationService;
use App\Services\Doctor\FollowUpService;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 2.5 authorisations (Craig's guide 4.1): six-month authorisations, review around month five, renew or ask
 * for a follow-up first, and an ended authorisation holds back the next repeat.
 */
class DoctorAuthorisationTest extends TestCase
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

    private function svc(): AuthorisationService
    {
        return app(AuthorisationService::class);
    }

    /** @return array{0:User,1:User,2:Order} */
    private function setup3(string $status = 'Delivered'): array
    {
        $doctor = $this->user(UserRole::Doctor);
        $patient = $this->user(UserRole::Patient);
        Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);

        return [$doctor, $patient, Order::create(['patient_id' => $patient->id, 'status' => $status, 'total_minor' => 100, 'ordered_at' => now()])];
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

    public function test_a_doctor_authorises_an_order_for_six_months(): void
    {
        [$doctor, $patient, $order] = $this->setup3();

        $a = $this->svc()->grant($doctor, $order);

        $this->assertSame('2026-11-25', $a->starts_on->format('Y-m-d'));
        $this->assertSame('2027-05-25', $a->expires_on->format('Y-m-d'));
        $this->assertSame($doctor->id, $a->doctor_id);
        $this->assertSame($patient->id, $a->patient_id);
        $this->assertTrue($this->svc()->isValid($a));
        $this->assertNotNull($this->svc()->validFor($order));
    }

    public function test_month_end_dates_do_not_overflow(): void
    {
        [$doctor, , $order] = $this->setup3();

        $a = $this->svc()->grant($doctor, $order, '2026-08-31');

        $this->assertSame('2027-02-28', $a->expires_on->format('Y-m-d'));
    }

    public function test_only_a_treating_doctor_can_grant_renew_or_ask_for_follow_up(): void
    {
        [$doctor, , $order] = $this->setup3();

        foreach ([$this->user(UserRole::Doctor), $this->user(UserRole::Admin), $this->user(UserRole::HealthCoach)] as $who) {
            $this->forbidden(fn () => $this->svc()->grant($who, $order));
        }
        $a = $this->svc()->grant($doctor, $order, '2026-05-01');
        $this->forbidden(fn () => $this->svc()->renew($this->user(UserRole::Doctor), $a));
        $this->forbidden(fn () => $this->svc()->requestFollowUp($this->user(UserRole::Admin), $a, 7));
        $this->assertSame(1, OrderAuthorisation::count());
    }

    public function test_an_order_already_authorised_cannot_be_authorised_again(): void
    {
        [$doctor, , $order] = $this->setup3();
        $this->svc()->grant($doctor, $order);

        $this->expectException(ValidationException::class);
        $this->svc()->grant($doctor, $order);
    }

    public function test_the_review_window_opens_about_a_month_before_the_end(): void
    {
        [$doctor, , $order] = $this->setup3();
        $a = $this->svc()->grant($doctor, $order);   // ends 25 May 2027

        $this->assertFalse($this->svc()->isDueForReview($a));
        $this->assertSame(0, $this->svc()->dueForReview()->count());

        $this->travelTo(CarbonImmutable::parse('2027-04-24 10:00', 'Africa/Johannesburg'));   // 31 days before
        $this->assertTrue($this->svc()->isDueForReview($a));
        $this->assertSame(1, $this->svc()->dueForReview()->count());

        $this->travelTo(CarbonImmutable::parse('2027-04-23 10:00', 'Africa/Johannesburg'));   // 32 days before
        $this->assertFalse($this->svc()->isDueForReview($a));
    }

    public function test_renewing_too_early_is_refused(): void
    {
        [$doctor, , $order] = $this->setup3();
        $a = $this->svc()->grant($doctor, $order);

        try {
            $this->svc()->renew($doctor, $a);
            $this->fail('Too early');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('not time', $e->errors()['status'][0]);
        }
        $this->assertSame(1, OrderAuthorisation::count());
    }

    public function test_renewing_in_the_window_carries_on_from_the_old_end_date(): void
    {
        [$doctor, , $order] = $this->setup3();
        $a = $this->svc()->grant($doctor, $order);
        $this->travelTo(CarbonImmutable::parse('2027-04-30 10:00', 'Africa/Johannesburg'));

        $new = $this->svc()->renew($doctor, $a, 'Doing well');

        $this->assertSame('2027-05-25', $new->starts_on->format('Y-m-d'), 'No days lost or doubled.');
        $this->assertSame('2027-11-25', $new->expires_on->format('Y-m-d'));
        $this->assertSame($a->id, $new->previous_id);
        $this->assertSame('Doing well', $new->fresh()->note);
        $this->assertNotSame('Doing well', \DB::table('order_authorisations')->where('id', $new->id)->value('note'), 'Encrypted.');
        $this->assertSame(OrderAuthorisation::RENEWED, $a->fresh()->status);
        $this->assertNotNull($a->fresh()->renewed_at);
        $this->assertSame($new->id, $this->svc()->validFor($order)->id);

        $this->expectException(ValidationException::class);   // the old one cannot be renewed twice
        $this->svc()->renew($doctor, $a->fresh());
    }

    public function test_renewing_after_it_ended_starts_today(): void
    {
        [$doctor, , $order] = $this->setup3();
        $a = $this->svc()->grant($doctor, $order);
        $this->travelTo(CarbonImmutable::parse('2027-06-10 10:00', 'Africa/Johannesburg'));
        $this->assertSame(1, $this->svc()->expireLapsed());

        $new = $this->svc()->renew($doctor, $a->fresh());

        $this->assertSame('2027-06-10', $new->starts_on->format('Y-m-d'));
        $this->assertSame('2027-12-10', $new->expires_on->format('Y-m-d'));
    }

    public function test_a_follow_up_instead_of_a_renewal_holds_the_order_and_a_renewal_waits_for_it(): void
    {
        [$doctor, , $order] = $this->setup3('Delivered');
        $a = $this->svc()->grant($doctor, $order);
        $this->travelTo(CarbonImmutable::parse('2027-05-01 10:00', 'Africa/Johannesburg'));

        $f = $this->svc()->requestFollowUp($doctor, $a, 14, 'Review BP');

        $this->assertSame($f->id, $a->fresh()->follow_up_request_id);
        $this->assertSame('PendingFollowUpRequest', $order->fresh()->status);
        $this->assertTrue(app(FollowUpService::class)->blocksRepeat($order));

        try {
            $this->svc()->renew($doctor, $a->fresh());
            $this->fail('Follow-up still open');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order', $e->errors());
        }

        app(FollowUpService::class)->complete($doctor, $f);
        $new = $this->svc()->renew($doctor, $a->fresh());
        $this->assertSame(OrderAuthorisation::ACTIVE, $new->status);
    }

    public function test_expiry_marks_the_status_and_is_idempotent(): void
    {
        [$doctor, , $order] = $this->setup3();
        $a = $this->svc()->grant($doctor, $order);

        $this->assertSame(0, $this->svc()->expireLapsed());
        $this->travelTo(CarbonImmutable::parse('2027-05-25 08:00', 'Africa/Johannesburg'));   // the end date itself is no longer valid
        $this->assertFalse($this->svc()->isValid($a->fresh()));
        $this->assertSame(1, $this->svc()->expireLapsed());
        $this->assertSame(OrderAuthorisation::EXPIRED, $a->fresh()->status);
        $this->assertSame(0, $this->svc()->expireLapsed());
    }

    public function test_the_last_valid_day_is_the_day_before_the_end_date(): void
    {
        [$doctor, , $order] = $this->setup3();
        $a = $this->svc()->grant($doctor, $order);

        $this->travelTo(CarbonImmutable::parse('2027-05-24 23:30', 'Africa/Johannesburg'));
        $this->assertTrue($this->svc()->isValid($a->fresh()));
        $this->travelTo(CarbonImmutable::parse('2027-05-25 00:30', 'Africa/Johannesburg'));
        $this->assertFalse($this->svc()->isValid($a->fresh()));
    }

    public function test_an_ended_authorisation_holds_back_the_repeat_until_it_is_renewed(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $patient = $this->user(UserRole::Patient);
        Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);
        $plan = SubscriptionPlan::create(['name' => 'Repeat plan', 'slug' => 'au-'.uniqid(), 'price' => 29900, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $sub = Subscription::create(['user_id' => $patient->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'total_paid' => 0, 'payment_count' => 0]);
        $lifecycle = app(SubscriptionLifecycle::class);
        $order = app(LiveOrderBridge::class)->onSubscriptionStarted($sub);
        Order::whereKey($order->id)->update(['patient_id' => $patient->id, 'status' => 'Delivered']);

        // no authorisation record: never held
        $this->assertNull($lifecycle->repeatBlockedBy($sub));

        $a = $this->svc()->grant($doctor, $order->fresh());
        $this->assertNull($lifecycle->repeatBlockedBy($sub));

        $this->travelTo(CarbonImmutable::parse('2027-05-26 10:00', 'Africa/Johannesburg'));
        $cycle = $lifecycle->scheduleNextCycle($sub, now()->subHour());
        $this->assertSame('AuthorisationExpired', $lifecycle->repeatBlockedBy($sub), 'Held without needing the daily command.');
        $this->assertSame(0, $lifecycle->runDueCycles());
        $this->assertSame(\App\Enums\CycleStatus::Scheduled, $cycle->fresh()->status);

        $this->svc()->renew($doctor, $a->fresh());
        $this->assertNull($lifecycle->repeatBlockedBy($sub));
        $this->assertSame(1, $lifecycle->runDueCycles());
    }

    public function test_the_hold_can_be_switched_off(): void
    {
        [$doctor, , $order] = $this->setup3();
        $this->svc()->grant($doctor, $order);
        $this->travelTo(CarbonImmutable::parse('2027-06-01 10:00', 'Africa/Johannesburg'));

        $this->assertTrue($this->svc()->blocksRepeat($order));
        config(['doctor.authorisations.block_when_expired' => false]);
        $this->assertFalse($this->svc()->blocksRepeat($order));
    }

    public function test_the_command_runs_and_is_scheduled(): void
    {
        [$doctor, , $order] = $this->setup3();
        $this->svc()->grant($doctor, $order, '2026-01-01');   // ended 1 Jul 2026

        $this->artisan('authorisations:expire')->expectsOutput('Expired 1 authorisation(s).')->assertSuccessful();

        $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command->implode(' ');
        $this->assertStringContainsString('authorisations:expire', $scheduled);
    }

    public function test_the_screens(): void
    {
        [$doctor, $patient, $order] = $this->setup3();

        Livewire::actingAs($doctor)->test(AuthorisationPanel::class, ['patientId' => $patient->id])
            ->call('authorise')->assertHasErrors('order')
            ->set('orderId', $order->id)->call('authorise')->assertHasNoErrors()
            ->assertSee('authorised until');

        $a = OrderAuthorisation::firstOrFail();
        $a->update(['expires_on' => '2026-12-10']);   // inside the review window

        Livewire::actingAs($doctor)->test(Authorisations::class)
            ->assertSee($order->reference)
            ->call('open', $a->id, 'followup')->set('days', 45)->call('followUp')->assertHasErrors()
            ->set('days', 30)->call('followUp')->assertHasNoErrors()
            ->assertSee('Follow-up asked for');

        $this->assertSame(1, FollowUpRequest::count());

        // someone else's doctor cannot touch it
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($this->user(UserRole::Doctor))->test(Authorisations::class)->set('acting', $a->id)->set('mode', 'renew')->call('renew');
    }

    public function test_pages_are_for_doctors_only(): void
    {
        [$doctor, $patient] = $this->setup3();

        $this->actingAs($doctor)->get(route('doctor.authorisations'))->assertOk();
        foreach ([UserRole::Patient, UserRole::Admin, UserRole::HealthCoach] as $role) {
            $this->actingAs($this->user($role))->get(route('doctor.authorisations'))->assertForbidden();
        }

        $appt = Appointment::where('doctor_id', $doctor->id)->firstOrFail();
        $this->actingAs($doctor)->get(route('doctor.consultation', $appt))->assertOk()->assertSee('data-panel="authorisation"', false);
    }
}
