<?php

namespace App\Livewire\Admin;

use App\Models\Prescription;
use App\Services\PharmacyService;
use Livewire\Component;
use Livewire\WithPagination;

class PharmacyOrders extends Component
{
    use WithPagination;

    public string $filter = 'all'; // all, pending, dispatched, delivered, failed

    /**
     * Manually re-dispatch a failed prescription.
     */
    public function redispatch(int $prescriptionId): void
    {
        $prescription = Prescription::findOrFail($prescriptionId);

        if (!$prescription->isPaid()) {
            session()->flash('error', 'Cannot dispatch — prescription is not paid.');
            return;
        }

        $prescription->update(['pharmacy_status' => 'pending']);

        $pharmacy = app(PharmacyService::class);
        $result = $pharmacy->dispatch($prescription);

        if ($result['success']) {
            session()->flash('message', "Dispatched successfully. Ref: {$result['reference']}");
        } else {
            session()->flash('error', "Dispatch failed: {$result['message']}");
        }
    }

    /**
     * Mark a prescription as manually delivered (for phone confirmations etc).
     */
    public function markDelivered(int $prescriptionId): void
    {
        $prescription = Prescription::findOrFail($prescriptionId);
        $prescription->update([
            'pharmacy_status' => 'delivered',
            'pharmacy_response' => array_merge(
                $prescription->pharmacy_response ?? [],
                ['manual_delivery' => now()->toIso8601String()]
            ),
        ]);

        session()->flash('message', 'Marked as delivered.');
    }

    public function getOrdersProperty()
    {
        $query = Prescription::with(['patient', 'doctor', 'pharmacy'])
            ->whereNotNull('signed_at')
            ->orderByDesc('signed_at');

        if ($this->filter !== 'all') {
            $query->where('pharmacy_status', $this->filter);
        }

        return $query->paginate(20);
    }

    public function getStatsProperty(): array
    {
        return [
            'pending' => Prescription::where('pharmacy_status', 'pending')->whereNotNull('signed_at')->count(),
            'dispatched' => Prescription::where('pharmacy_status', 'dispatched')->count(),
            'in_transit' => Prescription::where('pharmacy_status', 'in_transit')->count(),
            'delivered' => Prescription::where('pharmacy_status', 'delivered')->count(),
            'failed' => Prescription::where('pharmacy_status', 'failed')->count(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.pharmacy-orders')
            ->layout('layouts.app');
    }
}
