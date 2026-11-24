<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Patient\Notifications;
use App\Models\Appointment;
use App\Models\ConsentRecord;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Doctor\AuthorisationService;
use App\Services\Doctor\BloodTestService;
use App\Services\Doctor\FollowUpService;
use App\Services\Doctor\PatientNoteService;
use App\Services\Notifications\ChannelResult;
use App\Services\Notifications\Contracts\NotificationChannel;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use App\Services\Patient\PatientChannels;
use App\Services\Patient\PatientInbox;
use App\Services\Patient\PatientMessenger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3.4 Notifications tab (Craig's guide 3.1, 3.3): WhatsApp or email with recorded consent, the other channel as a
 * fallback, every attempt logged, and "from your care team" showing only what a patient may see.
 */
class PatientNotificationsTabTest extends TestCase
{
    use RefreshDatabase;

    public array $sent = [];
    public array $failing = [];          // channel keys that report a failed delivery
    public array $unconfigured = [];     // channel keys that are not set up

    private User $patient;
    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
        $this->sent = [];
        $this->failing = [];
        $this->unconfigured = [];

        $dispatcher = app(NotificationDispatcher::class);
        foreach (['email', 'sms', 'whatsapp'] as $key) {
            $dispatcher->register($this->channel($key));
        }
        $this->app->instance(NotificationDispatcher::class, $dispatcher);

        $this->patient = User::factory()->create(['role' => UserRole::Patient, 'phone' => '0821234567', 'preferred_contact' => null]);
        PatientProfile::create(['user_id' => $this->patient->id, 'onboarding_complete' => true]);
        $this->doctor = User::factory()->create(['role' => UserRole::Doctor]);
        Appointment::create([
            'patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => '2026-11-10', 'start_time' => '09:00', 'end_time' => '09:30', 'fee_amount' => 70000,
        ]);
    }

    private function channel(string $key): NotificationChannel
    {
        $t = $this;

        return new class($key, $t) implements NotificationChannel {
            public function __construct(private string $k, private $t) {}

            public function key(): string { return $this->k; }

            public function isConfigured(): bool { return ! in_array($this->k, $this->t->unconfigured, true); }

            public function send(OutboundMessage $message): ChannelResult
            {
                $this->t->sent[] = ['channel' => $this->k, 'meta' => $message->meta];

                return in_array($this->k, $this->t->failing, true) ? ChannelResult::failed('spy', 'boom') : ChannelResult::sent('spy');
            }
        };
    }

    private function channels(): PatientChannels
    {
        return app(PatientChannels::class);
    }

    private function message(string $body = 'Hello'): OutboundMessage
    {
        return new OutboundMessage(templateKey: 'test.msg', category: 'transactional', user: $this->patient, subject: 'Hi', body: $body);
    }

    public function test_email_is_the_default_and_whatsapp_needs_consent_and_a_phone(): void
    {
        $this->assertSame('email', $this->channels()->preferred($this->patient));

        try {
            $this->channels()->save($this->patient, 'whatsapp', false);
            $this->fail('Needs consent');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('consent', $e->errors());
        }

        $noPhone = User::factory()->create(['role' => UserRole::Patient, 'phone' => null]);
        try {
            $this->channels()->save($noPhone, 'whatsapp', true);
            $this->fail('Needs a number');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('preferred', $e->errors());
        }

        $this->expectException(ValidationException::class);
        $this->channels()->save($this->patient, 'pigeon', false);
    }

    public function test_choosing_whatsapp_records_the_consent_with_its_wording(): void
    {
        $this->channels()->save($this->patient, 'whatsapp', true);

        $this->assertSame('whatsapp', $this->patient->fresh()->preferred_contact);
        $this->assertTrue($this->channels()->whatsappConsent($this->patient));
        $row = ConsentRecord::where('user_id', $this->patient->id)->where('consent_type', 'whatsapp_service')->firstOrFail();
        $this->assertTrue($row->granted);
        $this->assertSame(config('patient.notifications.whatsapp_wording'), $row->wording);
        $this->assertSame(1, (int) $row->version);
    }

    public function test_saving_the_same_choice_twice_does_not_pile_up_records_and_withdrawing_is_a_new_record(): void
    {
        $this->channels()->save($this->patient, 'whatsapp', true);
        $this->channels()->save($this->patient, 'whatsapp', true);
        $this->assertSame(1, ConsentRecord::where('consent_type', 'whatsapp_service')->count());

        $this->channels()->save($this->patient, 'email', false);

        $this->assertFalse($this->channels()->whatsappConsent($this->patient));
        $this->assertSame(2, ConsentRecord::where('consent_type', 'whatsapp_service')->count(), 'History is kept, nothing edited.');
        $this->assertSame('email', $this->patient->fresh()->preferred_contact);
    }

    public function test_the_chosen_channel_goes_first_and_the_other_is_the_fallback(): void
    {
        $this->channels()->save($this->patient, 'whatsapp', true);
        $this->assertSame(['whatsapp', 'email'], $this->channels()->order($this->patient->fresh()));

        $this->channels()->save($this->patient, 'email', true);
        $this->assertSame(['email', 'whatsapp'], $this->channels()->order($this->patient->fresh()), 'Consent stays, so WhatsApp is still a fallback.');

        $this->channels()->save($this->patient, 'email', false);
        $this->assertSame(['email'], $this->channels()->order($this->patient->fresh()), 'No consent: never WhatsApp.');
    }

    public function test_whatsapp_that_is_not_set_up_is_skipped_so_the_message_is_not_lost(): void
    {
        $this->channels()->save($this->patient, 'whatsapp', true);
        $this->unconfigured = ['whatsapp'];

        $this->assertSame(['email'], $this->channels()->order($this->patient->fresh()));
        $logs = app(PatientMessenger::class)->send($this->message());

        $this->assertCount(1, $logs);
        $this->assertSame('email', $logs[0]->channel);
        $this->assertSame(['email'], array_column($this->sent, 'channel'));
    }

    public function test_a_failed_first_channel_falls_back_to_the_other_and_every_attempt_is_logged(): void
    {
        $this->channels()->save($this->patient, 'whatsapp', true);
        $this->failing = ['whatsapp'];

        $logs = app(PatientMessenger::class)->send($this->message());

        $this->assertSame(['whatsapp', 'email'], array_column($logs, 'channel'));
        $this->assertSame(['failed', 'sent'], array_column($logs, 'status'));
        $this->assertSame(['whatsapp', 'email'], array_column($this->sent, 'channel'));

        $this->assertSame(1, $logs[0]->meta['attempt']);
        $this->assertFalse($logs[0]->meta['fallback']);
        $this->assertSame(2, $logs[1]->meta['attempt']);
        $this->assertTrue($logs[1]->meta['fallback']);
        $this->assertSame('whatsapp', $logs[1]->meta['chosen_channel']);
        $this->assertSame('test.msg', $logs[1]->template_key);
    }

    public function test_it_stops_at_the_first_channel_that_delivers(): void
    {
        $this->channels()->save($this->patient, 'whatsapp', true);

        $logs = app(PatientMessenger::class)->send($this->message());

        $this->assertCount(1, $logs);
        $this->assertSame(['whatsapp'], array_column($this->sent, 'channel'));
    }

    public function test_when_everything_fails_nothing_throws(): void
    {
        $this->channels()->save($this->patient, 'whatsapp', true);
        $this->failing = ['whatsapp', 'email'];

        $logs = app(PatientMessenger::class)->send($this->message());

        $this->assertSame(['failed', 'failed'], array_column($logs, 'status'));
    }

    public function test_the_doctor_services_now_message_on_the_chosen_channel(): void
    {
        config(['doctor.blood_tests.notify_enabled' => true, 'doctor.followups.notify_enabled' => true]);
        $this->channels()->save($this->patient, 'whatsapp', true);
        $this->failing = ['whatsapp'];

        app(BloodTestService::class)->request($this->doctor, $this->patient, ['fbc']);

        $this->assertSame(['whatsapp', 'email'], NotificationLog::orderBy('id')->pluck('channel')->all(), 'WhatsApp failed, email took over.');

        NotificationLog::query()->delete();
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);
        app(FollowUpService::class)->request($this->doctor, $order, 14);
        $this->assertSame(['whatsapp', 'email'], NotificationLog::orderBy('id')->pluck('channel')->all());
    }

    public function test_operations_emails_for_follow_ups_still_go_by_email_only(): void
    {
        config(['doctor.followups.notify_enabled' => true, 'doctor.followups.ops_emails' => ['ops@example.test']]);
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);

        app(FollowUpService::class)->request($this->doctor, $order, 14);

        $ops = NotificationLog::where('recipient', 'ops@example.test')->get();
        $this->assertCount(1, $ops);
        $this->assertSame('email', $ops->first()->channel);
    }

    public function test_the_inbox_shows_what_the_patient_was_asked_in_plain_words(): void
    {
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);
        app(FollowUpService::class)->request($this->doctor, $order, 14);
        app(BloodTestService::class)->request($this->doctor, $this->patient, ['hba1c'], 'diabetes check');
        app(PatientNoteService::class)->create($this->doctor, $this->patient, 'patient_summary', 'Your blood pressure is on track.');
        app(PatientNoteService::class)->create($this->doctor, $this->patient, 'clinical', 'CLINICAL SECRET');
        app(PatientNoteService::class)->create($this->doctor, $this->patient, 'operations', 'STAFF SECRET', [['role' => 'admin']]);

        $items = app(PatientInbox::class)->items($this->patient);
        $text = $items->pluck('text')->join(' | ');

        $this->assertSame(3, $items->count());
        $this->assertStringContainsString('follow-up', $text);
        $this->assertStringContainsString('tests', $text);
        $this->assertStringContainsString('summary', $text);
        foreach (['CLINICAL SECRET', 'STAFF SECRET', 'HbA1c', 'diabetes', 'blood pressure'] as $secret) {
            $this->assertStringNotContainsString($secret, $text);
        }
    }

    public function test_a_renewal_that_is_due_shows_once_and_not_while_a_follow_up_is_already_open(): void
    {
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);
        $auth = app(AuthorisationService::class)->grant($this->doctor, $order, '2026-06-01');   // ends 1 Dec: due for review

        $items = app(PatientInbox::class)->items($this->patient);
        $this->assertSame(['renewal-'.$auth->id], $items->pluck('key')->all());

        app(FollowUpService::class)->request($this->doctor, $order, 7);
        $keys = app(PatientInbox::class)->items($this->patient)->pluck('key')->all();
        $this->assertNotContains('renewal-'.$auth->id, $keys, 'The follow-up item covers it.');
    }

    public function test_the_inbox_only_ever_holds_the_patients_own_items(): void
    {
        $other = User::factory()->create(['role' => UserRole::Patient]);
        Appointment::create(['patient_id' => $other->id, 'doctor_id' => $this->doctor->id, 'type' => 'general', 'status' => 'completed', 'appointment_date' => '2026-11-10', 'start_time' => '09:00', 'end_time' => '09:30', 'fee_amount' => 1]);
        app(BloodTestService::class)->request($this->doctor, $other, ['fbc']);

        $this->assertSame(0, app(PatientInbox::class)->items($this->patient)->count());
    }

    public function test_the_screen_saves_the_choice_and_shows_the_inbox(): void
    {
        app(BloodTestService::class)->request($this->doctor, $this->patient, ['fbc']);

        Livewire::actingAs($this->patient)->test(Notifications::class)
            ->assertSet('preferred', 'email')->assertSeeHtml('data-item="blood-test-1"')
            ->set('preferred', 'whatsapp')->set('consent', false)->call('save')->assertHasErrors('consent')
            ->set('consent', true)->call('save')->assertHasNoErrors()->assertSeeHtml('data-saved');

        $this->assertSame('whatsapp', $this->patient->fresh()->preferred_contact);

        Livewire::actingAs($this->patient)->test(Notifications::class)->assertSet('preferred', 'whatsapp')->assertSet('consent', true);
    }

    public function test_the_screen_warns_when_there_is_no_phone_and_when_whatsapp_is_not_switched_on(): void
    {
        $noPhone = User::factory()->create(['role' => UserRole::Patient, 'phone' => null]);
        PatientProfile::create(['user_id' => $noPhone->id, 'onboarding_complete' => true]);
        Livewire::actingAs($noPhone)->test(Notifications::class)->assertSeeHtml('data-no-phone');

        $this->unconfigured = ['whatsapp'];
        Livewire::actingAs($this->patient)->test(Notifications::class)
            ->set('preferred', 'whatsapp')->set('consent', true)->call('save')
            ->assertSeeHtml('data-whatsapp-pending');
    }

    public function test_the_page_is_for_patients_only(): void
    {
        $this->actingAs($this->patient)->get(route('patient.notifications'))->assertOk();
        foreach ([UserRole::Doctor, UserRole::Admin, UserRole::HealthCoach] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('patient.notifications'))->assertForbidden();
        }
    }
}
