<?php

namespace App\Services\Operations;

use App\Models\CatalogCoupon;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\OutboundMessage;
use App\Services\Patient\PatientMessenger;
use App\Services\Patient\PatientRewards;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The "you did not finish your order" reminder (Craig's guide 6.1).
 *
 *  - An order still waiting for payment after config after_hours (24h), and not older than max_age_days, gets ONE reminder
 *    on the patient's chosen channel with a link to My Orders, where "Finish paying" resumes the checkout.
 *  - The message names nothing about the treatment and carries no health detail.
 *  - OFF until OPS_ABANDONED_NOTIFY=true: with it off this does nothing at all (no coupon, no flag), so turning it on later only
 *    reaches recent carts.
 *  - A thank-you coupon is a separate switch (OPS_ABANDONED_COUPON), OFF, because checkout cannot take a coupon code yet.
 *  - Orders the patient archived, paid, cancelled or that have a card payment started in the last hour are left alone.
 */
class AbandonedCheckouts
{
    public function __construct(
        private readonly PatientMessenger $messenger,
        private readonly PatientRewards $rewards,
    ) {
    }

    /** @return Collection<int,Order> */
    public function candidates(): Collection
    {
        $cfg = config('operations.abandoned_checkout');
        $oldest = now()->subDays((int) $cfg['max_age_days']);
        $newest = now()->subHours((int) $cfg['after_hours']);

        return Order::with('patient')
            ->where('status', 'PendingPayment')
            ->whereNull('abandoned_followup_at')->whereNull('archived_at')
            ->whereBetween('ordered_at', [$oldest, $newest])
            ->whereDoesntHave('payments', fn ($q) => $q->where(fn ($p) => $p->where('status', 'completed')->orWhere('created_at', '>', now()->subHour())))
            ->orderBy('id')->limit(500)->get();
    }

    /** Send the reminders. Returns how many patients were reminded. */
    public function run(): int
    {
        if (! config('operations.abandoned_checkout.notify_enabled')) {
            return 0;
        }

        $sent = 0;
        foreach ($this->candidates() as $order) {
            $patient = $order->patient;
            if (! $patient || $patient->role->value !== 'patient') {
                continue;
            }

            // Mark first: a crash half way must never lead to a second reminder.
            $order->forceFill(['abandoned_followup_at' => now()])->save();

            $coupon = config('operations.abandoned_checkout.coupon.enabled') ? $this->makeCoupon($patient) : null;
            $body = 'You started an order with Zapmed and did not finish paying. You can pick up where you left off: sign in and open My Orders.';
            if ($coupon) {
                $body .= ' As a thank you, here is a code for you: '.$coupon->code.'. It is valid until '.$coupon->expiry_dt->format('j M Y').'.';
            }

            $this->messenger->send(new OutboundMessage(
                templateKey: 'ops.abandoned_checkout', category: 'transactional', user: $patient, phone: $patient->phone,
                subject: 'Finish your Zapmed order', body: $body.' '.route('patient.orders'),
                meta: ['order_id' => $order->id, 'coupon' => $coupon?->code],
            ));
            $sent++;
        }

        return $sent;
    }

    private function makeCoupon(User $patient): CatalogCoupon
    {
        $cfg = config('operations.abandoned_checkout.coupon');

        do {
            $code = 'BACK-'.strtoupper(Str::random(6));
        } while (CatalogCoupon::where('code', $code)->exists());

        $coupon = CatalogCoupon::create([
            'code' => $code, 'type' => 'percent', 'value' => (int) $cfg['percent'] * 100, 'max_uses' => 1, 'number_of_uses' => 0,
            'is_cancelled' => false, 'expiry_dt' => now()->addDays((int) $cfg['valid_days']),
            'metadata' => ['origin' => 'abandoned_checkout'],
        ]);

        return $this->rewards->assign($coupon, $patient);
    }
}
