<?php

namespace App\Services\Patient;

use App\Models\CatalogCoupon;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The Rewards tab (Craig's guide 3.1): coupons, vouchers and discounts a patient can use.
 *
 * A coupon is shown to a patient only when it is meant for them:
 *   - metadata.patient_id = their id (kept as TEXT, so the same query works on SQLite and PostgreSQL), or
 *   - metadata.audience = "all" (a public offer Operations chose to show everyone).
 * Imported Contro coupons carry neither, so a patient never sees somebody else's private code.
 * Cancelled, expired and used-up coupons are left out. Nothing here redeems or charges anything.
 */
class PatientRewards
{
    /** @return Collection<int,array{code:string,description:string,expires:?\Illuminate\Support\Carbon,uses_left:?int}> */
    public function available(User $patient): Collection
    {
        return CatalogCoupon::query()
            ->where('is_cancelled', false)
            ->where(fn ($q) => $q->whereNull('expiry_dt')->orWhere('expiry_dt', '>', now()))
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('number_of_uses', '<', 'max_uses'))
            ->where(fn ($q) => $q->where('metadata->patient_id', (string) $patient->id)->orWhere('metadata->audience', 'all'))
            ->orderBy('expiry_dt')->orderBy('id')->limit(100)->get()
            ->map(fn (CatalogCoupon $c) => [
                'code' => $c->code,
                'description' => $this->describe($c),
                'expires' => $c->expiry_dt,
                'uses_left' => $c->max_uses !== null ? max(0, $c->max_uses - (int) $c->number_of_uses) : null,
            ]);
    }

    /** Make a coupon private to one patient. Used by the Operations phase. */
    public function assign(CatalogCoupon $coupon, User $patient): CatalogCoupon
    {
        $coupon->update(['metadata' => array_merge((array) $coupon->metadata, ['patient_id' => (string) $patient->id])]);

        return $coupon;
    }

    private function describe(CatalogCoupon $c): string
    {
        // value is basis points for a percentage (1000 = 10%) and minor units (cents) for a fixed amount
        return $c->type === 'percent'
            ? rtrim(rtrim(number_format($c->value / 100, 2, '.', ''), '0'), '.').'% off'
            : 'R'.number_format($c->value / 100, 2).' off';
    }
}
