<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Doctor\NotesPanel;
use App\Livewire\Doctor\StaffNotes;
use App\Models\Appointment;
use App\Models\CoachingAssignment;
use App\Models\CoachNote;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\PatientNote;
use App\Models\User;
use App\Services\Doctor\PatientNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 2.6 note separation (Craig's guide 4.3, 8.1). The rule under test throughout: who can read what.
 * Clinical notes: treating doctors only. Summaries: the patient and treating doctors. Staff notes: the author and
 * the people or roles they are addressed to. Operations never reads clinical or coach notes.
 */
class PatientNotesTest extends TestCase
{
    use RefreshDatabase;

    private User $doctor;
    private User $colleague;     // second doctor who also treats the patient
    private User $stranger;      // doctor who does not
    private User $coach;
    private User $otherCoach;
    private User $ops;
    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctor = $this->user(UserRole::Doctor);
        $this->colleague = $this->user(UserRole::Doctor);
        $this->stranger = $this->user(UserRole::Doctor);
        $this->coach = $this->user(UserRole::HealthCoach);
        $this->otherCoach = $this->user(UserRole::HealthCoach);
        $this->ops = $this->user(UserRole::Admin);
        $this->patient = $this->user(UserRole::Patient);

        foreach ([$this->doctor, $this->colleague] as $d) {
            Appointment::create([
                'patient_id' => $this->patient->id, 'doctor_id' => $d->id, 'type' => 'general', 'status' => 'completed',
                'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
            ]);
        }
        CoachingAssignment::create(['patient_id' => $this->patient->id, 'coach_id' => $this->coach->id, 'started_at' => now()]);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function svc(): PatientNoteService
    {
        return app(PatientNoteService::class);
    }

    private function ids($collection): array
    {
        return $collection->pluck('id')->all();
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

    public function test_a_clinical_note_is_for_treating_doctors_only(): void
    {
        $n = $this->svc()->create($this->doctor, $this->patient, PatientNote::CLINICAL, 'Suspected hypertension');

        $this->assertSame('Suspected hypertension', $n->fresh()->body);
        $this->assertNotSame('Suspected hypertension', \DB::table('patient_notes')->value('body'), 'Encrypted.');

        foreach ([$this->doctor, $this->colleague] as $ok) {
            $this->assertTrue($this->svc()->canRead($ok, $n), 'treating doctor');
            $this->assertSame([$n->id], $this->ids($this->svc()->readableFor($ok, $this->patient)));
        }
        foreach ([$this->stranger, $this->coach, $this->otherCoach, $this->ops, $this->patient] as $no) {
            $this->assertFalse($this->svc()->canRead($no, $n), $no->role->value);
            $this->assertSame([], $this->ids($this->svc()->readableFor($no, $this->patient)));
        }
    }

    public function test_a_patient_summary_is_readable_by_the_patient_and_treating_doctors_only(): void
    {
        $n = $this->svc()->create($this->doctor, $this->patient, PatientNote::PATIENT_SUMMARY, 'Your blood pressure is fine.');

        foreach ([$this->doctor, $this->colleague, $this->patient] as $ok) {
            $this->assertTrue($this->svc()->canRead($ok, $n), $ok->role->value);
        }
        foreach ([$this->stranger, $this->coach, $this->ops, $this->user(UserRole::Patient)] as $no) {
            $this->assertFalse($this->svc()->canRead($no, $n), $no->role->value);
        }
        $this->assertSame([$n->id], $this->ids($this->svc()->summariesFor($this->patient)));
    }

    public function test_a_patient_never_sees_clinical_or_staff_notes(): void
    {
        $this->svc()->create($this->doctor, $this->patient, PatientNote::CLINICAL, 'Private');
        $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'Chase the pharmacy', [['role' => 'admin']]);
        $summary = $this->svc()->create($this->doctor, $this->patient, PatientNote::PATIENT_SUMMARY, 'All good');

        $this->assertSame([$summary->id], $this->ids($this->svc()->readableFor($this->patient, $this->patient)));
        $this->assertSame([$summary->id], $this->ids($this->svc()->summariesFor($this->patient)));
    }

    public function test_a_staff_note_goes_only_to_those_it_is_addressed_to(): void
    {
        $n = $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'Please check stock', [['role' => 'admin'], ['user_id' => $this->coach->id]]);

        foreach ([$this->doctor, $this->ops, $this->coach] as $ok) {
            $this->assertTrue($this->svc()->canRead($ok, $n), $ok->role->value.' (author, role or named)');
        }
        // a second admin is in the Operations role, so the role address reaches them too
        $this->assertTrue($this->svc()->canRead($this->user(UserRole::Admin), $n));
        $this->assertFalse($this->svc()->canRead($this->colleague, $n));
        $this->assertFalse($this->svc()->canRead($this->stranger, $n));
        $this->assertFalse($this->svc()->canRead($this->otherCoach, $n));
        $this->assertFalse($this->svc()->canRead($this->patient, $n));
    }

    public function test_operations_does_not_read_a_note_that_was_not_addressed_to_it(): void
    {
        $toCoach = $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'Coach: encourage walking', [['user_id' => $this->coach->id]]);
        $toDoctors = $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'For the team', [['role' => 'doctor']]);

        $this->assertFalse($this->svc()->canRead($this->ops, $toCoach));
        $this->assertFalse($this->svc()->canRead($this->ops, $toDoctors));
        $this->assertSame([], $this->ids($this->svc()->inbox($this->ops)));
    }

    public function test_a_role_address_needs_access_to_the_patient_but_a_named_person_does_not(): void
    {
        $role = $this->svc()->create($this->ops, $this->patient, PatientNote::OPERATIONS, 'To every doctor', [['role' => 'doctor']]);
        $this->assertTrue($this->svc()->canRead($this->colleague, $role));
        $this->assertFalse($this->svc()->canRead($this->stranger, $role), 'A doctor with no link to the patient is not reached by a role address.');

        $named = $this->svc()->create($this->ops, $this->patient, PatientNote::OPERATIONS, 'Dr X, please look', [['user_id' => $this->stranger->id]]);
        $this->assertTrue($this->svc()->canRead($this->stranger, $named), 'Named on purpose: they are being asked to act.');

        $coachRole = $this->svc()->create($this->ops, $this->patient, PatientNote::OPERATIONS, 'To the coaches', [['role' => 'health_coach']]);
        $this->assertTrue($this->svc()->canRead($this->coach, $coachRole));
        $this->assertFalse($this->svc()->canRead($this->otherCoach, $coachRole), 'Only the assigned coach.');
    }

    public function test_who_may_write_what(): void
    {
        $this->assertSame(['clinical', 'patient_summary', 'operations'], $this->svc()->typesFor($this->doctor, $this->patient));
        $this->assertSame([], $this->svc()->typesFor($this->stranger, $this->patient));
        $this->assertSame(['operations'], $this->svc()->typesFor($this->coach, $this->patient));
        $this->assertSame([], $this->svc()->typesFor($this->otherCoach, $this->patient));
        $this->assertSame(['operations'], $this->svc()->typesFor($this->ops, $this->patient));
        $this->assertSame([], $this->svc()->typesFor($this->patient, $this->patient));

        $this->forbidden(fn () => $this->svc()->create($this->ops, $this->patient, PatientNote::CLINICAL, 'x'));
        $this->forbidden(fn () => $this->svc()->create($this->coach, $this->patient, PatientNote::PATIENT_SUMMARY, 'x'));
        $this->forbidden(fn () => $this->svc()->create($this->stranger, $this->patient, PatientNote::CLINICAL, 'x'));
        $this->forbidden(fn () => $this->svc()->create($this->patient, $this->patient, PatientNote::OPERATIONS, 'x', [['role' => 'admin']]));
        $this->assertSame(0, PatientNote::count());
    }

    public function test_validation(): void
    {
        $order = Order::create(['patient_id' => $this->user(UserRole::Patient)->id, 'status' => 'InReview', 'total_minor' => 1, 'ordered_at' => now()]);

        $cases = [
            'body' => fn () => $this->svc()->create($this->doctor, $this->patient, PatientNote::CLINICAL, '   '),
            'body ' => fn () => $this->svc()->create($this->doctor, $this->patient, PatientNote::CLINICAL, str_repeat('a', 5001)),
            'order' => fn () => $this->svc()->create($this->doctor, $this->patient, PatientNote::CLINICAL, 'x', [], $order->id),
            'audience' => fn () => $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'x', []),
            'audience ' => fn () => $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'x', [['user_id' => $this->patient->id]]),
            'audience  ' => fn () => $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'x', [['role' => 'patient']]),
            'audience   ' => fn () => $this->svc()->create($this->doctor, $this->patient, PatientNote::CLINICAL, 'x', [['role' => 'admin']]),
        ];
        foreach ($cases as $field => $try) {
            try {
                $try();
                $this->fail("Should be refused: $field");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(trim($field), $e->errors());
            }
        }
        $this->assertSame(0, PatientNote::count());
    }

    public function test_an_action_stays_open_until_an_addressee_marks_it_done(): void
    {
        $n = $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'Call the patient about delivery', [['role' => 'admin']], null, true);
        $this->assertSame('open', $n->action_status);

        $this->assertSame([$n->id], $this->ids($this->svc()->inbox($this->ops, true)));

        $this->forbidden(fn () => $this->svc()->completeAction($this->stranger, $n));
        $this->forbidden(fn () => $this->svc()->completeAction($this->patient, $n));

        $done = $this->svc()->completeAction($this->ops, $n);
        $this->assertSame('done', $done->action_status);
        $this->assertSame($this->ops->id, $done->action_done_by);
        $this->assertNotNull($done->action_done_at);
        $this->assertSame([], $this->ids($this->svc()->inbox($this->ops, true)), 'No longer waiting.');
        $this->assertSame([$n->id], $this->ids($this->svc()->inbox($this->ops)));

        $this->expectException(ValidationException::class);
        $this->svc()->completeAction($this->ops, $n->fresh());
    }

    public function test_a_note_without_an_action_cannot_be_completed(): void
    {
        $n = $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'FYI', [['role' => 'admin']]);
        $this->assertSame('none', $n->action_status);

        $this->expectException(ValidationException::class);
        $this->svc()->completeAction($this->ops, $n);
    }

    public function test_marking_a_named_note_as_read(): void
    {
        $n = $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'Hello', [['user_id' => $this->coach->id]]);

        $this->svc()->markRead($this->coach, $n);
        $this->assertNotNull($n->audiences()->first()->read_at);

        $this->forbidden(fn () => $this->svc()->markRead($this->stranger, $n));
    }

    public function test_notes_cannot_be_edited_through_the_service_and_the_author_stays_visible(): void
    {
        $n = $this->svc()->create($this->coach, $this->patient, PatientNote::OPERATIONS, 'Patient is travelling', [['role' => 'doctor']]);

        $this->assertSame($this->coach->id, $n->author_id);
        $this->assertTrue($this->svc()->canRead($this->coach, $n), 'The author always reads their own.');
        $this->assertTrue($this->svc()->canRead($this->doctor, $n));
        $this->assertFalse(method_exists($this->svc(), 'update'), 'Append-only: a correction is a new note.');
        $this->assertFalse(method_exists($this->svc(), 'delete'));
    }

    public function test_the_order_is_recorded_and_must_belong_to_the_patient(): void
    {
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'InReview', 'total_minor' => 1, 'ordered_at' => now()]);

        $n = $this->svc()->create($this->doctor, $this->patient, PatientNote::CLINICAL, 'About this order', [], $order->id);

        $this->assertSame($order->id, $n->order_id);
    }

    public function test_coach_notes_stay_invisible_to_operations_and_to_this_service(): void
    {
        $private = CoachNote::create(['patient_id' => $this->patient->id, 'author_id' => $this->coach->id, 'visibility' => 'private', 'body' => 'Coach secret']);
        $shared = CoachNote::create(['patient_id' => $this->patient->id, 'author_id' => $this->coach->id, 'visibility' => 'shared', 'body' => 'Shared with doctor']);

        $this->assertSame([$shared->id], CoachNote::readableBy($this->doctor)->pluck('id')->all(), 'The coach app decides who reads coach notes.');
        $this->assertSame([], $this->ids($this->svc()->readableFor($this->ops, $this->patient)), 'Operations sees no coach text through the notes service.');
        $this->assertSame([], $this->ids($this->svc()->readableFor($this->doctor, $this->patient)));
        $this->assertNotContains($private->id, CoachNote::readableBy($this->doctor)->pluck('id')->all());
    }

    public function test_messages_are_off_by_default_and_never_carry_the_note(): void
    {
        $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'Secret detail one', [['role' => 'admin']]);
        $this->assertSame(0, NotificationLog::count());

        config(['doctor.notes.notify_enabled' => true]);
        $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'Secret detail two', [['role' => 'admin'], ['user_id' => $this->coach->id]]);

        $this->assertGreaterThan(0, NotificationLog::count());
        foreach (NotificationLog::all() as $row) {
            $this->assertStringNotContainsString('Secret', json_encode($row->toArray()));
        }

        $other = NotificationLog::count();
        $this->svc()->create($this->doctor, $this->patient, PatientNote::CLINICAL, 'Clinical stays quiet');
        $this->assertSame($other, NotificationLog::count(), 'Clinical notes notify nobody.');
    }

    public function test_the_panel_and_the_inbox_screens(): void
    {
        Livewire::actingAs($this->doctor)->test(NotesPanel::class, ['patientId' => $this->patient->id])
            ->set('type', 'clinical')->set('body', 'Clinical thing')->call('save')->assertHasNoErrors()
            ->assertSee('Clinical thing')
            ->set('type', 'operations')->set('body', 'Chase Rx')->call('save')->assertHasErrors('audience')
            ->set('roles', ['admin'])->set('actionRequired', true)->call('save')->assertHasNoErrors()
            ->assertSee('Chase Rx')->assertSee('Action needed');

        Livewire::actingAs($this->ops)->test(StaffNotes::class)
            ->assertSee('Chase Rx')->assertDontSee('Clinical thing')
            ->call('done', PatientNote::where('body', 'like', '%')->get()->first(fn ($n) => $n->type === 'operations')->id)
            ->assertHasNoErrors()
            ->assertSee('Nothing here');

        // the admin and the coach see only what is theirs inside the panel as well
        Livewire::actingAs($this->ops)->test(NotesPanel::class, ['patientId' => $this->patient->id])
            ->assertSee('Chase Rx')->assertDontSee('Clinical thing');
        Livewire::actingAs($this->coach)->test(NotesPanel::class, ['patientId' => $this->patient->id])
            ->assertDontSee('Chase Rx')->assertDontSee('Clinical thing');
    }

    public function test_a_stranger_cannot_complete_someone_elses_action_from_the_screen(): void
    {
        $n = $this->svc()->create($this->doctor, $this->patient, PatientNote::OPERATIONS, 'Do it', [['role' => 'admin']], null, true);

        Livewire::actingAs($this->stranger)->test(StaffNotes::class)->call('done', $n->id)->assertForbidden();
        $this->assertSame('open', $n->fresh()->action_status);
    }

    public function test_pages(): void
    {
        foreach ([$this->doctor, $this->ops, $this->coach] as $who) {
            $this->actingAs($who)->get(route('staff.notes'))->assertOk();
        }
        $this->actingAs($this->patient)->get(route('staff.notes'))->assertForbidden();

        $appt = Appointment::where('doctor_id', $this->doctor->id)->firstOrFail();
        $this->actingAs($this->doctor)->get(route('doctor.consultation', $appt))->assertOk()->assertSee('data-panel="notes"', false);
    }
}
