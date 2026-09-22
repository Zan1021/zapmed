<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Livewire\Component;
use Zapmed\SparCore\Concerns\LogsSparActivity;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Services\SparOrderService;

/**
 * SPAR Close-the-Loop Wave C2 (FR-C2) — Pharmacy Orders dashboard.
 *
 * Staff-facing queue of patient-placed orders, scoped to the actor
 * (super/group/pharmacy) via SparOrder::visibleToCurrentActor(). Staff move an
 * order through its lifecycle (requested → preparing → ready → completed) using
 * the shared SparOrderService. Orders auto-drop from the queue when the
 * underlying dispense is reconciled on the next import (FR-B5), so there is no
 * manual "mark done" required for the common path — but staff can still process
 * and complete here for the demo flow.
 *
 * When an order becomes ready/completed the patient receives a consent-gated
 * "come collect" alert (C2b, handled in SparOrderService).
 *
 * Package-pure: no host User/Prescription references; author identity duck-typed.
 */
class SparOrders extends Component
{
    use LogsSparActivity;

    public string $filter = 'active'; // active | ready | completed | all

    public ?string $actionNotice = null;

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['active', 'ready', 'completed', 'all'], true)
            ? $filter
            : 'active';
    }

    public function getOrdersProperty()
    {
        $query = SparOrder::query()
            ->visibleToCurrentActor()
            ->with(array_merge($this->patientWith(), ['pharmacy', 'items']));

        $query = match ($this->filter) {
            'active' => $query->whereIn('status', ['requested', 'preparing']),
            'ready' => $query->where('status', 'ready'),
            'completed' => $query->where('status', 'completed'),
            default => $query,
        };

        return $query->orderByDesc('created_at')->limit(100)->get();
    }

    /** Header counts for the filter tabs (scoped). */
    public function getCountsProperty(): array
    {
        $base = fn () => SparOrder::query()->visibleToCurrentActor();

        return [
            'active' => (clone $base())->whereIn('status', ['requested', 'preparing'])->count(),
            'ready' => (clone $base())->where('status', 'ready')->count(),
            'completed' => (clone $base())->where('status', 'completed')->count(),
        ];
    }

    /* ---- lifecycle actions (scope-guarded) -------------------------------- */

    public function startPreparing(int $orderId): void
    {
        if (! $order = $this->resolveOrder($orderId)) {
            return;
        }

        app(SparOrderService::class)->startPreparing($order);
        $this->audit('order_preparing', $order);
        $this->actionNotice = "Order {$order->reference} is now being prepared.";
    }

    public function markReady(int $orderId): void
    {
        if (! $order = $this->resolveOrder($orderId)) {
            return;
        }

        app(SparOrderService::class)->markReady($order);
        $this->audit('order_ready', $order);
        $this->actionNotice = "Order {$order->reference} is ready — the patient has been notified.";
    }

    public function completeOrder(int $orderId): void
    {
        if (! $order = $this->resolveOrder($orderId)) {
            return;
        }

        app(SparOrderService::class)->completeOrder($order);
        $this->audit('order_completed', $order);
        $this->actionNotice = "Order {$order->reference} completed.";
    }

    public function cancelOrder(int $orderId): void
    {
        if (! $order = $this->resolveOrder($orderId)) {
            return;
        }

        app(SparOrderService::class)->cancelOrder($order, 'Cancelled by pharmacy staff');
        $this->audit('order_cancelled', $order);
        $this->actionNotice = "Order {$order->reference} cancelled.";
    }

    /* ---- internals -------------------------------------------------------- */

    /**
     * Resolve an order by id, but ONLY within the current actor's scope. A
     * crafted id for another pharmacy's order resolves to null (fail-closed),
     * so staff can never process an out-of-scope order.
     */
    private function resolveOrder(int $orderId): ?SparOrder
    {
        $order = SparOrder::query()->visibleToCurrentActor()->find($orderId);

        if (! $order) {
            $this->actionNotice = 'That order is not available.';
        }

        return $order;
    }

    private function audit(string $event, SparOrder $order): void
    {
        $this->logSparActivity($event, 'Order lifecycle action by staff', [
            'order' => $order->reference,
            'spar_pharmacy_id' => $order->spar_pharmacy_id,
            'status' => $order->status,
        ]);
    }

    /**
     * Eager-load `patient.user` only when the model defines it (integrated host
     * subclass); the package base SparPatient has no telehealth user() relation.
     *
     * @return array<int, string>
     */
    private function patientWith(): array
    {
        return [method_exists(SparPatient::class, 'user') ? 'patient.user' : 'patient'];
    }

    public function render()
    {
        return view('spar::livewire.admin.spar-orders')
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
