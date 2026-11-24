<?php

namespace App\Livewire\Patient;

use App\Services\Patient\PatientOrders;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** My Orders tab (Craig's guide 3.1, 3.2): status, repeats left, next payment, pause / continue / cancel, archive. */
class MyOrders extends Component
{
    public bool $showArchived = false;
    public ?int $acting = null;
    public string $mode = '';          // pause | cancel
    public string $reason = '';

    public function ask(int $orderId, string $mode): void
    {
        $this->resetErrorBag();
        abort_unless(in_array($mode, ['pause', 'cancel'], true), 422);
        $this->acting = $orderId;
        $this->mode = $mode;
        $this->reason = '';
    }

    public function close(): void
    {
        $this->reset(['acting', 'mode', 'reason']);
        $this->resetErrorBag();
    }

    public function confirm(PatientOrders $orders): void
    {
        $this->resetErrorBag();
        abort_unless($this->acting, 422);

        $this->mode === 'cancel'
            ? $orders->cancel(Auth::user(), $this->acting, $this->reason)
            : $orders->pause(Auth::user(), $this->acting, $this->reason);

        $this->close();
    }

    public function continueOrder(int $orderId, PatientOrders $orders): void
    {
        $this->resetErrorBag();
        $orders->continue(Auth::user(), $orderId);
    }

    public function archive(int $orderId, PatientOrders $orders): void
    {
        $this->resetErrorBag();
        $orders->archive(Auth::user(), $orderId);
    }

    public function unarchive(int $orderId, PatientOrders $orders): void
    {
        $orders->unarchive(Auth::user(), $orderId);
    }

    public function resumeCheckout(int $orderId, PatientOrders $orders)
    {
        $this->resetErrorBag();
        $orders->restartSubscriptionCheckout(Auth::user(), $orderId);

        return $this->redirect(route('subscription.checkout'));
    }

    public function render(PatientOrders $orders)
    {
        return view('livewire.patient.my-orders', [
            'cards' => $orders->cards(Auth::user(), $this->showArchived),
        ])->layout('layouts.app');
    }
}
