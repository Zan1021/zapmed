<?php

namespace App\Livewire\Patient;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\PayFastService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MySubscription extends Component
{
    public ?Subscription $subscription = null;
    public ?int $selectedPlanId = null;
    public bool $showPlans = false;
    public bool $confirmingCancel = false;
    public string $cancellationReason = '';

    public function mount(): void
    {
        // Load current active/grace subscription
        $this->subscription = Subscription::where('user_id', Auth::id())
            ->whereIn('status', ['active', 'cancelled', 'paused'])
            ->with('plan')
            ->latest()
            ->first();

        // Show plans if no active subscription
        if (!$this->subscription || (!$this->subscription->isActive() && !$this->subscription->onGracePeriod())) {
            $this->showPlans = true;
        }
    }

    /**
     * Select a plan and proceed to PayFast checkout.
     */
    public function subscribe(int $planId): void
    {
        $plan = SubscriptionPlan::active()->findOrFail($planId);
        $user = Auth::user();

        // Make (or reuse) the unpaid subscription, build the PayFast form and park it in the session.
        // A coach add-on offered by a doctor is reused, not duplicated.
        $pending = Subscription::where('user_id', $user->id)
            ->where('subscription_plan_id', $plan->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
        app(\App\Services\Subscriptions\SubscriptionCheckout::class)->begin($user, $plan, $pending);

        $this->redirect(route('subscription.checkout'));
    }

    /**
     * Cancel subscription.
     */
    public function cancelSubscription(): void
    {
        if (!$this->subscription || !$this->subscription->isActive()) {
            return;
        }

        // Cancel with PayFast
        if ($this->subscription->payfast_token) {
            $payfast = app(PayFastService::class);
            $payfast->cancelSubscription($this->subscription->payfast_token);
        }

        $this->subscription->cancel($this->cancellationReason ?: null);
        $this->confirmingCancel = false;
        $this->cancellationReason = '';

        session()->flash('message', 'Your subscription has been cancelled. You can continue using it until the end of your billing period.');
    }

    public function getPlansProperty()
    {
        return SubscriptionPlan::active()->get();
    }

    public function render()
    {
        return view('livewire.patient.my-subscription')
            ->layout('layouts.app');
    }
}
