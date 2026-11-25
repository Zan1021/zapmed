<?php

namespace App\Services\Operations;

use App\Models\CatalogCoupon;
use App\Models\CatalogCouponUsage;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Coupon codes typed at checkout (Craig's guide 3.1 Rewards, 6.2).
 *
 *  - A coupon takes money off a CONSULTATION payment only (a payment with an appointment). Medication and repeat payments never take one.
 *  - A code works when it is unused up, not cancelled, not expired and meant for this patient (made for them) or open to anyone with
 *    the code (metadata audience "all" or "code"). Coupons imported from Contro carry neither, so they cannot be used by typing the code.
 *  - A patient can use one code once. They cannot try more than a few codes a minute.
 *  - The discount lowers the amount PayFast is asked for, never below the PayFast minimum (R5).
 *  - Applying a code only changes the pending payment. The use is counted when the payment is CONFIRMED (redeem), so a coupon that was
 *    put on and never paid for is not used up.
 *
 * Percent coupons hold basis points (1000 = 10%), fixed coupons hold cents, as everywhere else.
 */
class CouponRedemption
{
    /** @return array{coupon: CatalogCoupon, discount_cents: int, final_cents: int} */
    public function check(string $code, User $patient, int $amountCents, ?Payment $payment = null): array
    {
        $this->throttle($patient);

        $code = $this->normalise($code);
        if ($code === '') {
            $this->fail('Enter your coupon code.');
        }

        $coupon = CatalogCoupon::whereRaw('upper(code) = ?', [$code])->orderByDesc('id')->first();
        if (! $coupon || ! $this->meantFor($coupon, $patient)) {
            $this->fail('We do not recognise that code.');
        }
        if ($coupon->is_cancelled) {
            $this->fail('That code is no longer active.');
        }
        if ($coupon->expiry_dt && $coupon->expiry_dt->isPast()) {
            $this->fail('That code has expired.');
        }
        if ($coupon->max_uses !== null && (int) $coupon->number_of_uses >= $coupon->max_uses) {
            $this->fail('That code has already been used the most times it can be.');
        }
        if ($this->alreadyUsedBy($coupon, $patient, $payment)) {
            $this->fail('You have already used that code.');
        }

        $discount = $this->discountFor($coupon, $amountCents);
        if ($discount <= 0) {
            $this->fail('That code does not take anything off this payment.');
        }

        // a wrong code is "used up" against the throttle; a good one is not held against the patient
        RateLimiter::clear($this->key($patient));

        return ['coupon' => $coupon, 'discount_cents' => $discount, 'final_cents' => $amountCents - $discount];
    }

    /** Put a code on a pending consultation payment. Replaces a code already on it. */
    public function apply(Payment $payment, string $code, User $patient): Payment
    {
        $this->guardPayment($payment, $patient);

        return DB::transaction(function () use ($payment, $code, $patient) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $base = (int) ($payment->original_amount ?: $payment->amount);

            $result = $this->check($code, $patient, $base, $payment);
            $payment->forceFill([
                'original_amount' => $base, 'coupon_code' => $result['coupon']->code,
                'discount_cents' => $result['discount_cents'], 'amount' => $result['final_cents'],
            ])->save();

            return $payment;
        });
    }

    /** Take the code off again: the patient pays the full amount. */
    public function remove(Payment $payment, User $patient): Payment
    {
        $this->guardPayment($payment, $patient);

        if ($payment->coupon_code && $payment->original_amount) {
            $payment->forceFill(['amount' => $payment->original_amount, 'coupon_code' => null, 'discount_cents' => 0, 'original_amount' => null])->save();
        }

        return $payment;
    }

    /**
     * The payment was CONFIRMED by PayFast: count the use. Safe to call twice (one usage row per payment). A coupon that ran out in the
     * meantime still honours this payment, the patient already paid the lower amount.
     */
    public function redeem(Payment $payment): void
    {
        if (! $payment->coupon_code || $payment->discount_cents <= 0) {
            return;
        }

        DB::transaction(function () use ($payment) {
            if (CatalogCouponUsage::where('payment_reference_id', $payment->reference)->exists()) {
                return;
            }
            $coupon = CatalogCoupon::whereRaw('upper(code) = ?', [$this->normalise($payment->coupon_code)])->orderByDesc('id')->lockForUpdate()->first();
            if (! $coupon) {
                return;
            }

            $coupon->increment('number_of_uses');
            CatalogCouponUsage::create([
                'catalog_coupon_id' => $coupon->id, 'pre_value_minor' => (int) $payment->original_amount, 'post_value_minor' => (int) $payment->amount,
                'is_redeemed' => true, 'payment_reference_id' => $payment->reference, 'upstream_source' => 'zapmed',
            ]);
        });
    }

    /** True for a payment a coupon may go on: pending, the patient's own, for a consultation. */
    public function acceptsCoupon(Payment $payment): bool
    {
        return $payment->status === 'pending' && $payment->appointment_id !== null
            && ! str_starts_with((string) $payment->description, 'Medication -') && ! str_starts_with((string) $payment->description, 'Refill');
    }

    public function discountFor(CatalogCoupon $coupon, int $amountCents): int
    {
        $off = match ($coupon->type) {
            'percent' => intdiv($amountCents * max(0, min(10000, (int) $coupon->value)), 10000),
            'fixed' => max(0, (int) $coupon->value),
            default => 0,
        };
        $room = max(0, $amountCents - (int) config('operations.coupons.minimum_payment_cents', 500));

        return min($off, $room);
    }

    private function meantFor(CatalogCoupon $coupon, User $patient): bool
    {
        $meta = (array) $coupon->metadata;
        if (isset($meta['patient_id'])) {
            return (string) $meta['patient_id'] === (string) $patient->id;
        }

        return in_array($meta['audience'] ?? null, (array) config('operations.coupons.open_audiences', ['all', 'code']), true);
    }

    private function alreadyUsedBy(CatalogCoupon $coupon, User $patient, ?Payment $except): bool
    {
        return Payment::where('patient_id', $patient->id)->whereRaw('upper(coupon_code) = ?', [$this->normalise($coupon->code)])
            ->where('status', 'completed')->when($except, fn ($q) => $q->where('id', '!=', $except->id))->exists();
    }

    private function guardPayment(Payment $payment, User $patient): void
    {
        abort_unless($payment->patient_id === $patient->id, 403);

        if (! $this->acceptsCoupon($payment)) {
            $this->fail('A coupon can only be used on a consultation payment that is waiting to be paid.');
        }
    }

    private function throttle(User $patient): void
    {
        $max = (int) config('operations.coupons.attempts_per_minute', 8);
        if (RateLimiter::tooManyAttempts($this->key($patient), $max)) {
            $this->fail('Too many tries. Please wait a minute and try again.');
        }
        RateLimiter::hit($this->key($patient), 60);
    }

    private function key(User $patient): string
    {
        return 'coupon-attempts:'.$patient->id;
    }

    private function normalise(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', $code));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['coupon' => $message]);
    }
}
