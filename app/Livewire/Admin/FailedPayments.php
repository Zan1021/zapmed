<?php

namespace App\Livewire\Admin;

use App\Models\Subscription;
use Livewire\Component;
use Livewire\WithPagination;

class FailedPayments extends Component
{
    use WithPagination;

    public string $filter = 'failed'; // failed, all
    public string $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function markResolved(int $id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->update([
            'status' => 'active',
            'metadata' => array_merge($subscription->metadata ?? [], [
                'resolved_at' => now()->toISOString(),
                'resolved_by' => auth()->id(),
            ]),
        ]);

        session()->flash('message', 'Subscription marked as resolved and reactivated.');
    }

    public function markContacted(int $id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->update([
            'metadata' => array_merge($subscription->metadata ?? [], [
                'contacted_at' => now()->toISOString(),
                'contacted_by' => auth()->user()->name,
            ]),
        ]);

        session()->flash('message', 'Marked as contacted.');
    }

    public function pauseSubscription(int $id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->update(['status' => 'paused']);

        session()->flash('message', 'Subscription paused.');
    }

    public function resendEmail(int $id)
    {
        $subscription = Subscription::with('user', 'plan')->findOrFail($id);
        $user = $subscription->user;

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Dear {$user->first_name},\n\n" .
                "This is a reminder that your subscription payment of {$subscription->plan->formatted_price} for your {$subscription->plan->name} plan has failed.\n\n" .
                "Please log in to your Zapmed account and update your payment method to avoid losing access to your consultations.\n\n" .
                "Login: " . url('/login') . "\n\n" .
                "If you need assistance, please reply to this email.\n\n" .
                "Kind regards,\nThe Zapmed Team",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Payment Reminder - Your Subscription Needs Attention | Zapmed');
                }
            );

            session()->flash('message', "Reminder email sent to {$user->email}");
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send email: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = Subscription::with(['user', 'plan'])
            ->when($this->filter === 'failed', fn ($q) => $q->where('status', 'payment_failed'))
            ->when($this->filter === 'all', fn ($q) => $q->whereIn('status', ['payment_failed', 'paused']))
            ->when($this->search, function ($q) {
                $q->whereHas('user', function ($uq) {
                    $uq->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->latest('updated_at');

        $subscriptions = $query->paginate(15);
        $failedCount = Subscription::where('status', 'payment_failed')->count();

        return view('livewire.admin.failed-payments', compact('subscriptions', 'failedCount'));
    }
}
