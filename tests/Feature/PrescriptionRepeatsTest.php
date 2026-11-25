<?php

namespace Tests\Feature;

use App\Enums\CycleStatus;
use App\Enums\UserRole;
use App\Livewire\Patient\MySubscription;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\IntegrationMessage;
use App\Models\Order;
use App\Models\PatientProfile;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\User;
use App\Services\PayFastService;
use App\Services\Subscriptions\PrescriptionRepeats;
use App\Services\Subscriptions\RepeatChargeService;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A signed prescription with repeats becomes a repeat order (Craig's guide 3.2): the first medication payment saves the card, the
 * first repeat is scheduled (pack - 5 days), each paid repeat makes a refill that goes to the pharmacy, the last one ends it.
 * Every PayFast and pharmacy call is faked.
 */
class PrescriptionRepeatsTest extends TestCase
{
    use RefreshDatabase;

    private User $patient;
    private Prescription $rx;
    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday 25 Nov 2026, 09:31 in South Africa.
        $this->travelTo(CarbonImmutable::parse('2026-11-25 09:31', 'Africa/Johannesburg'));
        config(['subscriptions.repeat_orders.tokenise_first_payment' => true, 'subscriptions.charging.enabled' => true]);

        $this->patient = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $this->patient->id, 'onboarding_complete' => true]);
        $this->rx = $this->prescription(3);
        $this->payment = $this->medicationPayment($this->rx);
    }

    private function prescription(int $repeats, bool $chronic = true): Prescription
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $appt = Appointment::create(['patient_id' => $this->patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed', 'appointment_date' => '2026-11-25', 'start_time' => '08:00', 'end_time' => '08:45', 'fee_amount' => 1]);
        $c = Consultation::create(['appointment_id' => $appt->id, 'patient_id' => $this->patient->id, 'doctor_id' => $doctor->id, 'status' => 'completed']);
        $rx = Prescription::create([
            'consultation_id' => $c->id, 'patient_id' => $this->patient->id, 'doctor_id' => $doctor->id, 'status' => 'signed', 'total_amount' => 45000,
            'payment_status' => 'unpaid', 'pharmacy_status' => 'pending', 'signed_at' => now(), 'is_chronic' => $chronic, 'repeats' => $repeats,
        ]);
        $rx->items()->create(['medication_name' => 'Test medicine', 'strength' => '1 mg', 'form' => 'injection', 'dosage' => '1', 'frequency' => 'weekly', 'route' => 'subcutaneous', 'duration_days' => 28, 'quantity' => 1, 'unit_price' => 45000, 'line_total' => 45000]);

        return $rx;
    }

    private function medicationPayment(Prescription $rx): Payment
    {
        return Payment::create(['patient_id' => $rx->patient_id, 'provider' => 'payfast', 'amount' => $rx->total_amount, 'currency' => 'ZAR', 'status' => 'pending', 'description' => "Medication - {$rx->reference}"]);
    }

    /** The PayFast payment notice for the first medication payment (signed like the real one). */
    private function firstPaymentNotice(?string $token = 'tok-abc'): void
    {
        $itn = ['m_payment_id' => $this->payment->reference, 'pf_payment_id' => '999', 'payment_status' => 'COMPLETE', 'amount_gross' => '450.00'];
        if ($token) {
            $itn['token'] = $token;
        }
        $itn['signature'] = app(PayFastService::class)->generateSignature($itn);
        $this->post(route('payment.notify'), $itn)->assertOk();
    }

    /** Runs the first repeat all the way to "PayFast says paid". */
    private function payTheNextRepeat(): SubscriptionCycle
    {
        $sub = Subscription::where('metadata->prescription_id', $this->rx->id)->firstOrFail();
        $cycle = $sub->cycles()->where('status', 'scheduled')->firstOrFail();
        $cycle->update(['scheduled_for' => now()->subMinutes(5)]);

        Http::fake([
            'api.payfast.co.za/*' => Http::response(['code' => 200, 'status' => 'success', 'data' => ['response' => true]], 200),
            'pharmacy.test/*' => Http::response('down', 500),
        ]);
        app(SubscriptionLifecycle::class)->runDueCycles();
        $this->assertSame('submitted', app(RepeatChargeService::class)->charge($cycle->fresh()));

        $this->mock(PayFastService::class, fn ($m) => $m->shouldReceive('validateItn')->andReturn(true));
        $this->post(route('subscription.notify'), ['m_payment_id' => $cycle->fresh()->metadata['charge_reference'], 'payment_status' => 'COMPLETE', 'amount_gross' => '450.00'])->assertOk();

        return $cycle->fresh();
    }

    // --- the medication checkout ---------------------------------------------------------------------------------

    public function test_a_medication_payment_with_no_appointment_can_be_paid_for(): void
    {
        config(['subscriptions.repeat_orders.tokenise_first_payment' => false]);

        $this->actingAs($this->patient)->get(route('payment.checkout', $this->payment->reference))
            ->assertOk()->assertSee("Medication - {$this->rx->reference}")->assertSee('R450.00')
            ->assertDontSee('name="subscription_type"', false);
    }

    public function test_the_first_payment_saves_the_card_only_for_a_prescription_with_repeats_and_when_switched_on(): void
    {
        $this->actingAs($this->patient)->get(route('payment.checkout', $this->payment->reference))
            ->assertOk()->assertSee('name="subscription_type" value="2"', false);

        $single = $this->medicationPayment($this->prescription(0, false));
        $this->actingAs($this->patient)->get(route('payment.checkout', $single->reference))->assertOk()->assertDontSee('name="subscription_type"', false);

        config(['subscriptions.repeat_orders.tokenise_first_payment' => false]);
        $this->actingAs($this->patient)->get(route('payment.checkout', $this->payment->reference))->assertOk()->assertDontSee('name="subscription_type"', false);
    }

    public function test_the_switch_is_off_by_default(): void
    {
        $this->assertMatchesRegularExpression('/SUBSCRIPTIONS_TOKENISE_FIRST_PAYMENT.{1,8}false/', file_get_contents(base_path('config/subscriptions.php')));
    }

    // --- starting the repeat order ----------------------------------------------------------------------------------

    public function test_the_first_payment_with_a_card_token_starts_the_repeat_order(): void
    {
        $this->firstPaymentNotice();

        $this->assertSame('paid', $this->rx->fresh()->payment_status);
        $sub = Subscription::where('metadata->prescription_id', $this->rx->id)->first();
        $this->assertNotNull($sub);
        $this->assertSame([$this->patient->id, 'active', 'adhoc', 'tok-abc', 28], [$sub->user_id, $sub->status, $sub->charge_mode, $sub->payfast_token, $sub->repeat_cycle_days]);
        $this->assertSame(PrescriptionRepeats::PLAN_SLUG, $sub->plan->slug);
        $this->assertFalse($sub->plan->is_active, 'The plan is never offered to patients.');

        // a 28 day pack repeats on day 23 at 09:30 South African time, for the medication price
        $cycle = $sub->cycles()->first();
        $this->assertSame('2026-12-18 09:30', $cycle->scheduled_for->setTimezone('Africa/Johannesburg')->format('Y-m-d H:i'));
        $this->assertSame(45000, $cycle->metadata['amount_cents']);

        $order = Order::where('source_type', 'subscription')->where('source_ref', (string) $sub->id)->first();
        $this->assertNotNull($order, 'The patient and Operations see and control the repeats on this order.');
        $this->assertSame($this->patient->id, $order->patient_id);
    }

    public function test_a_payment_notice_without_a_token_starts_nothing_but_the_medication_still_goes(): void
    {
        $this->firstPaymentNotice(null);

        $this->assertSame(0, Subscription::count());
        $this->assertSame('paid', $this->rx->fresh()->payment_status);
        $this->assertSame('dispatched', $this->rx->fresh()->pharmacy_status);
    }

    public function test_a_repeated_payment_notice_starts_only_one_repeat_order(): void
    {
        $this->firstPaymentNotice();
        $this->firstPaymentNotice();

        $this->assertSame(1, Subscription::where('metadata->prescription_id', $this->rx->id)->count());
        $this->assertSame(1, SubscriptionCycle::count());
    }

    public function test_only_a_signed_chronic_prescription_with_repeats_left_qualifies(): void
    {
        $svc = app(PrescriptionRepeats::class);

        $this->assertTrue($svc->wantsRepeats($this->rx));
        $this->assertFalse($svc->wantsRepeats($this->prescription(0)));
        $this->assertFalse($svc->wantsRepeats($this->prescription(2, false)));
        $this->assertFalse($svc->wantsRepeats(tap($this->prescription(2), fn ($r) => $r->update(['valid_until' => now()->subDay()]))));
        $this->assertNull($svc->start($this->rx, null), 'No card token, no repeat order.');
    }

    public function test_the_repeat_plan_is_not_shown_as_the_patients_membership(): void
    {
        $this->firstPaymentNotice();

        Livewire::actingAs($this->patient)->test(MySubscription::class)->assertSet('subscription', null)->assertSet('showPlans', true);
    }

    // --- a paid repeat ----------------------------------------------------------------------------------------------

    public function test_a_paid_repeat_makes_a_refill_that_goes_to_the_pharmacy(): void
    {
        $this->firstPaymentNotice();
        $cycle = $this->payTheNextRepeat();

        $this->assertSame(CycleStatus::Fulfilled, $cycle->status);
        $this->assertSame(1, $this->rx->fresh()->repeats_used);

        $refill = Prescription::where('refill_of_id', $this->rx->id)->first();
        $this->assertNotNull($refill);
        $this->assertSame([1, 'paid', 'dispatched', 0, false], [$refill->refill_no, $refill->payment_status, $refill->pharmacy_status, $refill->repeats, $refill->is_chronic]);
        $this->assertNotSame($this->rx->reference, $refill->reference);
        $this->assertSame(1, $refill->items()->count());
        $this->assertSame($this->rx->doctor_id, $refill->doctor_id, 'The refill carries the original doctor and signature date.');

        $payment = Payment::where('description', "Refill (1/3) - {$this->rx->reference}")->first();
        $this->assertNotNull($payment);
        $this->assertSame(['completed', 45000, true], [$payment->status, (int) $payment->amount, (bool) $payment->is_repeat_charge]);

        // the next repeat is scheduled, and the order is with the pharmacy
        $sub = Subscription::where('metadata->prescription_id', $this->rx->id)->first();
        $this->assertSame(1, $sub->cycles()->where('status', 'scheduled')->count());
        $this->assertSame('PharmacyProcessing', Order::where('source_type', 'subscription')->where('source_ref', (string) $sub->id)->value('status'));
    }

    public function test_the_same_paid_repeat_never_makes_two_refills(): void
    {
        $this->firstPaymentNotice();
        $cycle = $this->payTheNextRepeat();

        $again = app(PrescriptionRepeats::class)->onRepeatPaid($cycle->fresh(), 45000);
        $this->post(route('subscription.notify'), ['m_payment_id' => $cycle->metadata['charge_reference'], 'payment_status' => 'COMPLETE', 'amount_gross' => '450.00'])->assertOk();

        $this->assertSame(1, Prescription::where('refill_of_id', $this->rx->id)->count());
        $this->assertSame(1, $this->rx->fresh()->repeats_used);
        $this->assertSame(1, Payment::where('is_repeat_charge', true)->count());
        $this->assertSame(Prescription::where('refill_of_id', $this->rx->id)->value('id'), $again->id);
    }

    public function test_the_last_repeat_ends_the_order_and_nothing_more_is_scheduled(): void
    {
        $this->rx->update(['repeats' => 1]);
        $this->firstPaymentNotice();
        $this->payTheNextRepeat();

        $sub = Subscription::where('metadata->prescription_id', $this->rx->id)->first();
        $this->assertSame('completed', $sub->status);
        $this->assertSame(0, $sub->cycles()->where('status', 'scheduled')->count());
        $this->assertFalse($this->rx->fresh()->hasRefillsRemaining());
        $this->assertSame(1, Prescription::where('refill_of_id', $this->rx->id)->count());
    }

    public function test_a_refill_the_pharmacy_refuses_is_logged_and_retried_without_losing_the_payment(): void
    {
        $this->firstPaymentNotice();
        config(['services.pharmacy.enabled' => true, 'services.pharmacy.api_url' => 'https://pharmacy.test', 'services.pharmacy.api_key' => 'k']);
        $cycle = $this->payTheNextRepeat();
        $refill = Prescription::where('refill_of_id', $this->rx->id)->first();

        $this->assertNotNull($refill, 'A pharmacy problem never undoes a payment.');
        $this->assertSame(CycleStatus::Fulfilled, $cycle->status);
        $this->assertSame('failed', $refill->pharmacy_status);
        $row = IntegrationMessage::where('prescription_id', $refill->id)->first();
        $this->assertNotNull($row, 'The attempt is in the pharmacy message log, so Operations can see and resend it.');
        $this->assertSame(['failed', 500], [$row->status, $row->response_code]);
        $this->assertNotNull($row->next_retry_at, 'A definite refusal is retried automatically.');
    }

    public function test_a_subscription_that_is_not_a_prescription_repeat_is_left_alone(): void
    {
        $sub = Subscription::create(['user_id' => $this->patient->id, 'subscription_plan_id' => app(PrescriptionRepeats::class)->plan()->id, 'status' => 'active', 'charge_mode' => 'adhoc', 'payfast_token' => 't']);
        $cycle = app(SubscriptionLifecycle::class)->scheduleNextCycle($sub, now()->subMinute());

        $this->assertNull(app(PrescriptionRepeats::class)->onRepeatPaid($cycle, 100));
        $this->assertSame(0, Prescription::whereNotNull('refill_of_id')->count());
    }
}
