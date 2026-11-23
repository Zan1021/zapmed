<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\Board;
use App\Livewire\Coach\PatientView;
use App\Models\Appointment;
use App\Models\CoachFlag;
use App\Models\CoachingAssignment;
use App\Models\CoachMessage;
use App\Models\CoachNote;
use App\Models\CoachProfile;
use App\Models\DoseEntry;
use App\Models\PatientProfile;
use App\Models\SideEffectEntry;
use App\Models\User;
use App\Models\WeightEntry;
use App\Services\Coach\CoachPatientBoard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 1.11 + 1.12 + 1.13 (access part): the coach board, the member view, private vs shared notes and the
 * doctor's coaching summary. The rule under test throughout: nobody sees a member they are not assigned to or have
 * not treated, operations/admin see no coach data here, and private notes never leave their author.
 */
class CoachStaffBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 09:00', 'Africa/Johannesburg'));
    }

    private function coach(): User
    {
        return User::factory()->create(['role' => UserRole::HealthCoach]);
    }

    private function doctor(): User
    {
        return User::factory()->create(['role' => UserRole::Doctor]);
    }

    private function member(User $coach, array $profile = []): User
    {
        $user = User::factory()->create(['role' => UserRole::Patient, 'gender' => 'female']);
        PatientProfile::create(['user_id' => $user->id, 'onboarding_complete' => true]);
        CoachingAssignment::create(['patient_id' => $user->id, 'coach_id' => $coach->id, 'started_at' => now()]);
        CoachProfile::create(array_merge(['user_id' => $user->id, 'onboarded_at' => now(), 'start_weight_kg' => 90.0, 'goal_weight_kg' => 70.0], $profile));

        return $user;
    }

    private function treat(User $doctor, User $patient): void
    {
        Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);
    }

    private function weigh(User $u, string $date, float $kg): void
    {
        WeightEntry::create(['user_id' => $u->id, 'logged_on' => $date, 'weight_kg' => $kg]);
    }

    // --- Board -------------------------------------------------------------------------------------------------

    public function test_board_is_for_coaches_only(): void
    {
        $this->get(route('coach.board'))->assertRedirect();

        foreach ([UserRole::Patient, UserRole::Doctor, UserRole::Admin] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get(route('coach.board'))->assertForbidden();
        }

        $this->actingAs($this->coach())->get(route('coach.board'))->assertOk()->assertSee('No members yet');
    }

    public function test_board_lists_only_my_active_members(): void
    {
        $me = $this->coach();
        $other = $this->coach();
        $mine = $this->member($me);
        $theirs = $this->member($other);

        $ended = $this->member($me);
        CoachingAssignment::where('patient_id', $ended->id)->update(['ended_at' => now()]);

        $this->actingAs($me)->get(route('coach.board'))
            ->assertOk()
            ->assertSee('data-member="'.$mine->id.'"', false)
            ->assertDontSee('data-member="'.$theirs->id.'"', false)
            ->assertDontSee('data-member="'.$ended->id.'"', false);
    }

    public function test_board_orders_flags_then_unread_then_quietest_and_shows_badges(): void
    {
        $me = $this->coach();
        $calm = $this->member($me);
        $unread = $this->member($me);
        $flagged = $this->member($me);

        $this->weigh($calm, '2026-11-23', 80.0);
        $this->weigh($unread, '2026-11-23', 80.0);
        CoachMessage::create(['patient_id' => $unread->id, 'sender_id' => $unread->id, 'from' => 'patient', 'body' => 'hi']);
        CoachFlag::create(['patient_id' => $flagged->id, 'source' => 'side_effect', 'symptom' => 'Abdominal pain', 'score' => 8, 'status' => 'open']);

        $rows = app(CoachPatientBoard::class)->boardFor($me);

        $this->assertSame([$flagged->id, $unread->id, $calm->id], $rows->pluck('patient.id')->all());
        $this->assertSame(1, $rows[0]['openFlags']);
        $this->assertSame(1, $rows[1]['unread']);
        $this->assertTrue($rows[0]['quiet'], 'No weigh-in = quiet');
        $this->assertFalse($rows[2]['quiet'], 'Weighed yesterday = not quiet');

        Livewire::actingAs($me)->test(Board::class)->assertSee('1 flag')->assertSee('1 new')->assertSee('quiet');
    }

    // --- Member view access ------------------------------------------------------------------------------------

    public function test_assigned_coach_and_treating_doctor_can_open_a_member(): void
    {
        $coach = $this->coach();
        $doctor = $this->doctor();
        $m = $this->member($coach);
        $this->treat($doctor, $m);

        $this->actingAs($coach)->get(route('coach.board.patient', $m))->assertOk()->assertSee($m->first_name);
        $this->actingAs($doctor)->get(route('coach.board.patient', $m))->assertOk();
    }

    public function test_everyone_else_is_refused(): void
    {
        $coach = $this->coach();
        $m = $this->member($coach);

        $strangers = [
            $this->coach(),                                            // unassigned coach
            $this->doctor(),                                           // doctor who never saw the member
            User::factory()->create(['role' => UserRole::Admin]),      // operations/admin
            User::factory()->create(['role' => UserRole::Patient]),    // another member
            User::factory()->create(['role' => UserRole::PharmacyStaff]),
        ];

        foreach ($strangers as $s) {
            $this->actingAs($s)->get(route('coach.board.patient', $m))->assertForbidden();
        }
    }

    public function test_an_ended_assignment_closes_the_page_even_mid_session(): void
    {
        $coach = $this->coach();
        $m = $this->member($coach);

        $page = Livewire::actingAs($coach)->test(PatientView::class, ['patient' => $m])->assertOk();

        CoachingAssignment::where('patient_id', $m->id)->update(['ended_at' => now()]);

        $page->call('$refresh')->assertForbidden();
    }

    public function test_member_view_shows_the_tracking_data(): void
    {
        $coach = $this->coach();
        $m = $this->member($coach, ['target_months' => 6, 'diet' => 'vegan', 'allergies' => ['nuts']]);
        $this->weigh($m, '2026-11-17', 85.0);
        $this->weigh($m, '2026-11-24', 83.5);
        DoseEntry::create(['user_id' => $m->id, 'taken_at' => now()->subDay(), 'site' => 'Left thigh', 'pain' => 2]);
        SideEffectEntry::create(['user_id' => $m->id, 'logged_on' => '2026-11-23', 'symptom' => 'Nausea', 'score' => 4, 'flagged' => false]);
        SideEffectEntry::create(['user_id' => $m->id, 'logged_on' => '2026-09-01', 'symptom' => 'Old one', 'score' => 3, 'flagged' => false]); // outside 30 days
        CoachFlag::create(['patient_id' => $m->id, 'source' => 'side_effect', 'symptom' => 'Abdominal pain', 'score' => 8, 'status' => 'open']);

        $this->actingAs($coach)->get(route('coach.board.patient', $m))
            ->assertOk()
            ->assertSee('83.5 kg')
            ->assertSee('Left thigh')
            ->assertSee('Nausea')
            ->assertDontSee('Old one')
            ->assertSee('Abdominal pain')
            ->assertSee('Safety flag')
            ->assertSee('Vegan')
            ->assertSee('Nuts')
            ->assertSee('6 months');
    }

    // --- Notes -------------------------------------------------------------------------------------------------

    public function test_private_notes_are_for_the_author_only_and_shared_reach_the_doctor(): void
    {
        $coach = $this->coach();
        $doctor = $this->doctor();
        $m = $this->member($coach);
        $this->treat($doctor, $m);

        $board = app(CoachPatientBoard::class);
        $board->addNote($coach, $m, 'private', 'PRIVATE-SECRET she is struggling at home');
        $board->addNote($coach, $m, 'shared', 'SHARED-OK plateau for two weeks');

        $this->actingAs($coach)->get(route('coach.board.patient', $m))->assertSee('PRIVATE-SECRET')->assertSee('SHARED-OK');
        $this->actingAs($doctor)->get(route('coach.board.patient', $m))->assertDontSee('PRIVATE-SECRET')->assertSee('SHARED-OK');

        $this->assertCount(2, $board->notesFor($coach, $m));
        $this->assertCount(1, $board->notesFor($doctor, $m));
    }

    public function test_a_previous_coachs_private_notes_stay_hidden_from_the_new_coach(): void
    {
        $old = $this->coach();
        $new = $this->coach();
        $m = $this->member($old);

        app(CoachPatientBoard::class)->addNote($old, $m, 'private', 'OLD-PRIVATE');
        app(CoachPatientBoard::class)->addNote($old, $m, 'shared', 'OLD-SHARED');

        CoachingAssignment::where('patient_id', $m->id)->update(['ended_at' => now()]);
        CoachingAssignment::create(['patient_id' => $m->id, 'coach_id' => $new->id, 'started_at' => now()]);

        $this->actingAs($new)->get(route('coach.board.patient', $m))->assertDontSee('OLD-PRIVATE')->assertSee('OLD-SHARED');
        $this->actingAs($old)->get(route('coach.board.patient', $m))->assertForbidden();
    }

    public function test_note_bodies_are_stored_encrypted(): void
    {
        $coach = $this->coach();
        $m = $this->member($coach);

        app(CoachPatientBoard::class)->addNote($coach, $m, 'shared', 'plain words here');

        $raw = \DB::table('coach_notes')->value('body');
        $this->assertStringNotContainsString('plain words here', $raw);
        $this->assertSame('plain words here', CoachNote::first()->body);
    }

    public function test_only_the_assigned_coach_can_write_notes(): void
    {
        $coach = $this->coach();
        $doctor = $this->doctor();
        $m = $this->member($coach);
        $this->treat($doctor, $m);
        $board = app(CoachPatientBoard::class);

        foreach ([$this->coach(), $doctor, User::factory()->create(['role' => UserRole::Admin]), $m] as $who) {
            try {
                $board->addNote($who, $m, 'shared', 'hello');
                $this->fail('Writing should have been refused for '.$who->role->value);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->assertSame(0, CoachNote::count());

        // And the doctor's page has no note form.
        $this->actingAs($doctor)->get(route('coach.board.patient', $m))->assertDontSee('data-action="add-note"', false);
    }

    public function test_note_validation_and_visibility_values(): void
    {
        $coach = $this->coach();
        $m = $this->member($coach);

        Livewire::actingAs($coach)->test(PatientView::class, ['patient' => $m])
            ->set('draft', '   ')->call('addNote')->assertSet('error', 'Please write a note first.')
            ->set('draft', str_repeat('a', 2001))->call('addNote')->assertSet('error', 'Notes can be up to 2000 characters.')
            ->set('visibility', 'everyone')->set('draft', 'ok')->call('addNote')->assertSet('error', 'Choose private or shared.')
            ->set('visibility', 'shared')->set('draft', 'a real note')->call('addNote')->assertSet('error', null)->assertSet('draft', '');

        $this->assertSame(1, CoachNote::count());
    }

    public function test_a_coach_can_delete_only_their_own_note(): void
    {
        $coach = $this->coach();
        $other = $this->coach();
        $m = $this->member($coach);
        $mine = app(CoachPatientBoard::class)->addNote($coach, $m, 'shared', 'mine');

        try {
            app(CoachPatientBoard::class)->deleteNote($other, $mine->id);
            $this->fail('Someone else deleted a note.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(1, CoachNote::count());

        app(CoachPatientBoard::class)->deleteNote($coach, $mine->id);
        $this->assertSame(0, CoachNote::count());
    }

    // --- Doctor summary (1.12) ---------------------------------------------------------------------------------

    public function test_doctor_summary_is_concise_and_respects_access(): void
    {
        $coach = $this->coach();
        $doctor = $this->doctor();
        $m = $this->member($coach);
        $this->treat($doctor, $m);
        $this->weigh($m, '2026-11-16', 86.0);
        $this->weigh($m, '2026-11-24', 84.0);
        DoseEntry::create(['user_id' => $m->id, 'taken_at' => now()->subDay(), 'site' => 'Left thigh', 'pain' => 2]);
        SideEffectEntry::create(['user_id' => $m->id, 'logged_on' => '2026-11-23', 'symptom' => 'Nausea', 'score' => 6, 'flagged' => false]);
        CoachFlag::create(['patient_id' => $m->id, 'source' => 'side_effect', 'symptom' => 'Abdominal pain', 'score' => 8, 'status' => 'open']);
        app(CoachPatientBoard::class)->addNote($coach, $m, 'shared', 'SHARED-FOR-DOCTOR');
        app(CoachPatientBoard::class)->addNote($coach, $m, 'private', 'PRIVATE-NOT-FOR-DOCTOR');

        $s = app(CoachPatientBoard::class)->doctorSummary($doctor, $m);

        $this->assertNotNull($s);
        $this->assertSame(1, $s['openFlags']);
        $this->assertSame(2.0, $s['weekLossKg']);
        $this->assertSame('Nausea', $s['recentSideEffects']->first()->symptom);
        $this->assertCount(1, $s['sharedNotes']);
        $this->assertSame($coach->id, $s['coach']->id);

        // Not a doctor who treated them, a coach, or admin: nothing.
        $this->assertNull(app(CoachPatientBoard::class)->doctorSummary($this->doctor(), $m));
        $this->assertNull(app(CoachPatientBoard::class)->doctorSummary($coach, $m));
        $this->assertNull(app(CoachPatientBoard::class)->doctorSummary(User::factory()->create(['role' => UserRole::Admin]), $m));
    }

    public function test_doctor_summary_is_empty_for_a_member_who_never_used_the_coach_app(): void
    {
        $doctor = $this->doctor();
        $m = User::factory()->create(['role' => UserRole::Patient]);
        $this->treat($doctor, $m);

        $this->assertNull(app(CoachPatientBoard::class)->doctorSummary($doctor, $m));
    }

    public function test_summary_card_shows_on_the_consultation_screen_only_for_the_treating_doctor(): void
    {
        $coach = $this->coach();
        $doctor = $this->doctor();
        $m = $this->member($coach);
        $this->treat($doctor, $m);
        app(CoachPatientBoard::class)->addNote($coach, $m, 'shared', 'SHARED-FOR-DOCTOR');
        app(CoachPatientBoard::class)->addNote($coach, $m, 'private', 'PRIVATE-NOT-FOR-DOCTOR');

        $appointment = Appointment::where('doctor_id', $doctor->id)->first();

        $this->actingAs($doctor)->get(route('doctor.consultation', $appointment))
            ->assertOk()
            ->assertSee('Coaching summary')
            ->assertSee('SHARED-FOR-DOCTOR')
            ->assertDontSee('PRIVATE-NOT-FOR-DOCTOR');
    }

    public function test_service_rejects_a_bad_visibility_directly(): void
    {
        $coach = $this->coach();
        $m = $this->member($coach);

        $this->expectException(InvalidArgumentException::class);
        app(CoachPatientBoard::class)->addNote($coach, $m, 'public', 'x');
    }
}
