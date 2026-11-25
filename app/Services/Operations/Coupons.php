<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\CatalogCoupon;
use App\Models\User;
use App\Services\Compliance\AuditTrail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Operations make and switch off coupons (Craig's guide 6.2). A coupon is either made for ONE patient (found by email) or open to anyone
 * who has the code. Percent coupons are 1 to 100 percent, fixed ones are an amount in rand. Redeeming is CouponRedemption.
 */
class Coupons
{
    public function __construct(private readonly AuditTrail $audit, private readonly \App\Services\Patient\PatientRewards $rewards)
    {
    }

    /**
     * @param  string  $type  percent|fixed
     * @param  float  $amount  percent (1 to 100) or rand
     */
    public function create(User $ops, ?string $code, string $type, float $amount, ?string $expires, ?int $maxUses, ?string $patientEmail): CatalogCoupon
    {
        abort_unless($ops->role === UserRole::Admin, 403);

        if (! in_array($type, ['percent', 'fixed'], true)) {
            throw ValidationException::withMessages(['type' => 'Choose a percentage or an amount in rand.']);
        }
        if ($type === 'percent' ? ($amount < 1 || $amount > 100) : ($amount < 1 || $amount > 100000)) {
            throw ValidationException::withMessages(['amount' => $type === 'percent' ? 'A percentage must be between 1 and 100.' : 'Enter an amount between R1 and R100 000.']);
        }
        if ($maxUses !== null && ($maxUses < 1 || $maxUses > 1000000)) {
            throw ValidationException::withMessages(['max_uses' => 'How many times it can be used must be 1 or more.']);
        }
        $expiry = null;
        if ($expires !== null && trim($expires) !== '') {
            try {
                $expiry = \Illuminate\Support\Carbon::parse($expires, config('doctor.time.timezone'))->endOfDay()->utc();
            } catch (\Throwable) {
                throw ValidationException::withMessages(['expires' => 'Enter the last day the code works.']);
            }
            if ($expiry->isPast()) {
                throw ValidationException::withMessages(['expires' => 'The last day must be today or later.']);
            }
        }

        $patient = null;
        if ($patientEmail !== null && trim($patientEmail) !== '') {
            $patient = User::where('role', UserRole::Patient->value)->whereRaw('lower(email) = ?', [strtolower(trim($patientEmail))])->first();
            if (! $patient) {
                throw ValidationException::withMessages(['patient' => 'No patient has that email address.']);
            }
        }

        $code = strtoupper(preg_replace('/\s+/', '', (string) $code));
        if ($code === '') {
            do {
                $code = 'ZAP-'.strtoupper(Str::random(6));
            } while (CatalogCoupon::whereRaw('upper(code) = ?', [$code])->exists());
        } elseif (! preg_match('/^[A-Z0-9][A-Z0-9_-]{2,29}$/', $code)) {
            throw ValidationException::withMessages(['code' => 'A code is 3 to 30 letters, numbers, dashes or underscores.']);
        } elseif (CatalogCoupon::whereRaw('upper(code) = ?', [$code])->exists()) {
            throw ValidationException::withMessages(['code' => 'That code already exists. Choose another.']);
        }

        $coupon = CatalogCoupon::create([
            'code' => $code, 'type' => $type, 'value' => (int) round($amount * 100),   // percent: basis points (10% = 1000), fixed: cents
            'expiry_dt' => $expiry, 'number_of_uses' => 0, 'max_uses' => $maxUses, 'is_cancelled' => false,
            'metadata' => ['origin' => 'operations', 'created_by' => $ops->id] + ($patient ? [] : ['audience' => 'code']),
            'upstream_source' => 'zapmed',
        ]);
        if ($patient) {
            $this->rewards->assign($coupon, $patient);
        }

        $this->audit->record('coupon', 'created', 'coupon', (string) $coupon->id, null, [
            'code' => $coupon->code, 'type' => $type, 'value' => $coupon->value, 'max_uses' => $maxUses, 'patient_id' => $patient?->id,
        ], $ops->id);

        return $coupon;
    }

    public function cancel(User $ops, CatalogCoupon $coupon): CatalogCoupon
    {
        abort_unless($ops->role === UserRole::Admin, 403);

        if (! $coupon->is_cancelled) {
            $coupon->update(['is_cancelled' => true]);
            $this->audit->record('coupon', 'cancelled', 'coupon', (string) $coupon->id, null, ['code' => $coupon->code], $ops->id);
        }

        return $coupon;
    }
}