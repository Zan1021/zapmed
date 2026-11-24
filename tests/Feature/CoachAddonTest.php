<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\Join;
use App\Livewire\Doctor\ConsultationScreen;
use App\Livewire\Patient\MySubscription;
use App\Models\Appointment;
use App\Models\CoachingAssignment;
use App\Models\PatientProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Coach\CoachAccess;
use App\Services\Coach\CoachAddon;
use App\Services\Coach\CoachAssigner;
use App\Services\PayFastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach add-on (phase 0.4): a doctor offers it, the member pays through PayFast, a coach is assigned automatically.
 * Design: .kiro/specs/dietitian-coach/coach-app-design.md
 */
class CoachAddonTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): User
    {
        $p = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $p->id, 'onboarding_complete' => true]);

        return $p;
    }

    private function user(UserRole $role, array $attrs = []): User
    {
        return User::factory()->create(['role' => $role] + $attrs);
    }

    private function plan(): SubscriptionPlan
    {
        return SubscriptionPlan::firstOrCreate(
            ['slug' => config('coach.plan_slug')],
            ['name' => 'Weight Loss Programme', 'price' => 45000, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1, 'is_active' => true]
        );
    }

    private function appointment(User $patient, User $doctor): Appointment
    {
        return Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'scheduled',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);
    }

    private function load(User $coach, int $members): void
    {
        for ($i = 0; $i < $members; $i++) {
            CoachingAssignment::create(['patient_id' => $this->patient()->id, 'coach_id' => $coach->id, 'started_at' => now()]);
        }
    }

    // --- Auto-assign ---------------------------------------------------------------------------------------------

    public function test_a_member_goes_to_the_coach_with_the_fewest_members(): void
    {
        $busy = $this->user(UserRole::HealthCoach);
        $quiet = $this->user(UserRole::HealthCoach);
        $this->load($busy, 3);
        $this->load($quiet, 1);

        $a = app(CoachAssigner::class)->assign($member = $this->patient());

        $this->assertSame($quiet->id, $a->coach_id);
        $this->assertSame($member->id, $a->patient_id);
        $this->assertNull($a->ended_at);
    }

    public function test_a_tie_goes_to_the_lowest_id_and_inactive_coaches_are_skipped(): void
    {
        $gone = $this->user(UserRole::HealthCoach, ['is_active' => false]);
        $first = $this->user(UserRole::HealthCoach);
        $second = $this->user(UserRole::HealthCoach);

        $a = app(CoachAssigner::class)->assign($this->patient());

        $this->assertSame($first->id, $a->coach_id);
        $this->assertNotSame($gone->id, $a->coach_id);
        $this->assertNotSame($second->id, $a->coach_id);
    }

    public function test_assigning_twice_keeps_the_same_coach(): void
    {
        $this->user(UserRole::HealthCoach);
        $this->user(UserRole::HealthCoach);
        $member = $this->patient();

        $one = app(CoachAssigner::class)->assign($member);
        $two = app(CoachAssigner::class)->assign($member);

        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, CoachingAssignment::active()->where('patient_id', $member->id)->count());
    }

    public function test_nobody_is_assigned_when_there_are_no_coaches_or_everyone_is_full(): void
    {
        $member = $this->patient();
        $this->assertNull(app(CoachAssigner::class)->assign($member));

        config(['coach.assignment.max_members' => 2]);
        $coach = $this->user(UserRole::HealthCoach);
        $this->load($coach, 2);

        $this->assertNull(app(CoachAssigner::class)->assign($member));
        $this->assertSame(0, CoachingAssignment::active()->where('patient_id', $member->id)->count());
    }

    public function test_the_cap_sends_members_to_the_next_coach(): void
    {
        config(['coach.assignment.max_members' => 2]);
        $full = $this->user(UserRole::HealthCoach);
        $open = $this->user(UserRole::HealthCoach);
        $this->load($full, 2);
        $this->load($open, 1);

        $this->assertSame($open->id, app(CoachAssigner::class)->assign($this->patient())->coach_id);
    }

    public function test_only_patients_are_assigned_a_coach(): void
    {
        $this->user(UserRole::HealthCoach);

        $this->assertNull(app(CoachAssigner::class)->assign($this->user(UserRole::Doctor)));
    }

    public function test_assign_pending_finds_paying_members_without_a_coach(): void
    {
        $coach = $this->user(UserRole::HealthCoach);
        $waiting = $this->patient();
        $covered = $this->patient();
        $unpaid = $this->patient();
        $other = $this->patient();
        foreach ([$waiting, $covered] as $m) {
            Subscription::create(['user_id' => $m->id, 'subscription_plan_id' => $this->plan()->id, 'status' => 'active']);
        }
        Subscription::create(['user_id' => $unpaid->id, 'subscription_plan_id' => $this->plan()->id, 'status' => 'pending']);
        $plus = SubscriptionPlan::create(['name' => 'Plus', 'slug' => 'plus', 'price' => 35000, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1, 'is_active' => true]);
        Subscription::create(['user_id' => $other->id, 'subscription_plan_id' => $plus->id, 'status' => 'active']);
        CoachingAssignment::create(['patient_id' => $covered->id, 'coach_id' => $coach->id, 'started_at' => now()]);

        $this->assertSame(1, app(CoachAssigner::class)->assignPending());
        $this->assertSame($coach->id, app(CoachAccess::class)->assignedCoachId($waiting));
        $this->assertNull(app(CoachAccess::class)->assignedCoachId($unpaid));
        $this->assertNull(app(CoachAccess::class)->assignedCoachId($other));

        $this->artisan('coach:assign-pending')->expectsOutputToContain('0 member')->assertExitCode(0);
    }

    // --- Doctor offers the add-on --------------------------------------------------------------------------------

    public function test_a_doctor_offer_makes_an_unpaid_membership_that_grants_nothing(): void
    {
        $this->plan();
        $doctor = $this->user(UserRole::Doctor);
        $member = $this->patient();
        $appointment = $this->appointment($member, $doctor);

        $sub = app(CoachAddon::class)->attachByDoctor($doctor, $appointment);

        $this->assertSame('pending', $sub->status);
        $this->assertSame($member->id, $sub->user_id);
        $this->assertNotEmpty($sub->payment_reference);
        $this->assertSame($doctor->id, $sub->metadata['attached_by']);
        $this->assertSame($appointment->id, $sub->metadata['appointment_id']);
        $this->assertFalse(app(CoachAccess::class)->patientHasAddon($member), 'Offering is not paying.');
        $this->assertTrue(app(CoachAddon::class)->offeredByDoctor($member));
    }

    public function test_offering_twice_does_not_make_a_second_membership(): void
    {
        $this->plan();
        $doctor = $this->user(UserRole::Doctor);
        $appointment = $this->appointment($this->patient(), $doctor);

        $one = app(CoachAddon::class)->attachByDoctor($doctor, $appointment);
        $two = app(CoachAddon::class)->attachByDoctor($doctor, $appointment);

        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, Subscription::count());
    }

    public function test_only_the_doctor_of_the_consultation_can_offer_it(): void
    {
        $this->plan();
        $doctor = $this->user(UserRole::Doctor);
        $appointment = $this->appointment($this->patient(), $doctor);

        foreach ([$this->user(UserRole::Doctor), $this->user(UserRole::Admin), $this->user(UserRole::HealthCoach)] as $stranger) {
            try {
                app(CoachAddon::class)->attachByDoctor($stranger, $appointment);
                $this->fail('A stranger must not be able to offer the add-on.');
            } catch (\DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, Subscription::count());
    }

    public function test_a_member_who_already_has_the_add_on_cannot_be_offered_it(): void
    {
        $this->plan();
        $doctor = $this->user(UserRole::Doctor);
        $member = $this->patient();
        Subscription::create(['user_id' => $member->id, 'subscription_plan_id' => $this->plan()->id, 'status' => 'active']);

        $this->expectException(\DomainException::class);
        app(CoachAddon::class)->attachByDoctor($doctor, $this->appointment($member, $doctor));
    }

    public function test_the_member_message_is_off_until_switched_on_and_has_no_health_detail(): void
    {
        $this->plan();
        $doctor = $this->user(UserRole::Doctor);
        $member = $this->patient();
        $sent = [];
        $this->mock(\App\Services\Notifications\NotificationDispatcher::class, function ($m) use (&$sent) {
            $m->shouldReceive('send')->andReturnUsing(function ($message) use (&$sent) {
                $sent[] = $message;

                return [];
            });
        });

        app(CoachAddon::class)->attachByDoctor($doctor, $this->appointment($member, $doctor));
        $this->assertCount(0, $sent, 'COACH_ADDON_NOTIFY is off by default.');

        config(['coach.addon.notify_enabled' => true]);
        $other = $this->patient();
        app(CoachAddon::class)->attachByDoctor($doctor, $this->appointment($other, $doctor));
        $this->assertCount(1, $sent);
        $this->assertSame($other->id, $sent[0]->user->id);
        $this->assertStringContainsString(route('coach.join'), $sent[0]->body);
        $this->assertStringNotContainsStringIgnoringCase('weight', $sent[0]->body);
    }

    public function test_the_doctor_offers_from_the_consultation_screen(): void
    {
        $this->plan();
        $doctor = $this->user(UserRole::Doctor);
        $member = $this->patient();
        $appointment = $this->appointment($member, $doctor);

        $this->actingAs($doctor)->get(route('doctor.consultation', $appointment))
            ->assertOk()->assertSee('data-offer-coach', false);

        Livewire::actingAs($doctor)->test(ConsultationScreen::class, ['appointment' => $appointment])
            ->call('offerCoach')
            ->assertSee('Health coach offered')
            ->assertSee('Waiting for the member to pay')
            ->assertDontSee('data-offer-coach', false);

        $this->assertTrue(app(CoachAddon::class)->offeredByDoctor($member));
    }

    public function test_the_offer_card_is_hidden_when_the_member_already_has_the_add_on(): void
    {
        $this->plan();
        $doctor = $this->user(UserRole::Doctor);
        $member = $this->patient();
        Subscription::create(['user_id' => $member->id, 'subscription_plan_id' => $this->plan()->id, 'status' => 'active']);

        $this->actingAs($doctor)->get(route('doctor.consultation', $this->appointment($member, $doctor)))
            ->assertOk()->assertDontSee('data-offer-coach', false);
    }

    // --- Member buys it ------------------------------------------------------------------------------------------

    public function test_the_join_page_shows_the_price_and_needs_a_patient(): void
    {
        $this->plan();

        $this->get(route('coach.join'))->assertRedirect();
        $this->actingAs($this->user(UserRole::Doctor))->get(route('coach.join'))->assertForbidden();
        $this->actingAs($this->patient())->get(route('coach.join'))->assertOk()->assertSee('R450.00')->assertSee('data-join-button', false);
    }

    public function test_the_join_page_tells_the_member_their_doctor_offered_it(): void
    {
        $this->plan();
        $doctor = $this->user(UserRole::Doctor);
        $member = $this->patient();
        app(CoachAddon::class)->attachByDoctor($doctor, $this->appointment($member, $doctor));

        $this->actingAs($member)->get(route('coach.join'))->assertOk()->assertSee('data-doctor-offer', false);
        $this->actingAs($this->patient())->get(route('coach.join'))->assertDontSee('data-doctor-offer', false);
    }

    public function test_joining_goes_to_payfast_and_reuses_the_offered_membership(): void
    {
        $this->plan();
        $doctor = $this->user(UserRole::Doctor);
        $member = $this->patient();
        $offered = app(CoachAddon::class)->attachByDoctor($doctor, $this->appointment($member, $doctor));

        Livewire::actingAs($member)->test(Join::class)->call('join')->assertRedirect(route('subscription.checkout'));

        $this->assertSame(1, Subscription::count(), 'The doctor-offered membership is reused, not duplicated.');
        $data = session('subscription_checkout');
        $this->assertSame($offered->id, $data['subscription_id']);
        $this->assertSame($offered->payment_reference, $data['payment_data']['m_payment_id']);
        $this->assertSame('450.00', $data['payment_data']['amount']);
        $this->assertSame('450.00', $data['payment_data']['recurring_amount']);
    }

    public function test_joining_without_an_offer_creates_a_pending_membership(): void
    {
        $this->plan();
        $member = $this->patient();

        Livewire::actingAs($member)->test(Join::class)->call('join')->assertRedirect(route('subscription.checkout'));

        $sub = Subscription::where('user_id', $member->id)->first();
        $this->assertSame('pending', $sub->status);
        $this->assertFalse(app(CoachAccess::class)->patientHasAddon($member), 'Nothing starts before PayFast confirms payment.');

        // Clicking again reuses the same unpaid membership.
        Livewire::actingAs($member)->test(Join::class)->call('join');
        $this->assertSame(1, Subscription::where('user_id', $member->id)->count());
    }

    public function test_a_member_who_already_has_the_add_on_is_sent_to_the_app(): void
    {
        $this->plan();
        $member = $this->patient();
        Subscription::create(['user_id' => $member->id, 'subscription_plan_id' => $this->plan()->id, 'status' => 'active']);

        Livewire::actingAs($member)->test(Join::class)->assertRedirect(route('coach.today'));
    }

    public function test_the_join_page_copes_with_the_plan_missing(): void
    {
        $member = $this->patient();

        Livewire::actingAs($member)->test(Join::class)->assertSee('not available right now')->call('join')->assertNoRedirect();
        $this->assertSame(0, Subscription::count());
    }

    public function test_the_normal_subscription_page_still_checks_out(): void
    {
        $plan = $this->plan();
        $member = $this->patient();

        Livewire::actingAs($member)->test(MySubscription::class)->call('subscribe', $plan->id)->assertRedirect(route('subscription.checkout'));

        $this->assertSame(1, Subscription::where('user_id', $member->id)->where('status', 'pending')->count());
        $this->assertNotEmpty(session('subscription_checkout.payment_data.signature'));
    }

    // --- PayFast pays -> member gets a coach -----------------------------------------------------------------------

    private function itn(Subscription $sub, string $status = 'COMPLETE'): \Illuminate\Testing\TestResponse
    {
        $this->mock(PayFastService::class, function ($m) {
            $m->shouldReceive('validateItn')->andReturn(true);
        });

        return $this->post(route('subscription.notify'), [
            'm_payment_id' => $sub->payment_reference, 'payment_status' => $status, 'amount_gross' => '450.00', 'token' => 'tok-1',
        ]);
    }

    public function test_the_first_payment_starts_the_add_on_and_assigns_a_coach(): void
    {
        $coach = $this->user(UserRole::HealthCoach);
        $doctor = $this->user(UserRole::Doctor);
        $member = $this->patient();
        $this->plan();
        $sub = app(CoachAddon::class)->attachByDoctor($doctor, $this->appointment($member, $doctor));

        $this->itn($sub)->assertOk();

        $this->assertSame('active', $sub->fresh()->status);
        $this->assertTrue(app(CoachAccess::class)->patientHasAddon($member));
        $this->assertSame($coach->id, app(CoachAccess::class)->assignedCoachId($member));
    }

    public function test_a_failed_payment_gives_no_coach(): void
    {
        $this->user(UserRole::HealthCoach);
        $member = $this->patient();
        $sub = Subscription::create(['user_id' => $member->id, 'subscription_plan_id' => $this->plan()->id, 'status' => 'pending', 'payment_reference' => 'ZM-SUB-FAIL']);

        $this->itn($sub, 'FAILED')->assertOk();

        $this->assertNull(app(CoachAccess::class)->assignedCoachId($member));
        $this->assertFalse(app(CoachAccess::class)->patientHasAddon($member));
    }

    public function test_paying_with_no_coach_free_still_starts_the_add_on_and_the_hourly_job_assigns_later(): void
    {
        $member = $this->patient();
        $sub = Subscription::create(['user_id' => $member->id, 'subscription_plan_id' => $this->plan()->id, 'status' => 'pending', 'payment_reference' => 'ZM-SUB-WAIT']);

        $this->itn($sub)->assertOk();

        $this->assertTrue(app(CoachAccess::class)->patientHasAddon($member));
        $this->assertNull(app(CoachAccess::class)->assignedCoachId($member));

        $coach = $this->user(UserRole::HealthCoach);
        $this->artisan('coach:assign-pending')->assertExitCode(0);
        $this->assertSame($coach->id, app(CoachAccess::class)->assignedCoachId($member));
    }

    public function test_paying_for_another_plan_assigns_no_coach(): void
    {
        $this->user(UserRole::HealthCoach);
        $member = $this->patient();
        $plus = SubscriptionPlan::create(['name' => 'Plus', 'slug' => 'plus', 'price' => 35000, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1, 'is_active' => true]);
        $sub = Subscription::create(['user_id' => $member->id, 'subscription_plan_id' => $plus->id, 'status' => 'pending', 'payment_reference' => 'ZM-SUB-PLUS']);

        $this->itn($sub)->assertOk();

        $this->assertSame('active', $sub->fresh()->status);
        $this->assertNull(app(CoachAccess::class)->assignedCoachId($member));
    }

    public function test_a_renewal_does_not_move_the_member_to_a_new_coach(): void
    {
        $first = $this->user(UserRole::HealthCoach);
        $member = $this->patient();
        $sub = Subscription::create(['user_id' => $member->id, 'subscription_plan_id' => $this->plan()->id, 'status' => 'pending', 'payment_reference' => 'ZM-SUB-RENEW']);
        $this->itn($sub)->assertOk();

        $this->user(UserRole::HealthCoach);
        $this->itn($sub->fresh())->assertOk();

        $this->assertSame(1, CoachingAssignment::where('patient_id', $member->id)->count());
        $this->assertSame($first->id, app(CoachAccess::class)->assignedCoachId($member));
    }
}
