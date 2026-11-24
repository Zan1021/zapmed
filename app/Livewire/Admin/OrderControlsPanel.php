<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\OrderAuditEvent;
use App\Models\OrderDiscount;
use App\Services\Operations\OrderControls;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Inside the order board drawer (Craig's guide 6.2): service-fee coupon, manual payment record and the audit trail. */
class OrderControlsPanel extends Component
{
    public int $orderId;

    // coupon
    public string $type = 'percent';
    public string $value = '';
    public string $months = '';              // '' = until switched off, 1 to 6
    public string $reason = '';
    public string $disableReason = '';

    // manual payment
    public string $transactionId = '';
    public string $amount = '';              // rand, e.g. 299.00
    public string $paidAt = '';
    public string $paymentReference = '';
    public string $result = 'completed';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $this->paidAt = now(config('doctor.time.timezone'))->format('Y-m-d\TH:i');
    }

    public function addDiscount(OrderControls $controls): void
    {
        $this->resetErrorBag();
        $order = Order::findOrFail($this->orderId);

        $value = $this->type === 'fixed' ? $this->toCents($this->value, 'value') : (int) $this->value;
        $controls->addDiscount(Auth::user(), $order, $this->type, $value, $this->months === '' ? null : (int) $this->months, $this->reason);

        $this->reset(['value', 'months', 'reason']);
    }

    public function disableDiscount(int $id, OrderControls $controls): void
    {
        $this->resetErrorBag();
        $controls->disableDiscount(Auth::user(), OrderDiscount::where('order_id', $this->orderId)->findOrFail($id), $this->disableReason);
        $this->reset('disableReason');
    }

    public function recordPayment(OrderControls $controls): void
    {
        $this->resetErrorBag();
        $order = Order::findOrFail($this->orderId);

        $controls->recordManualPayment(
            Auth::user(), $order, $this->transactionId, $this->toCents($this->amount, 'amount'),
            $this->paidAt, $this->paymentReference, $this->result,
        );

        $this->reset(['transactionId', 'amount', 'paymentReference']);
    }

    private function toCents(string $rand, string $field): int
    {
        $rand = str_replace([' ', ','], ['', '.'], trim($rand));
        if (! is_numeric($rand)) {
            throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Enter an amount in rand, for example 299.00.']);
        }

        return (int) round(((float) $rand) * 100);
    }

    public function render(OrderControls $controls)
    {
        $order = Order::findOrFail($this->orderId);

        return view('livewire.admin.order-controls-panel', [
            'order' => $order,
            'discounts' => OrderDiscount::where('order_id', $order->id)->orderByDesc('id')->get(),
            'current' => $controls->activeDiscount($order),
            'off' => $controls->discountCents($order),
            'audit' => OrderAuditEvent::with('user')->where('order_id', $order->id)->orderByDesc('id')->limit(50)->get(),
        ]);
    }
}
