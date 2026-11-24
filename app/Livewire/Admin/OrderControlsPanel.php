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

    // repeat reset
    public string $resetReason = '';

    // courier tracking
    public string $trackNumber = '';
    public string $trackStatus = 'in_transit';
    public string $trackCourier = '';
    public string $trackUrl = '';
    public string $trackPharmacyRef = '';

    // add-ons
    public string $addonId = '';
    public string $addonReason = '';

    // care team
    public string $staffId = '';
    public string $teamNote = '';

    public function assignStaff(\App\Services\Operations\StaffAssignments $team): void
    {
        $this->resetErrorBag();
        $order = Order::with('patient')->findOrFail($this->orderId);
        if ($this->staffId === '') {
            $this->addError('staff', 'Choose a doctor or health coach first.');

            return;
        }

        $team->assign(Auth::user(), $order->patient, \App\Models\User::findOrFail((int) $this->staffId), $this->teamNote);
        $this->reset(['staffId', 'teamNote']);
    }

    public function endAssignment(int $id, \App\Services\Operations\StaffAssignments $team): void
    {
        $this->resetErrorBag();
        $order = Order::findOrFail($this->orderId);

        $team->end(Auth::user(), \App\Models\StaffAssignment::where('patient_id', $order->patient_id)->findOrFail($id));
    }

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

    public function resetRepeat(OrderControls $controls): void
    {
        $this->resetErrorBag();
        $controls->resetRepeat(Auth::user(), Order::findOrFail($this->orderId), $this->resetReason);
        $this->reset('resetReason');
    }

    public function addAddon(\App\Services\Operations\Addons $addons): void
    {
        $this->resetErrorBag();
        $order = Order::findOrFail($this->orderId);
        if ($this->addonId === '') {
            $this->addError('addon', 'Choose an add-on first.');

            return;
        }

        $addons->add(Auth::user(), $order, \App\Models\Addon::findOrFail((int) $this->addonId));
        $this->reset('addonId');
    }

    public function changeAddon(int $orderAddonId, string $to, \App\Services\Operations\Addons $addons): void
    {
        $this->resetErrorBag();
        $oa = \App\Models\OrderAddon::where('order_id', $this->orderId)->findOrFail($orderAddonId);

        $addons->change(Auth::user(), $oa, $to, $this->addonReason);
        $this->reset('addonReason');
    }

    public function recordTracking(\App\Services\Operations\OrderTrackingService $tracking): void
    {
        $this->resetErrorBag();
        $tracking->record(Auth::user(), Order::findOrFail($this->orderId), $this->trackNumber, $this->trackStatus, $this->trackCourier, $this->trackUrl, $this->trackPharmacyRef);
        $this->reset(['trackNumber', 'trackUrl', 'trackPharmacyRef']);
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
            'isRepeat' => $order->source_type === 'subscription',
            'nextRepeat' => \App\Models\SubscriptionCycle::where('subscription_id', (int) $order->source_ref)->where('status', 'scheduled')->when($order->source_type !== 'subscription', fn ($q) => $q->whereRaw('1 = 0'))->orderBy('scheduled_for')->first()?->scheduled_for?->setTimezone(config('doctor.time.timezone'))->format('j M Y'),
            'careTeam' => app(\App\Services\Operations\StaffAssignments::class)->historyFor($order->patient),
            'assignableStaff' => app(\App\Services\Operations\StaffAssignments::class)->assignable(),
            'offeredAddons' => app(\App\Services\Operations\Addons::class)->offeredFor($order),
            'orderAddons' => \App\Models\OrderAddon::with('addon')->where('order_id', $order->id)->orderByDesc('id')->get(),
            'tracking' => app(\App\Services\Operations\OrderTrackingService::class)->latest($order),
            'trackingStatuses' => config('operations.tracking.statuses'),
            'audit' => OrderAuditEvent::with('user')->where('order_id', $order->id)->orderByDesc('id')->limit(50)->get(),
        ]);
    }
}
