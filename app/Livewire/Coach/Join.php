<?php

namespace App\Livewire\Coach;

use App\Models\SubscriptionPlan;
use App\Services\Coach\CoachAccess;
use App\Services\Coach\CoachAddon;
use App\Services\Subscriptions\SubscriptionCheckout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * "Join the Zapmed health coach": the page a member lands on to buy the R450/month add-on, either on their own or
 * because their doctor offered it. The button hands over to the normal PayFast subscription checkout.
 */
class Join extends Component
{
    public function mount(CoachAccess $access): void
    {
        // Already a member: go straight to the app.
        if ($access->patientHasAddon(Auth::user())) {
            $this->redirect(route('coach.today'), navigate: false);
        }
    }

    public function join(CoachAddon $addon, SubscriptionCheckout $checkout): void
    {
        $user = Auth::user();
        $plan = $addon->plan();

        if (! $plan instanceof SubscriptionPlan) {
            session()->flash('message', 'The health coach is not available right now. Please contact support.');

            return;
        }

        $checkout->begin($user, $plan, $addon->pendingFor($user));

        $this->redirect(route('subscription.checkout'));
    }

    public function render(CoachAddon $addon)
    {
        $user = Auth::user();

        return view('livewire.coach.join', [
            'plan' => $addon->plan(),
            'offeredByDoctor' => $addon->offeredByDoctor($user),
        ])->layout('layouts.app');
    }
}
