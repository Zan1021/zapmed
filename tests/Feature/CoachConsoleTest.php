<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\CoachConsole;
use App\Models\CoachingOffer;
use App\Models\User;
use App\Services\Coaching\CoachingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 5 — Coach console UI + role scoping.
 */
class CoachConsoleTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): User
    {
        return User::factory()->create(['role' => UserRole::Patient]);
    }

    private function coach(): User
    {
        return User::factory()->create(['role' => UserRole::HealthCoach]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_admin_and_coach_can_view_console_patient_cannot(): void
    {
        $this->actingAs($this->admin())->get(route('admin.coach-console'))->assertOk();
        $this->actingAs($this->coach())->get(route('admin.coach-console'))->assertOk();
        $this->actingAs($this->patient())->get(route('admin.coach-console'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Doctor]))->get(route('admin.coach-console'))->assertForbidden();
    }

    public function test_coach_sees_only_their_assigned_patients(): void
    {
        $coachA = $this->coach();
        $coachB = $this->coach();
        $mine = $this->patient();
        $theirs = $this->patient();

        app(CoachingService::class)->assign($mine, $coachA);
        app(CoachingService::class)->assign($theirs, $coachB);

        Livewire::actingAs($coachA)
            ->test(CoachConsole::class)
            ->tap(function ($t) use ($mine, $theirs) {
                $ids = $t->instance()->assignments()->pluck('patient_id');
                $this->assertTrue($ids->contains($mine->id));
                $this->assertFalse($ids->contains($theirs->id));
            });
    }

    public function test_coach_cannot_open_a_patient_they_are_not_assigned_to(): void
    {
        $coach = $this->coach();
        $other = $this->patient();

        Livewire::actingAs($coach)
            ->test(CoachConsole::class)
            ->call('selectPatient', $other->id)
            ->assertSet('selectedPatientId', null); // guard blocked it
    }

    public function test_coach_logs_a_touchpoint_for_assigned_patient(): void
    {
        $coach = $this->coach();
        $patient = $this->patient();
        app(CoachingService::class)->assign($patient, $coach);

        Livewire::actingAs($coach)
            ->test(CoachConsole::class)
            ->call('selectPatient', $patient->id)
            ->set('tpKind', 'check_in')
            ->set('tpChannel', 'phone')
            ->set('tpDirection', 'outbound')
            ->set('tpSummary', 'All good')
            ->call('logTouchpoint');

        $this->assertSame(1, \App\Models\CoachingTouchpoint::where('patient_id', $patient->id)->count());
    }

    public function test_coach_creates_and_accepts_an_offer(): void
    {
        $coach = $this->coach();
        $patient = $this->patient();
        app(CoachingService::class)->assign($patient, $coach);

        $component = Livewire::actingAs($coach)
            ->test(CoachConsole::class)
            ->call('selectPatient', $patient->id)
            ->set('offerServiceLine', 'weight-loss')
            ->call('makeOffer');

        $offer = CoachingOffer::where('patient_id', $patient->id)->firstOrFail();
        $component->call('acceptOffer', $offer->id);

        $this->assertSame(OfferStatus::Accepted, $offer->fresh()->status);
    }

    public function test_admin_can_assign_a_coach_from_the_console(): void
    {
        $admin = $this->admin();
        $coach = $this->coach();
        $patient = $this->patient();
        // Seed an existing assignment so the patient appears in the admin's list, then reassign.
        app(CoachingService::class)->assign($patient, $coach);

        Livewire::actingAs($admin)
            ->test(CoachConsole::class)
            ->call('selectPatient', $patient->id)
            ->set('assignCoachId', (string) $coach->id)
            ->call('assignCoach');

        $this->assertTrue(app(CoachingService::class)->activeCoach($patient->fresh())->is($coach));
    }
}
