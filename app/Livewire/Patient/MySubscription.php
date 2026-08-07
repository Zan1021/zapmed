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

        // Create subscription record
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'pending',
            'payment_reference' => PayFastService::generateSubscriptionReference(),
        ]);

        // Generate PayFast data and redirect
        $payfast = app(PayFastService::class);
        $paymentData = $payfast->generateSubscriptionData($user, $plan, $subscription);
        $processUrl = $payfast->getProcessUrl();

        // Store data in session for the checkout view
        session([
            'subscription_checkout' => [
                'payment_data' => $paymentData,
                'process_url' => $processUrl,
                'subscription_id' => $subscription->id,
                'plan_name' => $plan->name,
            ],
        ]);

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
