<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\MessageTemplateEditor;
use App\Models\Appointment;
use App\Models\Medication;
use App\Models\MessageTemplate;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\User;
use App\Services\Doctor\BloodTestService;
use App\Services\Doctor\FollowUpService;
use App\Services\Operations\MessageTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4 leftover: message wording Operations can change or switch off (Craig's guide 6.4, 8.3). No health detail, no medicine
 * names, only the placeholders a message allows, every change kept as a version, and the version is in the message log.
 */
class OperationsMessageTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;
    private User $doctor;
    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
        $this->doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $this->patient = User::factory()->create(['role' => UserRole::Patient]);
        Appointment::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'type' => 'general', 'status' => 'completed', 'appointment_date' => '2026-11-10', 'start_time' => '09:00', 'end_time' => '09:30', 'fee_amount' => 1]);
    }

    private function svc(): MessageTemplates
    {
        return app(MessageTemplates::class);
    }

    private function field(callable $try, string $field): void
    {
        try {
            $try();
            $this->fail("Should be refused: $field");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_every_registered_message_has_wording_and_clean_defaults(): void
    {
        foreach ($this->svc()->registry() as $key => $def) {
            $t = $this->svc()->render($key, ['addon' => 'Coaching', 'link' => 'https://x.test', 'coupon_text' => '']);
            $this->assertNotEmpty($t['subject'], $key);
            $this->assertNotEmpty($t['body'], $key);
            $this->assertSame(0, $t['version'], "$key: built-in wording is version 0");
            $this->assertStringNotContainsString('{', $t['body'], "$key: every placeholder is filled");
            $this->svc()->assertNoHealthDetail($t['subject'].' '.$t['body']);
        }
    }

    public function test_an_unknown_message_is_a_programming_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc()->render('nonsense');
    }

    public function test_placeholders_are_filled_in_and_extra_spaces_tidied(): void
    {
        $this->svc()->save($this->ops, 'ops.addon_added', 'Hello', 'We added   {addon} for you.   Thanks', true);

        $t = $this->svc()->render('ops.addon_added', ['addon' => 'Coach support']);

        $this->assertSame('We added Coach support for you. Thanks', $t['body']);
        $this->assertSame('Hello', $t['subject']);
        $this->assertSame(1, $t['version']);
    }

    public function test_only_the_listed_placeholders_may_be_used_and_required_ones_must_stay(): void
    {
        $this->field(fn () => $this->svc()->save($this->ops, 'ops.addon_added', 'S', 'Hi {name}, we added {addon}.', true), 'body');
        $this->field(fn () => $this->svc()->save($this->ops, 'doctor.followup_requested', 'S', 'Hi {addon}', true), 'body');
        $this->field(fn () => $this->svc()->save($this->ops, 'ops.abandoned_checkout', 'S', 'Come back and finish.', true), 'body');
        $this->field(fn () => $this->svc()->save($this->ops, 'ops.addon_added', 'Hi {secret}', 'ok', true), 'body');

        $this->svc()->save($this->ops, 'ops.abandoned_checkout', 'S', 'Come back: {link}', true);
        $this->assertSame(1, MessageTemplate::count());
    }

    public function test_health_detail_banned_words_and_medicine_names_are_refused(): void
    {
        Medication::create(['name' => 'Ozempic', 'form' => 'pen', 'strength' => '1mg', 'is_active' => true]);
        Medication::create(['name' => 'Old thing', 'form' => 'pen', 'strength' => '1mg', 'is_active' => false]);

        foreach (['Your diabetes check is due', 'Your blood pressure was high', 'Time for your dose', 'Your Ozempic is ready', 'ozempic repeat'] as $text) {
            $this->field(fn () => $this->svc()->save($this->ops, 'doctor.followup_prompt', 'Reminder', $text, true), 'body');
        }
        $this->field(fn () => $this->svc()->save($this->ops, 'doctor.followup_prompt', 'Your diabetes check', 'ok', true), 'body');

        $this->svc()->save($this->ops, 'doctor.followup_prompt', 'Reminder', 'Your appointment is coming up. Please book it.', true);
        $this->svc()->save($this->ops, 'doctor.followup_requested', 'Reminder', 'Mentioning the old thing is fine because it is switched off.', true);
        $this->assertSame(2, MessageTemplate::count());
    }

    public function test_a_medicine_name_inside_a_longer_word_is_not_a_false_alarm(): void
    {
        Medication::create(['name' => 'Rope', 'form' => 'x', 'strength' => '1', 'is_active' => true]);

        $this->svc()->save($this->ops, 'doctor.followup_prompt', 'Reminder', 'Please sign in to Zapmed to continue.', true);   // "Rope" is not in "properly"

        $this->svc()->assertNoHealthDetail('We handle everything properly');
        $this->field(fn () => $this->svc()->assertNoHealthDetail('A rope is mentioned'), 'body');
    }

    public function test_length_limits(): void
    {
        $this->field(fn () => $this->svc()->save($this->ops, 'doctor.followup_prompt', '', 'ok', true), 'subject');
        $this->field(fn () => $this->svc()->save($this->ops, 'doctor.followup_prompt', str_repeat('a', 121), 'ok', true), 'subject');
        $this->field(fn () => $this->svc()->save($this->ops, 'doctor.followup_prompt', 'S', '  ', true), 'body');
        $this->field(fn () => $this->svc()->save($this->ops, 'doctor.followup_prompt', 'S', str_repeat('a', 1001), true), 'body');
        $this->field(fn () => $this->svc()->save($this->ops, 'no.such.key', 'S', 'ok', true), 'key');
    }

    public function test_versions_count_up_and_survive_a_reset(): void
    {
        $this->svc()->save($this->ops, 'ops.addon_added', 'S1', 'First {addon}', true);
        $this->svc()->save($this->ops, 'ops.addon_added', 'S2', 'Second {addon}', true);
        $this->assertSame(2, $this->svc()->render('ops.addon_added', ['addon' => 'X'])['version']);

        $this->svc()->reset($this->ops, 'ops.addon_added');
        $this->assertSame(0, $this->svc()->render('ops.addon_added', ['addon' => 'X'])['version'], 'Built-in again.');
        $this->assertFalse($this->svc()->isCustom('ops.addon_added'));

        $again = $this->svc()->save($this->ops, 'ops.addon_added', 'S3', 'Third {addon}', true);
        $this->assertSame(4, $again->version, 'Versions 1, 2 and the reset (3) are used: never reused.');
        $this->assertSame([1, 2, 3, 4], \DB::table('message_template_versions')->where('template_key', 'ops.addon_added')->orderBy('version')->pluck('version')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('reset', \DB::table('message_template_versions')->where('version', 3)->value('change'));
    }

    public function test_resetting_a_message_that_was_never_changed_is_refused(): void
    {
        $this->field(fn () => $this->svc()->reset($this->ops, 'ops.addon_added'), 'key');
    }

    public function test_switching_a_message_off_stops_it_being_sent(): void
    {
        config(['doctor.followups.notify_enabled' => true]);
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);
        $this->svc()->save($this->ops, 'doctor.followup_requested', 'A follow-up appointment is needed', 'Your doctor asked for a follow-up.', false);

        $this->assertNull($this->svc()->render('doctor.followup_requested'));
        app(FollowUpService::class)->request($this->doctor, $order, 14);

        $this->assertSame(0, NotificationLog::count());
    }

    public function test_the_senders_use_the_new_wording_and_log_the_version(): void
    {
        config(['doctor.followups.notify_enabled' => true, 'doctor.blood_tests.notify_enabled' => true]);
        $this->svc()->save($this->ops, 'doctor.bloodtest_requested', 'Tests needed', 'Please see your Zapmed account for what to do next.', true);
        $this->svc()->save($this->ops, 'doctor.bloodtest_requested', 'Tests needed', 'Please open your Zapmed account to see what to do next.', true);

        app(BloodTestService::class)->request($this->doctor, $this->patient, ['fbc']);

        $log = NotificationLog::firstOrFail();
        $this->assertSame('doctor.bloodtest_requested', $log->template_key);
        $this->assertSame(2, $log->meta['template_version']);
    }

    public function test_built_in_wording_is_logged_as_version_zero(): void
    {
        config(['doctor.blood_tests.notify_enabled' => true]);

        app(BloodTestService::class)->request($this->doctor, $this->patient, ['fbc']);

        $this->assertSame(0, NotificationLog::firstOrFail()->meta['template_version']);
    }

    public function test_only_operations_can_change_wording(): void
    {
        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach] as $role) {
            $u = User::factory()->create(['role' => $role]);
            foreach ([fn () => $this->svc()->save($u, 'ops.addon_added', 'S', 'B {addon}', true), fn () => $this->svc()->reset($u, 'ops.addon_added')] as $try) {
                try {
                    $try();
                    $this->fail('Expected 403');
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    $this->assertSame(403, $e->getStatusCode());
                }
            }
            $this->actingAs($u)->get(route('admin.message-templates'))->assertForbidden();
        }
        $this->assertSame(0, MessageTemplate::count());
    }

    public function test_the_editor_screen(): void
    {
        $this->actingAs($this->ops)->get(route('admin.message-templates'))->assertOk()->assertSee('Patient messages')->assertSee('Unfinished order reminder');

        Livewire::actingAs($this->ops)->test(MessageTemplateEditor::class)
            ->call('edit', 'ops.addon_added')->assertSet('subject', 'An add-on was added to your order')->assertSeeHtml('data-editing="ops.addon_added"')
            ->set('body', 'Your diabetes plan changed {addon}')->call('save')->assertHasErrors('body')
            ->set('body', 'We added {addon}. Open My Orders.')->call('save')->assertHasNoErrors()->assertSeeHtml('data-saved')->assertSee('Changed by Operations')
            ->set('enabled', false)->call('save')->assertSee('Switched off')
            ->call('reset_')->assertHasNoErrors()->assertSet('body', config('operations.templates.registry')['ops.addon_added']['body'])->assertSet('enabled', true)
            ->assertSeeHtml('data-history')->assertSee('went back to the built-in wording');

        Livewire::actingAs($this->ops)->test(MessageTemplateEditor::class)->call('edit', 'nope')->assertNotFound();
    }
}
