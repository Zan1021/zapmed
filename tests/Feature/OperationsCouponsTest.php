<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\Coupons as CouponsPage;
use App\Models\Appointment;
use App\Models\CatalogCoupon;
use App\Models\CatalogCouponUsage;
use App\Models\CrmAuditEvent;
use App\Models\PatientProfile;
use App\Models\Payment;
use App\Models\User;
use App\Services\Operations\CouponRedemption;
use App\Services\Operations\Coupons;
use App\Services\PayFastService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Coupon codes typed at checkout, and the Operations page that makes them. */
class OperationsCouponsTest extends TestCase
{
    use RefreshDatabase;

    private User $patient;
    private User $other;
    private User $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
        config()->set('payfast.test_mode', true);
        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
        $this->patient = $this->patient();
        $this->other = $this->patient();
    }

    private function patient(): User
    {
        $u = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $u->id, 'onboarding_complete' => true]);

        return $u;
    }

    private function svc(): CouponRedemption
    {
        return app(CouponRedemption::class);
    }

    private function coupon(array $a = []): CatalogCoupon
    {
        return CatalogCoupon::create($a + [
            'code' => 'SAVE10', 'type' => 'percent', 'value' => 1000, 'max_uses' => null, 'number_of_uses' => 0, 'is_cancelled' => false,
            'metadata' => ['audience' => 'code'], 'upstream_source' => 'zapmed',
        ]);
    }

    /** A pending consultation payment of R700 (the weight-loss fee). */
    private function consultPayment(?User $for = null, int $cents = 70000): Payment
    {
        $for ??= $this->patient;
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $appt = Appointment::create(['patient_id' => $for->id, 'doctor_id' => $doctor->id, 'type' => 'weight-loss', 'status' => 'pending_payment', 'appointment_date' => '2026-11-27', 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => $cents]);

        return Payment::create(['patient_id' => $for->id, 'appointment_id' => $appt->id, 'amount' => $cents, 'status' => 'pending']);
    }

    private function refused(callable $fn, string $needle): void
    {
        try {
            $fn();
            $this->fail('Expected the coupon to be refused: '.$needle);
        } catch (ValidationException $e) {
            $this->assertStringContainsString($needle, $e->errors()['coupon'][0]);
        }
    }

    // --- the maths ----------------------------------------------------------------------------------------------------

    public function test_percent_and_fixed_discounts_and_the_r5_floor(): void
    {
        $this->assertSame(7000, $this->svc()->discountFor($this->coupon(), 70000));                                            // 10%
        $this->assertSame(10000, $this->svc()->discountFor($this->coupon(['code' => 'F100', 'type' => 'fixed', 'value' => 10000]), 70000)); // R100
        $this->assertSame(69500, $this->svc()->discountFor($this->coupon(['code' => 'ALL', 'value' => 10000]), 70000), '100% still leaves R5 for PayFast');
        $this->assertSame(69500, $this->svc()->discountFor($this->coupon(['code' => 'BIG', 'type' => 'fixed', 'value' => 900000]), 70000));
        $this->assertSame(0, $this->svc()->discountFor($this->coupon(['code' => 'FREE', 'type' => 'free_shipping', 'value' => 100]), 70000));
        $this->assertSame(0, $this->svc()->discountFor($this->coupon(['code' => 'SMALL']), 500), 'Nothing comes off a payment that is already the minimum.');
    }

    // --- who may use a code ------------------------------------------------------------------------------------------

    public function test_a_good_code_works_whatever_its_case_or_spaces(): void
    {
        $this->coupon();

        $r = $this->svc()->check(' save 10 ', $this->patient, 70000);

        $this->assertSame([7000, 63000], [$r['discount_cents'], $r['final_cents']]);
    }

    public function test_codes_that_cannot_be_used_are_refused_with_a_plain_reason(): void
    {
        $this->coupon(['code' => 'GONE', 'is_cancelled' => true]);
        $this->coupon(['code' => 'OLD', 'expiry_dt' => now()->subDay()]);
        $this->coupon(['code' => 'FULL', 'max_uses' => 2, 'number_of_uses' => 2]);

        $this->refused(fn () => $this->svc()->check('', $this->patient, 70000), 'Enter your coupon code');
        $this->refused(fn () => $this->svc()->check('NOPE', $this->patient, 70000), 'do not recognise');
        $this->refused(fn () => $this->svc()->check('GONE', $this->patient, 70000), 'no longer active');
        $this->refused(fn () => $this->svc()->check('OLD', $this->patient, 70000), 'expired');
        $this->refused(fn () => $this->svc()->check('FULL', $this->patient, 70000), 'most times');
    }

    public function test_a_private_coupon_is_only_for_its_patient_and_an_imported_one_cannot_be_typed(): void
    {
        $this->coupon(['code' => 'MINE', 'metadata' => ['patient_id' => (string) $this->patient->id]]);
        $this->coupon(['code' => 'CONTRO1', 'metadata' => null, 'upstream_source' => 'contro']);

        $this->assertSame(7000, $this->svc()->check('MINE', $this->patient, 70000)['discount_cents']);
        $this->refused(fn () => $this->svc()->check('MINE', $this->other, 70000), 'do not recognise');
        $this->refused(fn () => $this->svc()->check('CONTRO1', $this->patient, 70000), 'do not recognise');
    }

    public function test_guessing_codes_is_slowed_down(): void
    {
        RateLimiter::clear('coupon-attempts:'.$this->patient->id);
        config()->set('operations.coupons.attempts_per_minute', 3);

        foreach (range(1, 3) as $i) {
            $this->refused(fn () => $this->svc()->check('WRONG'.$i, $this->patient, 70000), 'do not recognise');
        }
        $this->refused(fn () => $this->svc()->check('WRONG4', $this->patient, 70000), 'Too many tries');
    }

    // --- putting a code on a payment ---------------------------------------------------------------------------------

    public function test_applying_lowers_the_amount_and_removing_restores_it(): void
    {
        $this->coupon();
        $payment = $this->consultPayment();

        $payment = $this->svc()->apply($payment, 'save10', $this->patient);
        $this->assertSame([63000, 70000, 7000, 'SAVE10'], [$payment->amount, $payment->original_amount, $payment->discount_cents, $payment->coupon_code]);

        $payment = $this->svc()->remove($payment, $this->patient);
        $this->assertSame([70000, null, 0, null], [$payment->amount, $payment->original_amount, $payment->discount_cents, $payment->coupon_code]);
    }

    public function test_a_second_code_replaces_the_first_and_counts_from_the_full_price(): void
    {
        $this->coupon();
        $this->coupon(['code' => 'R100', 'type' => 'fixed', 'value' => 10000]);
        $payment = $this->consultPayment();

        $this->svc()->apply($payment, 'SAVE10', $this->patient);
        $payment = $this->svc()->apply($payment->fresh(), 'R100', $this->patient);

        $this->assertSame([60000, 70000, 10000], [$payment->amount, $payment->original_amount, $payment->discount_cents]);
    }

    public function test_only_a_pending_consultation_payment_of_the_patient_takes_a_coupon(): void
    {
        $this->coupon();

        $medication = Payment::create(['patient_id' => $this->patient->id, 'amount' => 45000, 'status' => 'pending', 'description' => 'Medication - RX-1']);
        $this->refused(fn () => $this->svc()->apply($medication, 'SAVE10', $this->patient), 'consultation payment');

        $refill = Payment::create(['patient_id' => $this->patient->id, 'amount' => 45000, 'status' => 'pending', 'description' => 'Refill (1/3) - RX-1']);
        $this->assertFalse($this->svc()->acceptsCoupon($refill));

        $paid = $this->consultPayment();
        $paid->update(['status' => 'completed']);
        $this->refused(fn () => $this->svc()->apply($paid->fresh(), 'SAVE10', $this->patient), 'waiting to be paid');

        $theirs = $this->consultPayment($this->other);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc()->apply($theirs, 'SAVE10', $this->patient);
    }

    // --- counting the use ------------------------------------------------------------------------------------------------

    public function test_a_use_is_counted_once_when_the_payment_is_confirmed_and_not_before(): void
    {
        $coupon = $this->coupon(['max_uses' => 5]);
        $payment = $this->svc()->apply($this->consultPayment(), 'SAVE10', $this->patient);

        $this->assertSame(0, $coupon->fresh()->number_of_uses, 'Putting a code on is not using it up.');

        $payment->update(['status' => 'completed']);
        $this->svc()->redeem($payment->fresh());
        $this->svc()->redeem($payment->fresh());

        $this->assertSame(1, $coupon->fresh()->number_of_uses);
        $usage = CatalogCouponUsage::where('payment_reference_id', $payment->reference)->get();
        $this->assertCount(1, $usage);
        $this->assertSame([70000, 63000, true], [$usage[0]->pre_value_minor, $usage[0]->post_value_minor, $usage[0]->is_redeemed]);
    }

    public function test_a_patient_uses_a_code_once_but_others_still_can(): void
    {
        $this->coupon(['max_uses' => 2]);
        $first = $this->svc()->apply($this->consultPayment(), 'SAVE10', $this->patient);
        $first->update(['status' => 'completed']);
        $this->svc()->redeem($first->fresh());

        $this->refused(fn () => $this->svc()->apply($this->consultPayment(), 'SAVE10', $this->patient), 'already used');

        $theirs = $this->svc()->apply($this->consultPayment($this->other), 'SAVE10', $this->other);
        $this->assertSame(63000, $theirs->amount);
    }

    // --- the payment page ------------------------------------------------------------------------------------------------------

    public function test_the_payment_page_has_a_coupon_box_for_a_consultation_but_not_for_medication(): void
    {
        $payment = $this->consultPayment();
        $this->actingAs($this->patient)->get(route('payment.checkout', $payment->reference))->assertOk()->assertSeeHtml('data-coupon-box');

        $medication = Payment::create(['patient_id' => $this->patient->id, 'amount' => 45000, 'status' => 'pending', 'description' => 'Medication - RX-1']);
        $this->actingAs($this->patient)->get(route('payment.checkout', $medication->reference))->assertOk()->assertDontSeeHtml('data-coupon-box');
    }

    public function test_typing_a_code_on_the_payment_page_lowers_what_payfast_is_asked_for(): void
    {
        $this->coupon();
        $payment = $this->consultPayment();

        $this->actingAs($this->patient)->post(route('payment.coupon.apply', $payment->reference), ['coupon' => 'save10'])
            ->assertRedirect(route('payment.checkout', $payment->reference));

        $this->actingAs($this->patient)->get(route('payment.checkout', $payment->reference))->assertOk()
            ->assertSeeHtml('data-discount-row')->assertSee('R630.00')
            ->assertSee('name="amount" value="630.00"', false);

        $this->actingAs($this->patient)->delete(route('payment.coupon.remove', $payment->reference))->assertRedirect();
        $this->assertSame(70000, $payment->fresh()->amount);
    }

    public function test_a_wrong_code_shows_a_plain_message_and_changes_nothing(): void
    {
        $payment = $this->consultPayment();

        $this->actingAs($this->patient)->from(route('payment.checkout', $payment->reference))
            ->post(route('payment.coupon.apply', $payment->reference), ['coupon' => 'NOPE'])->assertRedirect(route('payment.checkout', $payment->reference));
        $this->actingAs($this->patient)->get(route('payment.checkout', $payment->reference))->assertSeeHtml('data-coupon-error')->assertSee('do not recognise');

        $this->assertSame(70000, $payment->fresh()->amount);
    }

    public function test_nobody_can_put_a_code_on_somebody_elses_payment(): void
    {
        $this->coupon();
        $theirs = $this->consultPayment($this->other);

        $this->actingAs($this->patient)->post(route('payment.coupon.apply', $theirs->reference), ['coupon' => 'SAVE10'])->assertNotFound();
        $this->assertSame(70000, $theirs->fresh()->amount);
    }

    public function test_the_confirmed_discounted_payment_is_accepted_and_the_coupon_is_used(): void
    {
        $coupon = $this->coupon(['max_uses' => 3]);
        $payment = $this->svc()->apply($this->consultPayment(), 'SAVE10', $this->patient);

        $itn = ['m_payment_id' => $payment->reference, 'pf_payment_id' => '777', 'payment_status' => 'COMPLETE', 'amount_gross' => '630.00'];
        $itn['signature'] = app(PayFastService::class)->generateSignature($itn);
        $this->post(route('payment.notify'), $itn)->assertOk();

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(1, $coupon->fresh()->number_of_uses);
    }

    public function test_the_full_price_is_refused_once_a_coupon_is_on_the_payment(): void
    {
        $this->coupon();
        $payment = $this->svc()->apply($this->consultPayment(), 'SAVE10', $this->patient);

        $itn = ['m_payment_id' => $payment->reference, 'pf_payment_id' => '778', 'payment_status' => 'COMPLETE', 'amount_gross' => '700.00'];
        $itn['signature'] = app(PayFastService::class)->generateSignature($itn);
        $this->post(route('payment.notify'), $itn)->assertStatus(400);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    // --- Operations make and switch off coupons --------------------------------------------------------------------------------

    public function test_operations_make_an_open_coupon_with_a_generated_code(): void
    {
        $c = app(Coupons::class)->create($this->ops, null, 'percent', 15, '2026-12-31', 50, null);

        $this->assertMatchesRegularExpression('/^ZAP-[A-Z0-9]{6}$/', $c->code);
        $this->assertSame(['percent', 1500, 50, 'code'], [$c->type, $c->value, $c->max_uses, $c->metadata['audience']]);
        $this->assertSame('zapmed', $c->upstream_source);
        $this->assertSame('2026-12-31', $c->expiry_dt->setTimezone('Africa/Johannesburg')->format('Y-m-d'));
        $this->assertSame(10500, $this->svc()->discountFor($c, 70000));
    }

    public function test_operations_make_a_coupon_for_one_patient_by_email(): void
    {
        $c = app(Coupons::class)->create($this->ops, 'welcome-100', 'fixed', 100, null, 1, strtoupper($this->patient->email));

        $this->assertSame(['WELCOME-100', 'fixed', 10000], [$c->code, $c->type, $c->value]);
        $this->assertSame((string) $this->patient->id, $c->metadata['patient_id']);
        $this->assertArrayNotHasKey('audience', $c->metadata);
        $this->assertSame(10000, $this->svc()->check('WELCOME-100', $this->patient, 70000)['discount_cents']);
        $this->refused(fn () => $this->svc()->check('WELCOME-100', $this->other, 70000), 'do not recognise');
    }

    public function test_bad_coupon_details_are_refused(): void
    {
        $svc = app(Coupons::class);
        $bad = function (callable $fn, string $field) {
            try {
                $fn();
                $this->fail('Expected an error on '.$field);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }
        };

        $bad(fn () => $svc->create($this->ops, null, 'percent', 0, null, null, null), 'amount');
        $bad(fn () => $svc->create($this->ops, null, 'percent', 101, null, null, null), 'amount');
        $bad(fn () => $svc->create($this->ops, null, 'fixed', 0.5, null, null, null), 'amount');
        $bad(fn () => $svc->create($this->ops, null, 'bogus', 10, null, null, null), 'type');
        $bad(fn () => $svc->create($this->ops, null, 'percent', 10, null, 0, null), 'max_uses');
        $bad(fn () => $svc->create($this->ops, null, 'percent', 10, '2026-11-01', null, null), 'expires');
        $bad(fn () => $svc->create($this->ops, null, 'percent', 10, null, null, 'nobody@example.com'), 'patient');
        $bad(fn () => $svc->create($this->ops, 'a b!', 'percent', 10, null, null, null), 'code');
        $svc->create($this->ops, 'TAKEN', 'percent', 10, null, null, null);
        $bad(fn () => $svc->create($this->ops, 'taken', 'percent', 10, null, null, null), 'code');
        $this->assertSame(1, CatalogCoupon::where('upstream_source', 'zapmed')->count());
    }

    public function test_only_operations_can_make_or_switch_off_a_coupon_and_it_is_audited(): void
    {
        $c = app(Coupons::class)->create($this->ops, 'AUDIT1', 'percent', 10, null, null, null);

        foreach ([fn () => app(Coupons::class)->create($this->patient, null, 'percent', 10, null, null, null), fn () => app(Coupons::class)->cancel($this->patient, $c)] as $try) {
            try {
                $try();
                $this->fail('Expected 403');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        app(Coupons::class)->cancel($this->ops, $c);
        app(Coupons::class)->cancel($this->ops, $c);

        $this->assertTrue($c->fresh()->is_cancelled);
        $this->refused(fn () => $this->svc()->check('AUDIT1', $this->patient, 70000), 'no longer active');
        $this->assertSame(['created', 'cancelled'], CrmAuditEvent::where('domain', 'coupon')->orderBy('id')->pluck('action')->all());
    }

    public function test_the_coupons_page_makes_lists_and_switches_off(): void
    {
        $this->actingAs($this->ops);

        Livewire::test(CouponsPage::class)->assertSeeHtml('data-empty="coupons"')
            ->set('type', 'percent')->call('create')->assertHasErrors('amount')
            ->set('amount', '10')->set('code', 'page10')->call('create')->assertHasNoErrors()->assertSeeHtml('data-created')->assertSeeHtml('data-coupon="PAGE10"')
            ->set('code', 'page10')->set('amount', '10')->call('create')->assertHasErrors('code');

        $c = CatalogCoupon::where('code', 'PAGE10')->firstOrFail();
        Livewire::test(CouponsPage::class)->call('cancelCoupon', $c->id)->assertSee('Switched off');
        $this->assertTrue($c->fresh()->is_cancelled);
    }

    public function test_an_imported_contro_coupon_cannot_be_switched_off_from_the_page(): void
    {
        $imported = $this->coupon(['code' => 'OLD1', 'metadata' => null, 'upstream_source' => 'contro']);
        $this->actingAs($this->ops);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(CouponsPage::class)->call('cancelCoupon', $imported->id);
    }

    public function test_the_page_is_for_operations_only_and_is_in_the_menu(): void
    {
        $this->actingAs($this->ops)->get(route('admin.coupons'))->assertOk()->assertSee('Coupons');
        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.coupons'))->assertForbidden();
        }
    }

    public function test_the_rewards_page_tells_patients_where_to_type_the_code(): void
    {
        $this->actingAs($this->patient)->get(route('patient.rewards'))->assertOk()->assertSee('coupon box on the payment page');
    }
}
