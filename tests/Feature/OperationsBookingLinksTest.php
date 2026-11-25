<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\OrderControlsPanel;
use App\Livewire\Patient\FollowUp;
use App\Models\BookingLink;
use App\Models\CrmAuditEvent;
use App\Models\FollowUpRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\Operations\BookingLinks;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Phase 4 leftover: limited-use, limited-time booking links for one patient (Craig's guide 6.2, 8.2). */
class OperationsBookingLinksTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;
    private User $patient;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
        $this->patient = User::factory()->create(['role' => UserRole::Patient]);
        $this->other = User::factory()->create(['role' => UserRole::Patient]);
        foreach ([$this->patient, $this->other] as $p) {
            \App\Models\PatientProfile::create(['user_id' => $p->id, 'onboarding_complete' => true]);
        }
    }

    private function svc(): BookingLinks
    {
        return app(BookingLinks::class);
    }

    private function token(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH));
    }

    private function order(): Order
    {
        return Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);
    }

    private function invalid(callable $fn, string $field): void
    {
        try {
            $fn();
            $this->fail('Expected a validation error on '.$field);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_a_link_stores_only_its_hash_and_shows_the_url_once(): void
    {
        $made = $this->svc()->make($this->ops, $this->patient);
        $token = $this->token($made['url']);

        $this->assertSame(hash('sha256', $token), $made['link']->token_hash);
        $this->assertStringNotContainsString($token, json_encode(BookingLink::first()->getAttributes()));
        $this->assertSame(3, $made['link']->max_uses);
        $this->assertSame(0, $made['link']->uses);
        $this->assertTrue($made['link']->expires_at->isSameDay(now()->addDays(14)));
    }

    public function test_limits_are_checked(): void
    {
        $this->invalid(fn () => $this->svc()->make($this->ops, $this->patient, 0), 'uses');
        $this->invalid(fn () => $this->svc()->make($this->ops, $this->patient, 21), 'uses');
        $this->invalid(fn () => $this->svc()->make($this->ops, $this->patient, 3, 0), 'days');
        $this->invalid(fn () => $this->svc()->make($this->ops, $this->patient, 3, 61), 'days');
        $this->assertSame(0, BookingLink::count());

        $ok = $this->svc()->make($this->ops, $this->patient, 20, 60);
        $this->assertSame(20, $ok['link']->max_uses);
    }

    public function test_a_link_is_only_for_a_patient_and_only_made_by_operations(): void
    {
        $this->invalid(fn () => $this->svc()->make($this->ops, User::factory()->create(['role' => UserRole::Doctor])), 'patient');

        try {
            $this->svc()->make($this->patient, $this->patient);
            $this->fail('Expected 403');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_a_follow_up_of_another_patient_is_refused(): void
    {
        $theirs = Order::create(['patient_id' => $this->other->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);
        $fu = FollowUpRequest::create(['order_id' => $theirs->id, 'patient_id' => $this->other->id, 'requested_by' => $this->ops->id, 'days' => 7, 'due_at' => now()->utc()->addDays(7), 'status' => 'requested']);

        $this->invalid(fn () => $this->svc()->make($this->ops, $this->patient, null, null, $fu), 'follow_up');
    }

    public function test_opening_counts_a_use_and_goes_to_the_follow_up_page(): void
    {
        $made = $this->svc()->make($this->ops, $this->patient);

        $this->actingAs($this->patient)->get($made['url'])->assertRedirect(route('patient.follow-up'));
        $this->assertSame(1, $made['link']->fresh()->uses);
        $this->assertNotNull($made['link']->fresh()->last_used_at);
    }

    public function test_somebody_elses_link_uses_nothing_up(): void
    {
        $made = $this->svc()->make($this->ops, $this->patient);

        $this->actingAs($this->other)->get($made['url'])->assertStatus(410)->assertSee('somebody else');
        $this->assertSame(0, $made['link']->fresh()->uses);
    }

    public function test_an_unknown_link_is_refused(): void
    {
        $this->actingAs($this->patient)->get(route('booking-link.open', str_repeat('a', 43)))->assertStatus(410)->assertSee('not valid');
    }

    public function test_used_up_expired_and_switched_off_links_open_nothing(): void
    {
        $used = $this->svc()->make($this->ops, $this->patient, 1);
        $this->actingAs($this->patient)->get($used['url'])->assertRedirect();
        $this->actingAs($this->patient)->get($used['url'])->assertStatus(410)->assertSee('too many times');

        $old = $this->svc()->make($this->ops, $this->patient, 3, 1);
        $this->travel(2)->days();
        $this->actingAs($this->patient)->get($old['url'])->assertStatus(410)->assertSee('expired');
        $this->assertSame(0, $old['link']->fresh()->uses);
        $this->travelBack();

        $off = $this->svc()->make($this->ops, $this->patient);
        $this->svc()->revoke($this->ops, $off['link']);
        $this->actingAs($this->patient)->get($off['url'])->assertStatus(410)->assertSee('switched off');
        $this->assertSame(0, $off['link']->fresh()->uses);
    }

    public function test_a_guest_is_sent_to_sign_in_first(): void
    {
        $made = $this->svc()->make($this->ops, $this->patient);

        $this->get($made['url'])->assertRedirect();
        $this->assertSame(0, $made['link']->fresh()->uses);
    }

    public function test_resend_switches_the_old_link_off_and_keeps_the_limits(): void
    {
        $first = $this->svc()->make($this->ops, $this->patient, 5, 10);
        $second = $this->svc()->resend($this->ops, $first['link']);

        $this->assertNotNull($first['link']->fresh()->revoked_at);
        $this->assertNull($second['link']->revoked_at);
        $this->assertSame(5, $second['link']->max_uses);
        $this->assertNotSame($first['url'], $second['url']);

        $this->actingAs($this->patient)->get($first['url'])->assertStatus(410);
        $this->actingAs($this->patient)->get($second['url'])->assertRedirect(route('patient.follow-up'));
    }

    public function test_only_operations_can_resend_or_revoke(): void
    {
        $made = $this->svc()->make($this->ops, $this->patient);

        foreach ([fn () => $this->svc()->resend($this->patient, $made['link']), fn () => $this->svc()->revoke($this->patient, $made['link'])] as $try) {
            try {
                $try();
                $this->fail('Expected 403');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertNull($made['link']->fresh()->revoked_at);
    }

    public function test_the_follow_up_page_starts_on_the_linked_request(): void
    {
        $order = $this->order();
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $fu = FollowUpRequest::create(['order_id' => $order->id, 'patient_id' => $this->patient->id, 'requested_by' => $doctor->id, 'days' => 7, 'due_at' => now()->utc()->addDays(7), 'status' => 'requested']);
        $made = $this->svc()->make($this->ops, $this->patient, null, null, $fu);

        $this->actingAs($this->patient)->get($made['url'])->assertRedirect();
        $this->assertSame($fu->id, session('booking_link.follow_up_request_id'));

        Livewire::test(FollowUp::class)->assertSet('requestId', $fu->id)->assertSet('doctorId', $doctor->id);
        $this->assertNull(session('booking_link.follow_up_request_id'));
    }

    public function test_the_panel_makes_resends_and_switches_off_links(): void
    {
        $order = $this->order();
        $this->actingAs($this->ops);

        $panel = Livewire::test(OrderControlsPanel::class, ['orderId' => $order->id])
            ->assertSeeHtml('data-section="booking-links"')
            ->set('linkUses', '99')->call('makeLink')->assertHasErrors('uses')
            ->set('linkUses', '2')->set('linkDays', '5')->call('makeLink')->assertHasNoErrors()
            ->assertSeeHtml('data-new-link');

        $link = BookingLink::firstOrFail();
        $this->assertSame(2, $link->max_uses);
        $this->assertSame($this->patient->id, $link->patient_id);

        $panel->call('resendLink', $link->id)->assertHasNoErrors();
        $this->assertNotNull($link->fresh()->revoked_at);
        $newest = BookingLink::orderByDesc('id')->first();
        $this->assertNull($newest->revoked_at);

        $panel->call('revokeLink', $newest->id);
        $this->assertNotNull($newest->fresh()->revoked_at);
    }

    public function test_the_panel_refuses_a_link_of_another_patient(): void
    {
        $order = $this->order();
        $theirs = $this->svc()->make($this->ops, $this->other)['link'];
        $this->actingAs($this->ops);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(OrderControlsPanel::class, ['orderId' => $order->id])->call('revokeLink', $theirs->id);
    }

    public function test_making_and_switching_off_are_written_to_the_audit_trail(): void
    {
        $made = $this->svc()->make($this->ops, $this->patient);
        $this->svc()->revoke($this->ops, $made['link']);

        $this->assertSame(['created', 'revoked'], CrmAuditEvent::where('domain', 'booking_link')->orderBy('id')->pluck('action')->all());
    }

    // --- sending the link ---------------------------------------------------------------------------------------------

    /** Captures what would be sent instead of sending it. */
    private function captureMessages(): \ArrayObject
    {
        $sent = new \ArrayObject();
        $this->mock(\App\Services\Patient\PatientMessenger::class, fn ($m) => $m->shouldReceive('send')->andReturnUsing(function ($message) use ($sent) {
            $sent->append($message);

            return [];
        }));

        return $sent;
    }

    public function test_the_link_is_sent_to_the_patient_only_when_the_switch_is_on(): void
    {
        $sent = $this->captureMessages();
        $made = $this->svc()->make($this->ops, $this->patient);

        config()->set('operations.booking_links.notify_enabled', false);
        $this->assertFalse($this->svc()->send($this->ops, $made['link'], $made['url']));
        $this->assertCount(0, $sent);

        config()->set('operations.booking_links.notify_enabled', true);
        $this->assertTrue($this->svc()->send($this->ops, $made['link'], $made['url']));
        $this->assertCount(1, $sent);
        $m = $sent[0];
        $this->assertSame([$this->patient->id, 'ops.booking_link'], [$m->user->id, $m->templateKey]);
        $this->assertStringContainsString($made['url'], $m->body);
        $this->assertStringContainsString('9 Dec 2026', $m->body, 'The last day is in the message (14 days from 25 Nov).');
        $this->assertSame(['created', 'sent'], CrmAuditEvent::where('domain', 'booking_link')->orderBy('id')->pluck('action')->all());
    }

    public function test_a_link_that_is_not_the_one_that_was_made_or_is_no_longer_usable_is_never_sent(): void
    {
        $sent = $this->captureMessages();
        config()->set('operations.booking_links.notify_enabled', true);
        $a = $this->svc()->make($this->ops, $this->patient);
        $b = $this->svc()->make($this->ops, $this->patient);

        $this->assertFalse($this->svc()->send($this->ops, $a['link'], $b['url']), 'A different link address.');
        $this->svc()->revoke($this->ops, $a['link']);
        $this->assertFalse($this->svc()->send($this->ops, $a['link']->fresh(), $a['url']), 'A switched-off link.');
        $this->assertCount(0, $sent);
    }

    public function test_only_operations_can_send_a_link(): void
    {
        $made = $this->svc()->make($this->ops, $this->patient);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc()->send($this->patient, $made['link'], $made['url']);
    }

    public function test_the_panel_sends_the_link_it_just_made_and_says_what_happened(): void
    {
        $sent = $this->captureMessages();
        $order = $this->order();
        $this->actingAs($this->ops);

        $panel = Livewire::test(OrderControlsPanel::class, ['orderId' => $order->id])
            ->call('sendLink')->assertSee('Make a link first')
            ->call('makeLink')->assertSeeHtml('data-action="send-link"');

        config()->set('operations.booking_links.notify_enabled', false);
        $panel->call('sendLink')->assertSee('switched off, so nothing was sent');
        $this->assertCount(0, $sent);

        config()->set('operations.booking_links.notify_enabled', true);
        $panel->call('sendLink')->assertSee('Sent to the patient.');
        $this->assertCount(1, $sent);
    }

    public function test_the_default_words_carry_no_health_detail_and_it_is_off_by_default(): void
    {
        $this->assertMatchesRegularExpression('/OPS_BOOKING_LINK_NOTIFY.{1,8}false/', file_get_contents(base_path('config/operations.php')));
        $t = app(\App\Services\Operations\MessageTemplates::class)->render('ops.booking_link', ['link' => 'https://x.test/my/book/abc', 'until' => '9 Dec 2026']);

        $this->assertNotNull($t);
        $this->assertStringNotContainsString('weight', strtolower($t['body']));
    }
}
