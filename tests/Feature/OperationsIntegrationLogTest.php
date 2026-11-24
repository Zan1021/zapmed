<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\IntegrationLogPage;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\IntegrationMessage;
use App\Models\Order;
use App\Models\OrderAuditEvent;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Operations\IntegrationLog;
use App\Services\PharmacyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4 leftover: the pharmacy message log with a safe resend (Craig's guide 6.2, 6.4). The rule: a prescription the pharmacy
 * may already have is never sent twice. Definite failures are retried by themselves (about ten times), unsure ones wait for a person.
 */
class OperationsIntegrationLogTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;
    private User $patient;
    private Prescription $rx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
        config(['services.pharmacy.enabled' => true, 'services.pharmacy.api_url' => 'https://pharmacy.example.test/api', 'services.pharmacy.api_key' => 'key']);

        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
        $this->patient = User::factory()->create(['role' => UserRole::Patient]);
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $appt = Appointment::create(['patient_id' => $this->patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed', 'appointment_date' => '2026-11-20', 'start_time' => '09:00', 'end_time' => '09:15', 'fee_amount' => 1]);
        $c = Consultation::create(['appointment_id' => $appt->id, 'patient_id' => $this->patient->id, 'doctor_id' => $doctor->id, 'status' => 'completed']);
        $this->rx = Prescription::create([
            'reference' => 'RX-LOG1', 'consultation_id' => $c->id, 'patient_id' => $this->patient->id, 'doctor_id' => $doctor->id,
            'status' => 'signed', 'total_amount' => 1, 'payment_status' => 'paid', 'pharmacy_status' => 'pending', 'signed_at' => now(), 'pharmacy_script_ref' => 'PH-SCRIPT-1',
        ]);
    }

    /** The pharmacy answers these in turn (the last one repeats). A string starting "cURL" is a connection error; an array is [body, status]. */
    private function pharmacyAnswers(array $answers): void
    {
        $n = 0;
        Http::fake(function () use (&$n, $answers) {
            $a = $answers[min($n++, count($answers) - 1)];
            if (is_string($a)) {
                throw new ConnectionException($a);
            }

            return Http::response($a[0], $a[1]);
        });
    }

    private function send(): array
    {
        return app(PharmacyService::class)->dispatch($this->rx->fresh());
    }

    private function log(): IntegrationLog
    {
        return app(IntegrationLog::class);
    }

    private function rows()
    {
        return IntegrationMessage::orderBy('id')->get();
    }

    public function test_a_successful_send_is_logged_with_the_code_and_a_summary_but_no_patient_detail(): void
    {
        Http::fake(['pharmacy.example.test/*' => Http::response(['reference' => 'PH-1'], 201)]);

        $r = $this->send();

        $this->assertTrue($r['success']);
        $row = IntegrationMessage::firstOrFail();
        $this->assertSame(['pharmacy', 'prescription_send', 'success', 1, 201], [$row->system, $row->type, $row->status, $row->attempt, $row->response_code]);
        $this->assertSame($this->rx->id, $row->prescription_id);
        $this->assertSame($this->patient->id, $row->patient_id);
        $this->assertSame('Prescription RX-LOG1 to the pharmacy (attempt 1)', $row->request_summary);
        $this->assertStringContainsString('PH-1', $row->response_excerpt);
        $this->assertNotSame('{"reference":"PH-1"}', \DB::table('integration_messages')->value('response_excerpt'), 'The response is stored encrypted.');
        $this->assertNull($row->next_retry_at);
    }

    public function test_the_log_row_is_linked_to_the_order_by_the_pharmacy_script_reference(): void
    {
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Processing', 'total_minor' => 1, 'ordered_at' => now(), 'pharmacy_script_ref' => 'PH-SCRIPT-1']);
        Order::create(['patient_id' => User::factory()->create(['role' => UserRole::Patient])->id, 'status' => 'Processing', 'total_minor' => 1, 'ordered_at' => now(), 'pharmacy_script_ref' => 'PH-SCRIPT-1']);
        Http::fake(['pharmacy.example.test/*' => Http::response([], 200)]);

        $this->send();

        $this->assertSame($order->id, IntegrationMessage::firstOrFail()->order_id, 'Only this patient\'s order.');
    }

    public function test_an_error_answer_is_a_definite_failure_and_schedules_a_retry(): void
    {
        Http::fake(['pharmacy.example.test/*' => Http::response('Bad request', 422)]);

        $r = $this->send();

        $this->assertFalse($r['success']);
        $row = IntegrationMessage::firstOrFail();
        $this->assertSame(['failed', 422], [$row->status, $row->response_code]);
        $this->assertSame('2026-11-25 11:05', $row->next_retry_at->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i'), 'First wait is 5 minutes.');
        $this->assertSame('failed', $this->rx->fresh()->pharmacy_status);
    }

    public function test_a_timeout_is_unknown_and_never_scheduled_for_an_automatic_retry(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 30001 milliseconds'));

        $this->send();

        $row = IntegrationMessage::firstOrFail();
        $this->assertSame('unknown', $row->status);
        $this->assertNull($row->next_retry_at);
        $this->assertStringContainsString('timed out', $row->error);
    }

    public function test_not_being_able_to_connect_at_all_is_a_definite_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to pharmacy.example.test'));

        $this->send();

        $row = IntegrationMessage::firstOrFail();
        $this->assertSame('failed', $row->status, 'Nothing could have been sent.');
        $this->assertNotNull($row->next_retry_at);
    }

    public function test_the_development_mode_send_is_logged_as_a_success_that_sent_nothing(): void
    {
        config(['services.pharmacy.enabled' => false]);
        Http::fake();

        $this->send();

        Http::assertNothingSent();
        $row = IntegrationMessage::firstOrFail();
        $this->assertSame('success', $row->status);
        $this->assertStringContainsString('nothing was really sent', $row->response_excerpt);
    }

    public function test_a_prescription_that_is_not_ready_is_not_logged_at_all(): void
    {
        $this->rx->update(['payment_status' => 'unpaid']);

        $r = $this->send();

        $this->assertFalse($r['success']);
        $this->assertSame(0, IntegrationMessage::count());
    }

    public function test_the_automatic_retry_waits_for_its_time_then_succeeds_and_stops(): void
    {
        Http::fake(['pharmacy.example.test/*' => Http::sequence()->push('Busy', 503)->push(['reference' => 'PH-9'], 200)]);
        $this->send();

        $this->assertSame(0, $this->log()->retryDue(), 'Not yet: 5 minutes have not passed.');

        $this->travel(6)->minutes();
        $this->assertSame(1, $this->log()->retryDue());

        $rows = $this->rows();
        $this->assertSame(['failed', 'success'], $rows->pluck('status')->all());
        $this->assertSame([1, 2], $rows->pluck('attempt')->all());
        $this->assertSame($rows[0]->id, $rows[1]->parent_id, 'Attempts are linked.');
        $this->assertSame('dispatched', $this->rx->fresh()->pharmacy_status);

        $this->travel(2)->days();
        $this->assertSame(0, $this->log()->retryDue(), 'Done: nothing more to send.');
    }

    public function test_waits_grow_and_it_gives_up_after_ten_attempts(): void
    {
        Http::fake(['pharmacy.example.test/*' => Http::response('Down', 500)]);
        $this->send();

        for ($i = 0; $i < 12; $i++) {
            $this->travel(2)->days();
            $this->log()->retryDue();
        }

        $rows = $this->rows();
        $this->assertCount(10, $rows);
        $this->assertSame(10, $rows->last()->attempt);
        $this->assertNull($rows->last()->next_retry_at, 'No eleventh attempt.');
        $this->assertSame([5, 15, 30, 60, 120, 240, 480, 720, 1440], array_slice(config('operations.integrations.retry_minutes'), 0, 9));
    }

    public function test_a_prescription_the_pharmacy_already_has_is_never_sent_again(): void
    {
        Http::fake(['pharmacy.example.test/*' => Http::response('Down', 500)]);
        $this->send();
        $failed = IntegrationMessage::firstOrFail();

        // meanwhile the pharmacy confirms it by another route
        $this->rx->update(['pharmacy_status' => 'dispatched']);
        $this->travel(1)->hours();

        $this->assertSame(0, $this->log()->retryDue());
        $this->assertCount(1, $this->rows());

        try {
            $this->log()->resend($this->ops, $failed);
            $this->fail('Already there');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already has', $e->errors()['resend'][0]);
        }
        Http::assertSentCount(1);
    }

    public function test_an_unknown_row_is_never_retried_by_itself_and_a_person_must_confirm_first(): void
    {
        $this->pharmacyAnswers(['cURL error 28: Operation timed out', [['reference' => 'PH-2'], 200]]);
        $this->send();
        $unknown = IntegrationMessage::firstOrFail();
        $this->travel(3)->days();

        $this->assertSame(0, $this->log()->retryDue());

        try {
            $this->log()->resend($this->ops, $unknown);
            $this->fail('Needs confirmation');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Check with them first', $e->errors()['resend'][0]);
        }
        $this->assertCount(1, $this->rows(), 'Nothing was sent again.');

        $result = $this->log()->resend($this->ops, $unknown, true);

        $this->assertTrue($result['success']);
        $sent = $this->rows()->last();
        $this->assertSame(['success', 2, $this->ops->id], [$sent->status, $sent->attempt, $sent->resent_by]);
    }

    public function test_a_definite_failure_can_be_resent_by_a_person_without_confirming_and_is_audited_on_the_order(): void
    {
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Processing', 'total_minor' => 1, 'ordered_at' => now(), 'pharmacy_script_ref' => 'PH-SCRIPT-1']);
        $this->pharmacyAnswers([['No', 400], [['reference' => 'PH-3'], 200]]);
        $this->send();

        $r = $this->log()->resend($this->ops, IntegrationMessage::firstOrFail());

        $this->assertTrue($r['success']);
        $this->assertSame(1, OrderAuditEvent::where('order_id', $order->id)->where('event', 'integration_resend')->count());
    }

    public function test_only_the_newest_attempt_can_be_resent_and_only_by_operations(): void
    {
        Http::fake(['pharmacy.example.test/*' => Http::response('No', 400)]);
        $this->send();
        $first = IntegrationMessage::firstOrFail();
        $this->travel(10)->minutes();
        $this->log()->retryDue();

        try {
            $this->log()->resend($this->ops, $first);
            $this->fail('A newer attempt exists');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('newer attempt', $e->errors()['resend'][0]);
        }

        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach] as $role) {
            try {
                $this->log()->resend(User::factory()->create(['role' => $role]), $this->rows()->last());
                $this->fail('Expected 403');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_the_command_runs_and_is_scheduled(): void
    {
        Http::fake(['pharmacy.example.test/*' => Http::response('No', 500)]);
        $this->send();
        $this->travel(10)->minutes();
        Http::fake(['pharmacy.example.test/*' => Http::response([], 200)]);

        $this->artisan('integrations:retry')->expectsOutput('Sent again: 1.')->assertSuccessful();

        $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command->implode(' ');
        $this->assertStringContainsString('integrations:retry', $scheduled);
    }

    public function test_the_page_shows_the_log_filters_it_and_resends_safely(): void
    {
        $this->pharmacyAnswers([['No', 400], [['reference' => 'PH-4'], 200]]);
        $this->send();
        $row = IntegrationMessage::firstOrFail();

        $this->actingAs($this->ops)->get(route('admin.integration-log'))->assertOk()->assertSee('Pharmacy messages');
        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.integration-log'))->assertForbidden();
        }

        Livewire::actingAs($this->ops)->test(IntegrationLogPage::class)
            ->assertSeeHtml('data-message="'.$row->id.'"')->assertSee('RX-LOG1')
            ->set('status', 'success')->assertDontSeeHtml('data-message="'.$row->id.'"')
            ->set('status', '')->set('search', 'nobody-here')->assertSeeHtml('data-empty="messages"')
            ->set('search', $this->patient->email)->assertSeeHtml('data-message="'.$row->id.'"')
            ->call('askResend', $row->id)->call('resend', $row->id)->assertSeeHtml('data-result')->assertSee('The pharmacy accepted it');

        $this->assertSame('dispatched', $this->rx->fresh()->pharmacy_status);
        Livewire::actingAs($this->ops)->test(IntegrationLogPage::class)->assertDontSeeHtml('data-action="resend"');
    }
}
