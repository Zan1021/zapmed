<?php

namespace App\Services\Coach;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use App\Services\PayFastService;
use Illuminate\Support\Facades\Log;

/**
 * The coach add-on (R450/month): the plan, a doctor offering it at a consultation, and what happens once paid.
 *
 * The add-on is a subscription on the plan in config coach.plan_slug. A doctor never switches it on for free:
 * "attach" creates an unpaid (pending) membership that remembers who offered it, and the member pays on
 * /coach/join. When PayFast confirms the first payment the member gets a coach (see onActivated).
 */
class CoachAddon
{
    public function __construct(
        private readonly CoachAccess $access,
        private readonly CoachAssigner $assigner,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function plan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::active()->where('slug', config('coach.plan_slug'))->first();
    }

    public function isAddonPlan(Subscription $subscription): bool
    {
        return $subscription->plan?->slug === config('coach.plan_slug');
    }

    /** The member's unpaid add-on membership, if one is waiting for payment. */
    public function pendingFor(User $patient): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $patient->id)
            ->where('status', 'pending')
            ->whereHas('plan', fn ($q) => $q->where('slug', config('coach.plan_slug')))
            ->latest('id')
            ->first();
    }

    /** True when a doctor has offered this member the add-on and it is not paid yet. */
    public function offeredByDoctor(User $patient): bool
    {
        return ! empty(data_get($this->pendingFor($patient)?->metadata, 'attached_by'));
    }

    /**
     * A doctor offers the add-on during (or after) a consultation they own. Creates the unpaid membership and tells
     * the member how to pay. Returns the pending subscription. Asking twice returns the same one.
     *
     * @throws \DomainException when the doctor may not do this or the member already has the add-on
     */
    public function attachByDoctor(User $doctor, Appointment $appointment): Subscription
    {
        if ($doctor->role !== UserRole::Doctor || $appointment->doctor_id !== $doctor->id) {
            throw new \DomainException('Only the doctor of this consultation can offer the coach add-on.');
        }

        $patient = $appointment->patient;
        if (! $patient || $patient->role !== UserRole::Patient) {
            throw new \DomainException('This consultation has no patient.');
        }

        if ($this->access->patientHasAddon($patient)) {
            throw new \DomainException('This member already has the coach add-on.');
        }

        $plan = $this->plan();
        if (! $plan) {
            throw new \DomainException('The coach add-on plan is not set up.');
        }

        $existing = $this->pendingFor($patient);
        if ($existing) {
            return $existing;
        }

        $subscription = Subscription::create([
            'user_id' => $patient->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'pending',
            'payment_reference' => PayFastService::generateSubscriptionReference(),
            'metadata' => [
                'attached_by' => $doctor->id,
                'appointment_id' => $appointment->id,
                'attached_at' => now()->toISOString(),
            ],
        ]);

        $this->tellMember($patient, $subscription);

        return $subscription;
    }

    /**
     * Called after PayFast confirms a payment on a subscription. When it is the add-on's first payment the member
     * is given a coach. Never throws: a failed assignment is retried hourly by coach:assign-pending.
     */
    public function onActivated(Subscription $subscription): void
    {
        try {
            if (! $this->isAddonPlan($subscription) || ! $subscription->user) {
                return;
            }

            $this->assigner->assign($subscription->user);
        } catch (\Throwable $e) {
            Log::error('coach addon: assigning a coach failed', ['subscription' => $subscription->id, 'error' => $e->getMessage()]);
        }
    }

    /** The message carries no health detail, only the offer and the link. OFF until COACH_ADDON_NOTIFY=true. */
    private function tellMember(User $patient, Subscription $subscription): void
    {
        if (! config('coach.addon.notify_enabled')) {
            return;
        }

        try {
            $this->dispatcher->send(new OutboundMessage(
                templateKey: 'coach.addon_offer', category: 'transactional', user: $patient,
                phone: $patient->phone,
                subject: 'Your Zapmed health coach',
                body: 'Your doctor has added the Zapmed health coach to your plan. To start, sign in and finish the payment here: '.route('coach.join'),
                meta: ['subscription_id' => $subscription->id],
            ), ['email']);
        } catch (\Throwable $e) {
            Log::error('coach addon: offer message failed', ['subscription' => $subscription->id, 'error' => $e->getMessage()]);
        }
    }
}
