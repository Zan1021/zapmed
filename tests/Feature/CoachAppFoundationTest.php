<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CoachingAssignment;
use App\Models\PatientProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Coach\CoachAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Coach App foundation (Phase 0): settings, tables, who may use the app and who may see tracking data.
 * Design + rules: .kiro/specs/dietitian-coach/coach-app-design.md
 */
class CoachAppFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): User
    {
        $p = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $p->id, 'onboarding_complete' => true]);

        return $p;
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function subscribe(User $patient, string $status = 'active'): Subscription
    {
        $plan = SubscriptionPlan::firstOrCreate(
            ['slug' => config('coach.plan_slug')],
            ['name' => 'Weight loss', 'price' => 45000, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1, 'is_active' => true]
        );

        return Subscription::create([
            'user_id' => $patient->id, 'subscription_plan_id' => $plan->id, 'status' => $status,
            'total_paid' => 0, 'payment_count' => 0,
        ]);
    }

    private function assign(User $patient, User $coach, bool $active = true): CoachingAssignment
    {
        return CoachingAssignment::create([
            'patient_id' => $patient->id, 'coach_id' => $coach->id,
            'started_at' => now(), 'ended_at' => $active ? null : now(),
        ]);
    }

    public function test_settings_match_the_build_guide_defaults(): void
    {
        $this->assertSame(1450, config('coach.targets.kcal'));
        $this->assertSame(92, config('coach.targets.protein_g'));
        $this->assertSame(2200, config('coach.targets.water_ml'));
        $this->assertSame(7, config('coach.flags.abdominal_level'));
        $this->assertSame(9, config('coach.flags.any_level'));
        $this->assertSame(45, config('coach.weight.min_goal_kg'));
        $this->assertSame(45000, config('coach.price_cents'));
        $this->assertCount(9, config('coach.default_symptoms'));
        $this->assertCount(6, config('coach.medication.sites'));
        $this->assertTrue(config('coach.provisional'), 'Clinical numbers stay provisional until Dr Anke signs off.');
    }

    public function test_coach_tables_exist(): void
    {
        foreach (['coach_profiles', 'weight_entries', 'dose_entries', 'side_effect_entries', 'custom_symptoms',
            'water_entries', 'reminder_settings', 'coach_calls', 'coach_flags'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }
    }

    public function test_patient_has_addon_with_active_assignment_or_plan_subscription(): void
    {
        $access = app(CoachAccess::class);

        $none = $this->patient();
        $this->assertFalse($access->patientHasAddon($none));

        $assigned = $this->patient();
        $this->assign($assigned, $this->user(UserRole::HealthCoach));
        $this->assertTrue($access->patientHasAddon($assigned));

        $subscribed = $this->patient();
        $this->subscribe($subscribed);
        $this->assertTrue($access->patientHasAddon($subscribed));

        $lapsed = $this->patient();
        $this->subscribe($lapsed, 'cancelled');
        $this->assertFalse($access->patientHasAddon($lapsed));

        $ended = $this->patient();
        $this->assign($ended, $this->user(UserRole::HealthCoach), false);
        $this->assertFalse($access->patientHasAddon($ended));
    }

    public function test_only_the_patient_their_assigned_coach_and_treating_doctor_can_view_tracking_data(): void
    {
        $access = app(CoachAccess::class);
        $patient = $this->patient();
        $coach = $this->user(UserRole::HealthCoach);
        $otherCoach = $this->user(UserRole::HealthCoach);
        $doctor = $this->user(UserRole::Doctor);
        $otherDoctor = $this->user(UserRole::Doctor);
        $admin = $this->user(UserRole::Admin);
        $stranger = $this->patient();

        $this->assign($patient, $coach);
        Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);

        $this->assertTrue($access->canView($patient, $patient));
        $this->assertTrue($access->canView($coach, $patient));
        $this->assertTrue($access->canView($doctor, $patient));

        $this->assertFalse($access->canView($otherCoach, $patient), 'Unassigned coach must not see the patient.');
        $this->assertFalse($access->canView($otherDoctor, $patient), 'Doctor without an appointment must not see the patient.');
        $this->assertFalse($access->canView($admin, $patient), 'Operations/admin do not get tracking data.');
        $this->assertFalse($access->canView($stranger, $patient));
    }

    public function test_an_ended_assignment_removes_coach_access(): void
    {
        $access = app(CoachAccess::class);
        $patient = $this->patient();
        $coach = $this->user(UserRole::HealthCoach);
        $this->assign($patient, $coach, false);

        $this->assertFalse($access->canView($coach, $patient));
    }

    public function test_patient_without_addon_is_sent_to_the_weight_loss_page(): void
    {
        $this->actingAs($this->patient())->get('/coach')->assertRedirect('/weight-loss');
    }

    public function test_patient_with_addon_sees_the_today_screen(): void
    {
        $patient = $this->patient();
        $this->subscribe($patient);
        \App\Models\CoachProfile::create(['user_id' => $patient->id, 'onboarded_at' => now()]);

        $this->actingAs($patient)->get('/coach')
            ->assertOk()
            ->assertSee('Today')
            ->assertSee('not medical advice');
    }

    public function test_staff_and_guests_cannot_open_the_patient_coach_app(): void
    {
        $this->get('/coach')->assertRedirect();
        $this->actingAs($this->user(UserRole::HealthCoach))->get('/coach')->assertStatus(403);
    }
}
