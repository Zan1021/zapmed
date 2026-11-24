<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Commerce\LiveOrderBridge;
use App\Services\PayFastService;
use Illuminate\Support\Facades\Log;

/**
 * Starts a PayFast subscription payment: makes (or reuses) the unpaid subscription, builds the PayFast form
 * and parks it in the session for /subscription/checkout. Shared by the My Subscription page and the coach
 * add-on join page so both buy the same way. Nothing is charged here; PayFast calls back when the member pays.
 */
class SubscriptionCheckout
{
    public function __construct(private readonly PayFastService $payfast)
    {
    }

    /** @param Subscription|null $pending an unpaid subscription on this plan to reuse instead of making a new one */
    /**
     * @param  bool  $tokenised  true for a repeat order we charge ourselves (PayFast saves the card, we charge the token). The
     *                          subscription is marked charge_mode 'adhoc'. Memberships stay false: PayFast bills those.
     */
    public function begin(User $user, SubscriptionPlan $plan, ?Subscription $pending = null, bool $tokenised = false): Subscription
    {
        if ($pending && ($pending->user_id !== $user->id || $pending->subscription_plan_id !== $plan->id || $pending->status !== 'pending')) {
            $pending = null;
        }

        $subscription = $pending ?? Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'pending',
            'payment_reference' => PayFastService::generateSubscriptionReference(),
        ]);

        if (! $subscription->payment_reference) {
            $subscription->update(['payment_reference' => PayFastService::generateSubscriptionReference()]);
        }

        if ($tokenised && $subscription->charge_mode !== 'adhoc') {
            $subscription->update(['charge_mode' => 'adhoc']);
        }

        $paymentData = $this->payfast->generateSubscriptionData($user, $plan, $subscription, $tokenised);

        // Mirror into the CRM Order aggregate. A bridge failure must never break the customer's purchase.
        try {
            app(LiveOrderBridge::class)->onSubscriptionStarted($subscription);
        } catch (\Throwable $e) {
            Log::warning('LiveOrderBridge onSubscriptionStarted failed', [
                'subscription_id' => $subscription->id, 'error' => $e->getMessage(),
            ]);
        }

        session([
            'subscription_checkout' => [
                'payment_data' => $paymentData,
                'process_url' => $this->payfast->getProcessUrl(),
                'subscription_id' => $subscription->id,
                'plan_name' => $plan->name,
            ],
        ]);

        return $subscription;
    }
}
