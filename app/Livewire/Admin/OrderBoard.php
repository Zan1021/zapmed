<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderStatusMachine;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Order Board / Kanban (Task 2, specs/contro-rebuild/08-crm-build-spec §2.2).
 *
 * The headline ops screen: a read-first Kanban over the `orders` aggregate, grouped into swimlanes
 * (config('orders.board_lanes')) that collapse the 25-status machine into ops-meaningful columns.
 *
 * Filters: service line (orders.service_category), assignee (the assigned doctor — the only
 * assignee-like field on an order), and an ordered_at date window.
 *
 * Status changes are NOT free-form: the detail drawer only offers transitions that
 * OrderStatusMachine::allowedNextStatuses() permits, and applies them through the guarded, audited
 * Order::transitionTo() — never a raw status write. Everything else on the board is read-only.
 */
class OrderBoard extends Component
{
    /** Filters (URL-bound so a board view is shareable/bookmarkable). */
    public string $serviceLine = '';
    public string $assignee = '';          // doctor user id, as string
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $search = '';

    /** Currently opened order (detail drawer), or null. */
    public ?int $selectedOrderId = null;

    /** Chosen target status in the drawer's transition control. */
    public string $transitionTo = '';
    public string $transitionNotes = '';

    public function updated(string $property): void
    {
        // Any filter change closes the drawer to avoid showing a stale, filtered-out order.
        if (in_array($property, ['serviceLine', 'assignee', 'dateFrom', 'dateTo', 'search'], true)) {
            $this->selectedOrderId = null;
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['serviceLine', 'assignee', 'dateFrom', 'dateTo', 'search', 'selectedOrderId']);
    }

    public function selectOrder(int $orderId): void
    {
        $this->selectedOrderId = $orderId;
        $this->reset(['transitionTo', 'transitionNotes']);
    }

    public function closeDrawer(): void
    {
        $this->reset(['selectedOrderId', 'transitionTo', 'transitionNotes']);
    }

    /**
     * Apply a guarded status transition through the aggregate. The picker only ever offers legal
     * targets, but we re-validate here (the trigger is derived, and the machine is the source of truth)
     * so a stale/tampered request can never force an illegal move.
     */
    public function applyTransition(): void
    {
        $order = Order::find($this->selectedOrderId);

        if (! $order) {
            session()->flash('error', 'Order no longer exists.');
            $this->closeDrawer();
            return;
        }

        $this->validate([
            'transitionTo' => 'required|string',
            'transitionNotes' => 'nullable|string|max:1000',
        ]);

        // Resolve the trigger for this (from -> to) pair from the state-machine rules. An admin acting
        // on the board is an 'admin' trigger when the rules allow it; otherwise fall back to the single
        // trigger the rules define for the pair. If none exists, the move is illegal.
        $trigger = $this->resolveTrigger($order->status, $this->transitionTo);

        if ($trigger === null) {
            session()->flash('error', "Illegal transition {$order->status} → {$this->transitionTo}.");
            return;
        }

        try {
            $order->transitionTo($this->transitionTo, $trigger, [
                'triggered_by' => 'admin:' . auth()->id(),
                'notes' => $this->transitionNotes ?: null,
            ]);
            session()->flash('message', "Order {$order->reference} moved to {$this->transitionTo}.");
            $this->reset(['transitionTo', 'transitionNotes']);
        } catch (\Throwable $e) {
            session()->flash('error', 'Transition failed: ' . $e->getMessage());
        }
    }

    /**
     * Pick the trigger for a (from -> to) pair. Prefer 'admin' if the rules allow it (manual board
     * action), else the sole trigger defined for that pair. Null if the pair is not permitted at all.
     */
    private function resolveTrigger(string $from, string $to): ?string
    {
        if (OrderStatusMachine::isTransitionAllowed($from, $to, 'admin')) {
            return 'admin';
        }

        foreach (config('orders.transitions', []) as [$f, $t, $trg]) {
            if ($f === $from && $t === $to) {
                return $trg;
            }
        }

        return null;
    }

    /** Base query with the active filters applied. Reused by the lanes + counts. */
    private function filteredQuery()
    {
        return Order::query()
            ->with(['patient:id,first_name,last_name', 'doctor:id,first_name,last_name'])
            ->when($this->serviceLine !== '', fn ($q) => $q->where('service_category', $this->serviceLine))
            ->when($this->assignee !== '', fn ($q) => $q->where('doctor_id', (int) $this->assignee))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('ordered_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('ordered_at', '<=', $this->dateTo))
            ->when($this->search !== '', function ($q) {
                $term = trim($this->search);
                $q->where(function ($inner) use ($term) {
                    $inner->where('reference', 'like', "%{$term}%")
                        ->orWhere('contro_order_number', 'like', "%{$term}%")
                        ->orWhereHas('patient', function ($p) use ($term) {
                            $p->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%");
                        });
                });
            });
    }

    /**
     * Orders grouped into board lanes. Each lane holds a bounded, most-recent slice so the board can
     * never try to render thousands of cards; the true per-lane total comes from laneCounts().
     * @return array<string,Collection>
     */
    #[Computed]
    public function lanes(): array
    {
        $lanes = OrderStatusMachine::boardLanes();
        $out = [];

        foreach ($lanes as $key => $lane) {
            $out[$key] = $this->filteredQuery()
                ->whereIn('status', $lane['statuses'])
                ->latest('ordered_at')
                ->limit(50)
                ->get();
        }

        return $out;
    }

    /**
     * Exact order count per lane (independent of the render cap above).
     * @return array<string,int>
     */
    #[Computed]
    public function laneCounts(): array
    {
        $counts = [];
        foreach (OrderStatusMachine::boardLanes() as $key => $lane) {
            $counts[$key] = $this->filteredQuery()
                ->whereIn('status', $lane['statuses'])
                ->count();
        }

        return $counts;
    }

    /** The order shown in the detail drawer, eager-loaded, or null. */
    #[Computed]
    public function selectedOrder(): ?Order
    {
        if ($this->selectedOrderId === null) {
            return null;
        }

        return Order::with([
            'patient:id,first_name,last_name,email',
            'doctor:id,first_name,last_name',
            'items',
            'statusHistory',
            'payments',
            'prescriptions',
        ])->find($this->selectedOrderId);
    }

    /** Legal next statuses for the drawer's picker (data-driven, no free-form writes). */
    #[Computed]
    public function allowedNextStatuses(): array
    {
        $order = $this->selectedOrder();

        return $order ? OrderStatusMachine::allowedNextStatuses($order->status) : [];
    }

    /** Doctors, for the assignee filter dropdown. */
    #[Computed]
    public function assignableDoctors(): Collection
    {
        return User::query()
            ->where('role', \App\Enums\UserRole::Doctor->value)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);
    }

    /** Distinct service categories present on orders, for the service-line filter. */
    #[Computed]
    public function serviceLines(): Collection
    {
        return Order::query()
            ->whereNotNull('service_category')
            ->distinct()
            ->orderBy('service_category')
            ->pluck('service_category');
    }

    public function render()
    {
        return view('livewire.admin.order-board', [
            'laneDefinitions' => OrderStatusMachine::boardLanes(),
        ]);
    }
}
