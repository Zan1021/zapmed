<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Doctor\RequestDetail;
use App\Livewire\Doctor\Requests;
use App\Models\Appointment;
use App\Models\CoachingAssignment;
use App\Models\DoctorRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\Doctor\DoctorRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 2.1 Doctor Requests (Craig's guide 4.1, 8.1). Who can ask, who can answer, who can see, the status
 * chain Pending > In review > In progress > Submitted > Completed, patient references and private attachments.
 */
class DoctorRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['doctor.requests.disk' => 'local']);
        Storage::fake('local');
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function svc(): DoctorRequestService
    {
        return app(DoctorRequestService::class);
    }

    private function treat(User $doctor, User $patient): void
    {
        Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);
    }

    private function make(User $from, User $to, array $extra = []): DoctorRequest
    {
        return $this->svc()->create($from, ['title' => 'Check this', 'description' => 'Please look', 'assignee_ids' => [$to->id]] + $extra);
    }

    public function test_operations_can_ask_a_doctor_and_the_request_starts_pending(): void
    {
        $ops = $this->user(UserRole::Admin);
        $doctor = $this->user(UserRole::Doctor);

        $r = $this->make($ops, $doctor);

        $this->assertSame(DoctorRequest::PENDING, $r->status);
        $this->assertSame('Please look', $r->fresh()->description);
        $this->assertDatabaseHas('doctor_request_events', ['doctor_request_id' => $r->id, 'event' => 'created', 'user_id' => $ops->id]);
        $this->assertNotSame('Please look', \DB::table('doctor_requests')->value('description'), 'Description is stored encrypted.');
    }

    public function test_patients_and_pharmacy_cannot_create_requests(): void
    {
        $doctor = $this->user(UserRole::Doctor);

        foreach ([UserRole::Patient, UserRole::PharmacyStaff] as $role) {
            try {
                $this->make($this->user($role), $doctor);
                $this->fail('Should be forbidden');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertSame(0, DoctorRequest::count());
    }

    public function test_requests_can_only_go_to_doctors(): void
    {
        $this->expectException(ValidationException::class);
        $this->make($this->user(UserRole::Admin), $this->user(UserRole::HealthCoach));
    }

    public function test_title_and_an_assignee_are_required(): void
    {
        $ops = $this->user(UserRole::Admin);
        $doctor = $this->user(UserRole::Doctor);

        foreach ([['title' => ' ', 'assignee_ids' => [$doctor->id]], ['title' => 'x', 'assignee_ids' => []]] as $data) {
            try {
                $this->svc()->create($ops, $data);
                $this->fail('Should fail validation');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_the_status_chain_runs_in_order(): void
    {
        $coach = $this->user(UserRole::HealthCoach);
        $doctor = $this->user(UserRole::Doctor);
        $r = $this->make($coach, $doctor);

        $this->svc()->open($doctor, $r);
        $this->assertSame(DoctorRequest::IN_REVIEW, $r->fresh()->status);
        $this->assertNotNull($r->fresh()->opened_at);

        $this->svc()->open($doctor, $r->fresh());   // opening again changes nothing
        $this->assertSame(DoctorRequest::IN_REVIEW, $r->fresh()->status);

        $this->svc()->start($doctor, $r->fresh());
        $this->assertSame(DoctorRequest::IN_PROGRESS, $r->fresh()->status);

        $this->svc()->submit($doctor, $r->fresh(), 'Done, see attached');
        $r = $r->fresh();
        $this->assertSame(DoctorRequest::SUBMITTED, $r->status);
        $this->assertSame('Done, see attached', $r->response);
        $this->assertNotNull($r->submitted_at);

        $this->svc()->complete($coach, $r);
        $this->assertSame(DoctorRequest::COMPLETED, $r->fresh()->status);
        $this->assertNotNull($r->fresh()->completed_at);

        $this->assertSame(
            ['created', 'status', 'status', 'status', 'status'],
            \DB::table('doctor_request_events')->where('doctor_request_id', $r->id)->orderBy('id')->pluck('event')->all(),
        );
    }

    public function test_only_an_assignee_can_open_start_or_answer(): void
    {
        $ops = $this->user(UserRole::Admin);
        $doctor = $this->user(UserRole::Doctor);
        $other = $this->user(UserRole::Doctor);
        $r = $this->make($ops, $doctor);

        $this->svc()->open($other, $r);   // not an assignee: nothing happens
        $this->assertSame(DoctorRequest::PENDING, $r->fresh()->status);

        foreach ([fn () => $this->svc()->start($other, $r), fn () => $this->svc()->submit($other, $r, 'x'), fn () => $this->svc()->start($ops, $r)] as $try) {
            try {
                $try();
                $this->fail('Should be forbidden');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_a_response_cannot_be_empty_and_complete_needs_a_submitted_request(): void
    {
        $ops = $this->user(UserRole::Admin);
        $doctor = $this->user(UserRole::Doctor);
        $r = $this->make($ops, $doctor);

        try {
            $this->svc()->submit($doctor, $r, '   ');
            $this->fail('Empty response');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('response', $e->errors());
        }

        try {
            $this->svc()->complete($ops, $r);
            $this->fail('Not submitted yet');
        } catch (ValidationException $e) {
            $this->assertSame(DoctorRequest::PENDING, $r->fresh()->status);
        }
    }

    public function test_only_the_requester_or_operations_can_complete(): void
    {
        $coach = $this->user(UserRole::HealthCoach);
        $doctor = $this->user(UserRole::Doctor);
        $ops = $this->user(UserRole::Admin);
        $r = $this->make($coach, $doctor);
        $this->svc()->submit($doctor, $r, 'Answer');

        try {
            $this->svc()->complete($doctor, $r->fresh());   // the assignee cannot close their own work
            $this->fail('Should be forbidden');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->svc()->complete($ops, $r->fresh());
        $this->assertSame(DoctorRequest::COMPLETED, $r->fresh()->status);
    }

    public function test_visibility_requester_assignee_participant_and_operations_only(): void
    {
        $coach = $this->user(UserRole::HealthCoach);
        $doctor = $this->user(UserRole::Doctor);
        $looped = $this->user(UserRole::Doctor);
        $stranger = $this->user(UserRole::Doctor);
        $ops = $this->user(UserRole::Admin);
        $r = $this->make($coach, $doctor, ['participant_ids' => [$looped->id]]);

        foreach ([$coach, $doctor, $looped, $ops] as $u) {
            $this->assertTrue($this->svc()->canView($u, $r), $u->role->value);
        }
        $this->assertFalse($this->svc()->canView($stranger, $r));

        $this->assertSame([$r->id], $this->svc()->visibleTo($looped)->pluck('id')->all());
        $this->assertSame([], $this->svc()->visibleTo($stranger)->pluck('id')->all());
    }

    public function test_patient_references_follow_access_rules(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $coach = $this->user(UserRole::HealthCoach);
        $ops = $this->user(UserRole::Admin);
        $target = $this->user(UserRole::Doctor);
        $mine = $this->user(UserRole::Patient);
        $notMine = $this->user(UserRole::Patient);
        $this->treat($doctor, $mine);
        CoachingAssignment::create(['patient_id' => $mine->id, 'coach_id' => $coach->id, 'started_at' => now()]);

        $this->make($doctor, $target, ['refs' => [['patient_id' => $mine->id]]]);
        $this->make($coach, $target, ['refs' => [['patient_id' => $mine->id]]]);
        $this->make($ops, $target, ['refs' => [['patient_id' => $notMine->id]]]);
        $this->assertSame(3, \DB::table('doctor_request_refs')->count());

        foreach ([$doctor, $coach] as $who) {
            try {
                $this->make($who, $target, ['refs' => [['patient_id' => $notMine->id]]]);
                $this->fail('Should not reach another staff member\'s patient');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('refs', $e->errors());
            }
        }
    }

    public function test_an_order_reference_must_belong_to_that_patient(): void
    {
        $ops = $this->user(UserRole::Admin);
        $doctor = $this->user(UserRole::Doctor);
        $a = $this->user(UserRole::Patient);
        $b = $this->user(UserRole::Patient);
        $orderOfB = Order::create(['patient_id' => $b->id, 'status' => 'InReview', 'total_minor' => 100, 'ordered_at' => now()]);
        $orderOfA = Order::create(['patient_id' => $a->id, 'status' => 'InReview', 'total_minor' => 100, 'ordered_at' => now()]);

        $this->make($ops, $doctor, ['refs' => [['patient_id' => $a->id, 'order_id' => $orderOfA->id]]]);
        $this->assertDatabaseHas('doctor_request_refs', ['patient_id' => $a->id, 'order_id' => $orderOfA->id]);

        $this->expectException(ValidationException::class);
        $this->make($ops, $doctor, ['refs' => [['patient_id' => $a->id, 'order_id' => $orderOfB->id]]]);
    }

    public function test_attachments_are_private_checked_and_served_only_to_people_on_the_request(): void
    {
        $ops = $this->user(UserRole::Admin);
        $doctor = $this->user(UserRole::Doctor);
        $stranger = $this->user(UserRole::Doctor);
        $r = $this->make($ops, $doctor);

        $file = $this->svc()->attach($doctor, $r, UploadedFile::fake()->create('../../results.pdf', 50, 'application/pdf'));
        Storage::disk('local')->assertExists($file->path);
        $this->assertStringNotContainsString('results', $file->path, 'Stored under a random name.');
        $this->assertStringNotContainsString('..', $file->original_name);

        $this->actingAs($doctor)->get(route('doctor.requests.file', [$r, $file]))->assertOk();
        $this->actingAs($ops)->get(route('doctor.requests.file', [$r, $file]))->assertOk();
        $this->actingAs($stranger)->get(route('doctor.requests.file', [$r, $file]))->assertForbidden();

        $other = $this->make($ops, $doctor);
        $this->actingAs($doctor)->get(route('doctor.requests.file', [$other, $file]))->assertNotFound();
    }

    public function test_bad_file_types_and_big_files_are_refused_and_completed_requests_are_closed(): void
    {
        $ops = $this->user(UserRole::Admin);
        $doctor = $this->user(UserRole::Doctor);
        $r = $this->make($ops, $doctor);

        foreach ([UploadedFile::fake()->create('run.exe', 10), UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf')] as $bad) {
            try {
                $this->svc()->attach($doctor, $r, $bad);
                $this->fail('Should be refused');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('file', $e->errors());
            }
        }

        $this->svc()->submit($doctor, $r, 'Done');
        $this->svc()->complete($ops, $r->fresh());

        $this->expectException(ValidationException::class);
        $this->svc()->attach($doctor, $r->fresh(), UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'));
    }

    public function test_messages_are_off_by_default_and_never_carry_request_text(): void
    {
        $this->assertFalse(config('doctor.requests.notify_enabled'));

        $ops = $this->user(UserRole::Admin);
        $doctor = $this->user(UserRole::Doctor);
        $this->make($ops, $doctor);
        $this->assertSame(0, \App\Models\NotificationLog::count());

        config(['doctor.requests.notify_enabled' => true]);
        $this->make($ops, $doctor, ['title' => 'Secret patient title', 'description' => 'Secret body']);

        $logged = \App\Models\NotificationLog::all();
        $this->assertGreaterThan(0, $logged->count());
        foreach ($logged as $row) {
            $this->assertStringNotContainsString('Secret', json_encode($row->toArray()));
        }
    }

    public function test_the_pages_work_for_each_role_and_block_patients(): void
    {
        $ops = $this->user(UserRole::Admin);
        $doctor = $this->user(UserRole::Doctor);
        $r = $this->make($ops, $doctor);

        $this->actingAs($this->user(UserRole::Patient))->get(route('doctor.requests'))->assertForbidden();

        $this->actingAs($doctor)->get(route('doctor.requests'))->assertOk()->assertSee('Check this');
        $this->actingAs($doctor)->get(route('doctor.requests.show', $r))->assertOk()->assertSee('Please look');
        $this->assertSame(DoctorRequest::IN_REVIEW, $r->fresh()->status, 'Opening the page as the assignee moves it to In review.');

        $this->actingAs($this->user(UserRole::Doctor))->get(route('doctor.requests.show', $r))->assertForbidden();
        $this->actingAs($ops)->get(route('doctor.requests'))->assertOk();
    }

    public function test_livewire_create_screen_and_answer_screen(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $coach = $this->user(UserRole::HealthCoach);
        $patient = $this->user(UserRole::Patient);
        CoachingAssignment::create(['patient_id' => $patient->id, 'coach_id' => $coach->id, 'started_at' => now()]);

        Livewire::actingAs($coach)->test(Requests::class)
            ->call('newRequest')
            ->set('title', 'Dose question')
            ->set('description', 'Is this ok?')
            ->set('assigneeIds', [$doctor->id])
            ->set('patientSearch', substr($patient->first_name, 0, 3))
            ->call('addPatient', $patient->id)
            ->call('create')
            ->assertHasNoErrors();

        $r = DoctorRequest::firstOrFail();
        $this->assertSame($coach->id, $r->requester_id);
        $this->assertSame([$patient->id], $r->refs->pluck('patient_id')->all());

        Livewire::actingAs($doctor)->test(RequestDetail::class, ['doctorRequest' => $r])
            ->call('start')
            ->set('response', 'Yes, fine')
            ->call('submit')
            ->assertSee('Yes, fine');

        $this->assertSame(DoctorRequest::SUBMITTED, $r->fresh()->status);

        Livewire::actingAs($coach)->test(RequestDetail::class, ['doctorRequest' => $r->fresh()])
            ->call('complete');
        $this->assertSame(DoctorRequest::COMPLETED, $r->fresh()->status);
    }

    public function test_a_coach_cannot_search_for_patients_that_are_not_theirs(): void
    {
        $coach = $this->user(UserRole::HealthCoach);
        $stranger = $this->user(UserRole::Patient);

        Livewire::actingAs($coach)->test(Requests::class)
            ->set('patientSearch', substr($stranger->first_name, 0, 3))
            ->call('addPatient', $stranger->id)
            ->assertSet('refs', []);
    }
}
