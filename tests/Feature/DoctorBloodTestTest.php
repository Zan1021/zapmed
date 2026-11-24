<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Doctor\BloodTestPanel;
use App\Livewire\Doctor\BloodTests;
use App\Models\Appointment;
use App\Models\BloodTestRequest;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\User;
use App\Services\Doctor\BloodTestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 2.4 blood tests (Craig's guide 4.1, 4.2): ticked tests + free text + lab form, order status moves,
 * results from the patient or a doctor, review, cancel, and private access (no Operations, no coach).
 */
class DoctorBloodTestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['doctor.blood_tests.disk' => 'local']);
        Storage::fake('local');
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function svc(): BloodTestService
    {
        return app(BloodTestService::class);
    }

    /** @return array{0:User,1:User,2:Order} */
    private function setup3(string $status = 'InReview'): array
    {
        $doctor = $this->user(UserRole::Doctor);
        $patient = $this->user(UserRole::Patient);
        Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);

        return [$doctor, $patient, Order::create(['patient_id' => $patient->id, 'status' => $status, 'total_minor' => 100, 'ordered_at' => now()])];
    }

    private function pdf(string $name = 'results.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 40, 'application/pdf');
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

    public function test_a_doctor_requests_tests_and_an_in_review_order_waits(): void
    {
        [$doctor, $patient, $order] = $this->setup3('InReview');

        $r = $this->svc()->request($doctor, $patient, ['hba1c', 'lipids'], 'Fasting please', 'pathcare', null, $order);

        $this->assertSame(BloodTestRequest::REQUESTED, $r->status);
        $this->assertSame(['hba1c', 'lipids'], $r->tests);
        $this->assertSame(['HbA1c', 'Cholesterol (lipid profile)'], $r->testNames());
        $this->assertSame('Fasting please', $r->fresh()->free_text);
        $this->assertNotSame('Fasting please', \DB::table('blood_test_requests')->value('free_text'), 'Encrypted.');
        $this->assertSame('PendingBloodTests', $order->fresh()->status);
        $this->assertSame('InReview', $r->order_status_before);
    }

    public function test_an_order_in_another_status_is_left_alone_and_an_order_is_optional(): void
    {
        [$doctor, $patient, $order] = $this->setup3('Delivered');

        $this->svc()->request($doctor, $patient, ['fbc'], null, null, null, $order);
        $this->assertSame('Delivered', $order->fresh()->status);

        $this->svc()->request($doctor, $patient, [], 'Just a general check');
        $this->assertSame(2, BloodTestRequest::count());
    }

    public function test_validation_of_tests_forms_and_orders(): void
    {
        [$doctor, $patient] = $this->setup3();
        $other = Order::create(['patient_id' => $this->user(UserRole::Patient)->id, 'status' => 'InReview', 'total_minor' => 1, 'ordered_at' => now()]);

        foreach ([
            ['args' => [[], null], 'field' => 'tests'],
            ['args' => [['fbc', 'nonsense'], null], 'field' => 'tests'],
            ['args' => [['fbc'], null, 'nolab'], 'field' => 'lab_form'],
            ['args' => [['fbc'], null, null, null, $other], 'field' => 'order'],
            ['args' => [['fbc'], null, null, UploadedFile::fake()->create('form.exe', 5)], 'field' => 'lab_form_file'],
        ] as $case) {
            try {
                $this->svc()->request($doctor, $patient, ...$case['args']);
                $this->fail('Should be refused: '.$case['field']);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($case['field'], $e->errors());
            }
        }
        $this->assertSame(0, BloodTestRequest::count());
    }

    public function test_only_a_treating_doctor_can_request(): void
    {
        [, $patient] = $this->setup3();

        $this->forbidden(fn () => $this->svc()->request($this->user(UserRole::Doctor), $patient, ['fbc']));
        $this->forbidden(fn () => $this->svc()->request($this->user(UserRole::Admin), $patient, ['fbc']));
        $this->forbidden(fn () => $this->svc()->request($this->user(UserRole::HealthCoach), $patient, ['fbc']));
        $this->assertSame(0, BloodTestRequest::count());
    }

    public function test_a_patient_upload_moves_the_order_to_review_and_the_review_brings_it_back(): void
    {
        [$doctor, $patient, $order] = $this->setup3('InReview');
        $r = $this->svc()->request($doctor, $patient, ['hba1c'], null, null, null, $order);

        try {
            $this->svc()->review($doctor, $r, 'x');
            $this->fail('No results yet');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no results', $e->errors()['status'][0]);
        }

        $res = $this->svc()->uploadResult($patient, $r, $this->pdf());
        $this->assertSame('patient', $res->uploader_role);
        Storage::disk('local')->assertExists($res->path);
        $this->assertSame(BloodTestRequest::RESULTS_RECEIVED, $r->fresh()->status);
        $this->assertSame('PendingReviewFeedback', $order->fresh()->status);
        $this->assertDatabaseHas('order_status_history', ['order_id' => $order->id, 'to_status' => 'PendingReviewFeedback', 'trigger_type' => 'patient']);

        $this->svc()->uploadResult($patient, $r->fresh(), $this->pdf('page2.pdf'));   // more files are fine
        $this->assertSame(2, $r->results()->count());

        $r = $this->svc()->review($doctor, $r->fresh(), 'All normal');
        $this->assertSame(BloodTestRequest::REVIEWED, $r->status);
        $this->assertSame('All normal', $r->review_note);
        $this->assertSame($doctor->id, $r->closed_by);
        $this->assertSame('InReview', $order->fresh()->status);
    }

    public function test_a_doctor_can_upload_results_too(): void
    {
        [$doctor, $patient, $order] = $this->setup3('InReview');
        $r = $this->svc()->request($doctor, $patient, ['fbc'], null, null, null, $order);

        $res = $this->svc()->uploadResult($doctor, $r, $this->pdf());

        $this->assertSame('doctor', $res->uploader_role);
        $this->assertDatabaseHas('order_status_history', ['order_id' => $order->id, 'to_status' => 'PendingReviewFeedback', 'trigger_type' => 'doctor']);
    }

    public function test_nobody_else_can_upload_or_review(): void
    {
        [$doctor, $patient] = $this->setup3();
        $r = $this->svc()->request($doctor, $patient, ['fbc']);

        foreach ([$this->user(UserRole::Patient), $this->user(UserRole::Doctor), $this->user(UserRole::Admin), $this->user(UserRole::HealthCoach)] as $who) {
            $this->forbidden(fn () => $this->svc()->uploadResult($who, $r, $this->pdf()));
        }

        $this->svc()->uploadResult($patient, $r, $this->pdf());
        $this->forbidden(fn () => $this->svc()->review($patient, $r->fresh()));
        $this->forbidden(fn () => $this->svc()->review($this->user(UserRole::Doctor), $r->fresh()));
        $this->forbidden(fn () => $this->svc()->review($this->user(UserRole::Admin), $r->fresh()));
    }

    public function test_bad_result_files_are_refused_and_a_closed_request_takes_no_more(): void
    {
        [$doctor, $patient] = $this->setup3();
        $r = $this->svc()->request($doctor, $patient, ['fbc']);

        foreach ([UploadedFile::fake()->create('r.exe', 5), UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'), UploadedFile::fake()->create('r.docx', 5)] as $bad) {
            try {
                $this->svc()->uploadResult($patient, $r, $bad);
                $this->fail('Should be refused');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('file', $e->errors());
            }
        }

        $this->svc()->uploadResult($patient, $r, $this->pdf());
        $this->svc()->review($doctor, $r->fresh());

        $this->expectException(ValidationException::class);
        $this->svc()->uploadResult($patient, $r->fresh(), $this->pdf());
    }

    public function test_cancel_needs_a_reason_belongs_to_the_requesting_doctor_and_restores_the_order(): void
    {
        [$doctor, $patient, $order] = $this->setup3('InReview');
        $r = $this->svc()->request($doctor, $patient, ['fbc'], null, null, null, $order);

        $this->forbidden(fn () => $this->svc()->cancel($this->user(UserRole::Doctor), $r, 'x'));
        $this->forbidden(fn () => $this->svc()->cancel($this->user(UserRole::Admin), $r, 'x'));
        try {
            $this->svc()->cancel($doctor, $r, ' ');
            $this->fail('Reason');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $r = $this->svc()->cancel($doctor, $r, 'Patient had them done last week');
        $this->assertSame(BloodTestRequest::CANCELLED, $r->status);
        $this->assertSame('InReview', $order->fresh()->status);

        $this->expectException(ValidationException::class);
        $this->svc()->cancel($doctor, $r, 'again');
    }

    public function test_cancelling_after_results_also_puts_the_order_back(): void
    {
        [$doctor, $patient, $order] = $this->setup3('InReview');
        $r = $this->svc()->request($doctor, $patient, ['fbc'], null, null, null, $order);
        $this->svc()->uploadResult($patient, $r, $this->pdf());
        $this->assertSame('PendingReviewFeedback', $order->fresh()->status);

        $this->svc()->cancel($doctor, $r->fresh(), 'Wrong tests');
        $this->assertSame('InReview', $order->fresh()->status);
    }

    public function test_blood_tests_do_not_block_repeats(): void
    {
        $this->assertFalse(\App\Services\Orders\OrderStatusMachine::blocksRepeat('PendingBloodTests'));
        $this->assertFalse(\App\Services\Orders\OrderStatusMachine::blocksRepeat('PendingReviewFeedback'));
    }

    public function test_results_and_forms_are_private(): void
    {
        [$doctor, $patient] = $this->setup3();
        $r = $this->svc()->request($doctor, $patient, ['fbc'], null, null, UploadedFile::fake()->create('form.pdf', 5, 'application/pdf'));
        $res = $this->svc()->uploadResult($patient, $r, $this->pdf());
        $stranger = $this->user(UserRole::Doctor);

        $this->assertStringNotContainsString('results', $res->path);
        $this->assertStringNotContainsString('form.pdf', $r->lab_form_path);

        foreach ([$patient, $doctor] as $ok) {
            $this->actingAs($ok)->get(route('bloodtests.result', [$r, $res]))->assertOk();
            $this->actingAs($ok)->get(route('bloodtests.form', $r))->assertOk();
        }

        foreach ([$stranger, $this->user(UserRole::Patient)] as $no) {
            $this->actingAs($no)->get(route('bloodtests.result', [$r, $res]))->assertForbidden();
            $this->actingAs($no)->get(route('bloodtests.form', $r))->assertForbidden();
        }
        foreach ([UserRole::Admin, UserRole::HealthCoach, UserRole::PharmacyStaff] as $role) {
            $this->actingAs($this->user($role))->get(route('bloodtests.result', [$r, $res]))->assertForbidden();
        }

        $other = $this->svc()->request($doctor, $patient, ['tsh']);
        $this->actingAs($doctor)->get(route('bloodtests.result', [$other, $res]))->assertNotFound();
    }

    public function test_messages_are_off_by_default_and_never_name_the_tests(): void
    {
        [$doctor, $patient] = $this->setup3();
        $this->svc()->request($doctor, $patient, ['hba1c'], 'diabetes check');
        $this->assertSame(0, NotificationLog::count());

        config(['doctor.blood_tests.notify_enabled' => true]);
        $this->svc()->request($doctor, $patient, ['hba1c'], 'diabetes check');

        $this->assertGreaterThan(0, NotificationLog::count());
        foreach (NotificationLog::all() as $row) {
            $json = strtolower(json_encode($row->toArray()));
            $this->assertStringNotContainsString('hba1c', $json);
            $this->assertStringNotContainsString('diabetes', $json);
        }
    }

    public function test_the_screens_request_upload_review_and_cancel(): void
    {
        [$doctor, $patient, $order] = $this->setup3('InReview');

        Livewire::actingAs($doctor)->test(BloodTestPanel::class, ['patientId' => $patient->id])
            ->call('request')->assertHasErrors('tests')
            ->set('tests', ['fbc', 'tsh'])->set('orderId', $order->id)->set('labForm', 'lancet')
            ->call('request')->assertHasNoErrors();

        $r = BloodTestRequest::firstOrFail();
        $this->assertSame('PendingBloodTests', $order->fresh()->status);

        Livewire::actingAs($doctor)->test(BloodTests::class)
            ->assertSee('Full blood count')
            ->call('open', $r->id, 'upload')->set('upload', $this->pdf())->call('uploadResult')->assertHasNoErrors()
            ->assertSee('uploaded by doctor')
            ->call('open', $r->id, 'review')->set('text', 'Fine')->call('review')->assertHasNoErrors()
            ->set('filter', 'done')->assertSee('Fine');

        $this->assertSame(BloodTestRequest::REVIEWED, $r->fresh()->status);

        $second = $this->svc()->request($doctor, $patient, ['vitd']);
        Livewire::actingAs($doctor)->test(BloodTests::class)
            ->call('open', $second->id, 'cancel')->set('text', '')->call('cancel')->assertHasErrors('reason')
            ->set('text', 'Not needed')->call('cancel')->assertHasNoErrors();
        $this->assertSame(BloodTestRequest::CANCELLED, $second->fresh()->status);
    }

    public function test_a_doctor_who_does_not_treat_the_patient_cannot_act_on_the_request(): void
    {
        [$doctor, $patient] = $this->setup3();
        $r = $this->svc()->request($doctor, $patient, ['fbc']);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($this->user(UserRole::Doctor))->test(BloodTests::class)->set('acting', $r->id)->set('mode', 'cancel')->call('cancel');
    }

    public function test_the_pages_render_for_doctors_only_and_the_consultation_shows_the_panel(): void
    {
        [$doctor] = $this->setup3();

        $this->actingAs($doctor)->get(route('doctor.blood-tests'))->assertOk();
        foreach ([UserRole::Patient, UserRole::Admin, UserRole::HealthCoach] as $role) {
            $this->actingAs($this->user($role))->get(route('doctor.blood-tests'))->assertForbidden();
        }

        $appt = Appointment::where('doctor_id', $doctor->id)->firstOrFail();
        $this->actingAs($doctor)->get(route('doctor.consultation', $appt))->assertOk()->assertSee('data-panel="blood-tests"', false);
    }
}
