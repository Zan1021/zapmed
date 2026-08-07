<?php

namespace App\Livewire\Admin;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Str;
use Livewire\Component;

class SubscriptionPlans extends Component
{
    // Plan form
    public bool $showForm = false;
    public ?int $editingPlanId = null;
    public string $name = '';
    public string $description = '';
    public string $price = '';
    public string $billing_cycle = 'monthly';
    public int $cycle_frequency = 1;
    public int $consultations_per_month = 0;
    public bool $includes_chronic_renewals = false;
    public bool $includes_priority_booking = false;
    public bool $includes_messaging = true;
    public string $features_text = '';

    public function createPlan(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editPlan(int $planId): void
    {
        $plan = SubscriptionPlan::findOrFail($planId);

        $this->editingPlanId = $plan->id;
        $this->name = $plan->name;
        $this->description = $plan->description ?? '';
        $this->price = number_format($plan->price / 100, 2, '.', '');
        $this->billing_cycle = $plan->billing_cycle;
        $this->cycle_frequency = $plan->cycle_frequency;
        $this->consultations_per_month = $plan->consultations_per_month;
        $this->includes_chronic_renewals = $plan->includes_chronic_renewals;
        $this->includes_priority_booking = $plan->includes_priority_booking;
        $this->includes_messaging = $plan->includes_messaging;
        $this->features_text = $plan->features ? implode("\n", $plan->features) : '';

        $this->showForm = true;
    }

    public function savePlan(): void
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,annually',
            'cycle_frequency' => 'required|integer|min:1|max:12',
            'consultations_per_month' => 'required|integer|min:0',
        ]);

        $priceInCents = (int) round((float) $this->price * 100);
        $features = array_filter(array_map('trim', explode("\n", $this->features_text)));

        $data = [
            'name' => $this->name,
            'slug' => $this->editingPlanId
                ? SubscriptionPlan::find($this->editingPlanId)->slug
                : Str::slug($this->name),
            'description' => $this->description ?: null,
            'price' => $priceInCents,
            'billing_cycle' => $this->billing_cycle,
            'cycle_frequency' => $this->cycle_frequency,
            'consultations_per_month' => $this->consultations_per_month,
            'includes_chronic_renewals' => $this->includes_chronic_renewals,
            'includes_priority_booking' => $this->includes_priority_booking,
            'includes_messaging' => $this->includes_messaging,
            'features' => !empty($features) ? $features : null,
        ];

        if ($this->editingPlanId) {
            SubscriptionPlan::findOrFail($this->editingPlanId)->update($data);
            session()->flash('message', 'Plan updated.');
        } else {
            $data['sort_order'] = SubscriptionPlan::max('sort_order') + 1;
            SubscriptionPlan::create($data);
            session()->flash('message', 'Plan created.');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function togglePlanStatus(int $planId): void
    {
        $plan = SubscriptionPlan::findOrFail($planId);
        $plan->update(['is_active' => !$plan->is_active]);
    }

    public function deletePlan(int $planId): void
    {
        $plan = SubscriptionPlan::findOrFail($planId);

        // Don't delete if there are active subscriptions
        if ($plan->subscriptions()->whereIn('status', ['active', 'paused'])->exists()) {
            session()->flash('error', 'Cannot delete a plan with active subscriptions.');
            return;
        }

        $plan->delete();
        session()->flash('message', 'Plan deleted.');
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingPlanId = null;
        $this->name = '';
        $this->description = '';
        $this->price = '';
        $this->billing_cycle = 'monthly';
        $this->cycle_frequency = 1;
        $this->consultations_per_month = 0;
        $this->includes_chronic_renewals = false;
        $this->includes_priority_booking = false;
        $this->includes_messaging = true;
        $this->features_text = '';
    }

    public function getStatsProperty(): array
    {
        return [
            'total_plans' => SubscriptionPlan::count(),
            'active_plans' => SubscriptionPlan::active()->count(),
            'active_subscriptions' => Subscription::active()->count(),
            'mrr' => Subscription::active()
                ->with('plan')
                ->get()
                ->sum(fn ($sub) => $sub->plan->price ?? 0),
        ];
    }

    public function render()
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->get();

        return view('livewire.admin.subscription-plans', [
            'plans' => $plans,
        ])->layout('layouts.app');
    }
}
