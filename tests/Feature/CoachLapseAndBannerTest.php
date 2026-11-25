<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CoachingAssignment;
use App\Models\PatientProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Coach\CoachAddon;
use App\Services\Coach\CoachAssigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Coach leftovers: the coach ends when the add-on lapses, and a banner tells the member their doctor offered the coach. */
class CoachLapseAndBannerTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coach = User::factory()->create(['role' => UserRole::HealthCoach]);
    }

    private function patient(): User
    {
        $p = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $p->id, 'onboarding_complete' => true]);

        return $p;
    }

    private function plan(): SubscriptionPlan
    {
        return SubscriptionPlan::firstOrCreate(
            ['slug' => config('coach.plan_slug')],
            ['name' => 'Weight Loss Programme', 'price' => 45000, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1, 'is_active' => true]
        );
    }

    private function member(string $status, array $sub = [], string $reason = 'Auto-assigned when the coach add-on started'): array
    {
        $p = $this->patient();
        $s = Subscription::create(['user_id' => $p->id, 'subscription_plan_id' => $this->plan()->id, 'status' => $status] + $sub);
        $a = CoachingAssignment::create(['patient_id' => $p->id, 'coach_id' => $this->coach->id, 'reason' => $reason, 'started_at' => now()->subMonth()]);

        return [$p, $s, $a];
    }

    private function ended(): int
    {
        return app(CoachAssigner::class)->endLapsed();
    }

    public function test_a_cancelled_add_on_past_its_paid_period_loses_its_coach(): void
    {
        [, , $a] = $this->member('cancelled', ['cancelled_at' => now()->subDays(10), 'ends_at' => now()->subDay()]);

        $this->assertSame(1, $this->ended());
        $this->assertNotNull($a->fresh()->ended_at);
        $this->assertSame('The coach add-on ended', $a->fresh()->ended_reason);
    }

    public function test_a_cancelled_add_on_keeps_its_coach_until_the_paid_period_ends(): void
    {
        [, , $a] = $this->member('cancelled', ['cancelled_at' => now()->subDay(), 'ends_at' => now()->addDays(10)]);

        $this->assertSame(0, $this->ended());
        $this->assertNull($a->fresh()->ended_at);

        $this->travel(11)->days();
        $this->assertSame(1, $this->ended());
        $this->assertNotNull($a->fresh()->ended_at);
    }

    public function test_active_paused_and_payment_failed_add_ons_keep_their_coach(): void
    {
        foreach (['active', 'paused', 'payment_failed'] as $status) {
            [, , $a] = $this->member($status);
            $this->assertSame(0, $this->ended(), $status);
            $this->assertNull($a->fresh()->ended_at, $status);
        }
    }

    public function test_a_coach_chosen_by_hand_is_never_ended(): void
    {
        [, , $a] = $this->member('cancelled', ['cancelled_at' => now()->subDays(10), 'ends_at' => now()->subDay()], 'Chosen by Operations');

        $this->assertSame(0, $this->ended());
        $this->assertNull($a->fresh()->ended_at);
    }

    public function test_a_member_with_no_add_on_record_at_all_is_ended_and_can_get_a_new_coach_later(): void
    {
        $p = $this->patient();
        $a = CoachingAssignment::create(['patient_id' => $p->id, 'coach_id' => $this->coach->id, 'reason' => 'Auto-assigned when the coach add-on started', 'started_at' => now()->subMonth()]);

        $this->assertSame(1, $this->ended());
        $this->assertNotNull($a->fresh()->ended_at);

        Subscription::create(['user_id' => $p->id, 'subscription_plan_id' => $this->plan()->id, 'status' => 'active']);
        $this->assertSame(1, app(CoachAssigner::class)->assignPending());
        $this->assertSame(2, CoachingAssignment::where('patient_id', $p->id)->count());
    }

    public function test_the_switch_turns_it_off_and_the_command_runs(): void
    {
        [, , $a] = $this->member('cancelled', ['cancelled_at' => now()->subDays(10), 'ends_at' => now()->subDay()]);

        config(['coach.assignment.end_when_lapsed' => false]);
        $this->artisan('coach:end-lapsed')->expectsOutput('Ended 0 coach assignment(s).')->assertSuccessful();
        $this->assertNull($a->fresh()->ended_at);

        config(['coach.assignment.end_when_lapsed' => true]);
        $this->artisan('coach:end-lapsed')->expectsOutput('Ended 1 coach assignment(s).')->assertSuccessful();
        $this->assertNotNull($a->fresh()->ended_at);
    }

    public function test_the_banner_shows_while_a_doctors_offer_is_unpaid_and_goes_when_it_is_paid(): void
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $p = $this->patient();
        $this->plan();
        $appt = Appointment::create([
            'patient_id' => $p->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'scheduled',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);

        $this->actingAs($p)->get(route('patient.orders'))->assertOk()->assertDontSeeHtml('data-coach-offer-banner');

        $sub = app(CoachAddon::class)->attachByDoctor($doctor, $appt);

        $this->actingAs($p)->get(route('patient.orders'))->assertSeeHtml('data-coach-offer-banner')->assertSee(route('coach.join'));
        $this->actingAs($p)->get(route('dashboard'))->assertSeeHtml('data-coach-offer-banner');

        $sub->update(['status' => 'active']);
        $this->actingAs($p)->get(route('patient.orders'))->assertDontSeeHtml('data-coach-offer-banner');
    }

    public function test_the_banner_is_not_shown_to_staff(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->assertStringNotContainsString('data-coach-offer-banner', view('partials.coach-offer-banner')->render());
    }
}