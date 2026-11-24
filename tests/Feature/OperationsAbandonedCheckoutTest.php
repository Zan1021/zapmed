<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CatalogCoupon;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Operations\AbandonedCheckouts;
use App\Services\Patient\PatientRewards;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 leftover: the "you did not finish your order" reminder (Craig's guide 6.1). One reminder per order, a day after,
 * only for recent carts, OFF by default, nothing health-related in the message.
 */
class OperationsAbandonedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-27 12:00', 'Africa/Johannesburg'));
        $this->patient = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $this->patient->id, 'onboarding_complete' => true]);
    }

    private function svc(): AbandonedCheckouts
    {
        return app(AbandonedCheckouts::class);
    }

    private function cart(string $orderedAgo = '30 hours', array $over = []): Order
    {
        return Order::create($over + [
            'patient_id' => $this->patient->id, 'status' => 'PendingPayment', 'total_minor' => 1, 'service_category' => 'Hair Loss',
            'ordered_at' => now()->sub(\Carbon\CarbonInterval::make($orderedAgo)),
        ]);
    }

    private function on(): void
    {
        config(['operations.abandoned_checkout.notify_enabled' => true]);
    }

    public function test_it_is_off_by_default_and_then_does_nothing_at_all(): void
    {
        $o = $this->cart();
        config(['operations.abandoned_checkout.coupon.enabled' => true]);

        $this->assertSame(0, $this->svc()->run());
        $this->assertNull($o->fresh()->abandoned_followup_at, 'Nothing is flagged, so switching it on later still works.');
        $this->assertSame(0, NotificationLog::count());
        $this->assertSame(0, CatalogCoupon::count());
    }

    public function test_a_cart_a_day_old_gets_one_reminder_with_a_link_and_no_health_detail(): void
    {
        $this->on();
        $o = $this->cart('30 hours');

        $this->assertSame(1, $this->svc()->run());

        $log = NotificationLog::firstOrFail();
        $this->assertSame('ops.abandoned_checkout', $log->template_key);
        $this->assertSame($this->patient->id, $log->user_id);
        $this->assertSame($o->id, $log->meta['order_id']);
        $this->assertNotNull($o->fresh()->abandoned_followup_at);
        $this->assertStringNotContainsString('Hair', json_encode($log->toArray()));
    }

    public function test_only_once_per_order(): void
    {
        $this->on();
        $this->cart();

        $this->assertSame(1, $this->svc()->run());
        $this->assertSame(0, $this->svc()->run());
        $this->assertSame(1, NotificationLog::count());
    }

    public function test_the_timing_window(): void
    {
        $this->on();
        $tooFresh = $this->cart('5 hours');
        $justRight = $this->cart('25 hours');
        $tooOld = $this->cart('5 days');

        $ids = $this->svc()->candidates()->pluck('id')->all();

        $this->assertSame([$justRight->id], $ids);
        $this->assertNotContains($tooFresh->id, $ids);
        $this->assertNotContains($tooOld->id, $ids, 'Old carts are not chased after the switch is turned on.');
    }

    public function test_orders_that_are_paid_started_paying_archived_or_not_waiting_are_left_alone(): void
    {
        $this->on();
        $paid = $this->cart();
        Payment::create(['patient_id' => $this->patient->id, 'order_id' => $paid->id, 'amount' => 1, 'status' => 'completed']);
        $paying = $this->cart();
        Payment::create(['patient_id' => $this->patient->id, 'order_id' => $paying->id, 'amount' => 1, 'status' => 'pending']);   // started just now
        $archived = $this->cart('30 hours', ['archived_at' => now()]);
        $cancelled = $this->cart('30 hours', ['status' => 'Cancelled']);
        $delivered = $this->cart('30 hours', ['status' => 'Delivered']);
        $abandonedOnPurpose = $this->cart();   // the only one that should be chased

        $this->assertSame([$abandonedOnPurpose->id], $this->svc()->candidates()->pluck('id')->all());
    }

    public function test_an_old_unfinished_payment_does_not_stop_the_reminder(): void
    {
        $this->on();
        $o = $this->cart();
        $p = Payment::create(['patient_id' => $this->patient->id, 'order_id' => $o->id, 'amount' => 1, 'status' => 'pending']);
        $p->forceFill(['created_at' => now()->subHours(5)])->save();

        $this->assertSame([$o->id], $this->svc()->candidates()->pluck('id')->all());
    }

    public function test_the_reminder_follows_the_patients_chosen_channel_with_email_as_the_fallback(): void
    {
        $this->on();
        $this->cart();
        $this->svc()->run();

        $this->assertSame(['email'], NotificationLog::pluck('channel')->all(), 'No WhatsApp consent: email.');
    }

    public function test_the_thank_you_coupon_is_a_separate_switch_made_for_that_patient_only(): void
    {
        $this->on();
        config(['operations.abandoned_checkout.coupon.enabled' => true]);
        $this->cart();
        $other = User::factory()->create(['role' => UserRole::Patient]);

        $this->svc()->run();

        $coupon = CatalogCoupon::firstOrFail();
        $this->assertStringStartsWith('BACK-', $coupon->code);
        $this->assertSame(1000, $coupon->value, '10%');
        $this->assertSame(1, $coupon->max_uses);
        $this->assertSame('2026-12-04', $coupon->expiry_dt->setTimezone('Africa/Johannesburg')->format('Y-m-d'));
        $this->assertSame([$coupon->code], app(PatientRewards::class)->available($this->patient)->pluck('code')->all());
        $this->assertSame([], app(PatientRewards::class)->available($other)->pluck('code')->all());
        $this->assertStringContainsString($coupon->code, NotificationLog::firstOrFail()->meta['coupon']);
    }

    public function test_no_coupon_unless_it_is_switched_on(): void
    {
        $this->on();
        $this->cart();

        $this->svc()->run();

        $this->assertSame(0, CatalogCoupon::count());
        $this->assertNull(NotificationLog::firstOrFail()->meta['coupon']);
    }

    public function test_the_command_runs_and_is_scheduled(): void
    {
        $this->on();
        $this->cart();

        $this->artisan('checkouts:follow-up')->expectsOutput('Reminded 1 patient(s).')->assertSuccessful();

        $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command->implode(' ');
        $this->assertStringContainsString('checkouts:follow-up', $scheduled);
    }

    public function test_the_rewards_page_does_not_promise_a_checkout_box(): void
    {
        $this->actingAs($this->patient)->get(route('patient.rewards'))->assertOk()->assertDontSee('at checkout')->assertSee('apply it to your order');
    }
}
