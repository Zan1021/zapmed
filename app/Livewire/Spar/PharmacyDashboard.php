<?php

namespace App\Livewire\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparOrder;
use App\Models\SparPatient;
use App\Models\SparPrescriptionJourney;
use App\Traits\LogsSparActivity;
use Livewire\Component;

class PharmacyDashboard extends Component
{
    use LogsSparActivity;
    public function getPharmacyProperty()
    {
        return auth()->user()->sparPharmacy;
    }

    public function getStatsProperty(): array
    {
        $pharmacyId = $this->pharmacy?->id;

        if (!$pharmacyId) return [];

        return [
            'active_patients' => SparPatient::forPharmacy($pharmacyId)->active()->count(),
            'pending_orders' => SparOrder::forPharmacy($pharmacyId)->pending()->count(),
            'ready_orders' => SparOrder::forPharmacy($pharmacyId)->where('status', 'ready')->count(),
            'active_journeys' => SparPrescriptionJourney::forPharmacy($pharmacyId)->active()->count(),
            'renewals_due' => SparPrescriptionJourney::forPharmacy($pharmacyId)->renewalDue()->count(),
            'upcoming_dispenses' => SparDispenseRecord::whereHas('journey', fn ($q) => $q->where('spar_pharmacy_id', $pharmacyId))
                ->where('status', 'upcoming')
                ->where('due_date', '<=', now()->addDays(7))
                ->count(),
        ];
    }

    public function getPendingOrdersProperty()
    {
        if (!$this->pharmacy) return collect();

        return SparOrder::forPharmacy($this->pharmacy->id)
            ->pending()
            ->with(['patient.user', 'dispenseRecord'])
            ->orderBy('created_at')
            ->limit(20)
            ->get();
    }

    public function getReadyOrdersProperty()
    {
        if (!$this->pharmacy) return collect();

        return SparOrder::forPharmacy($this->pharmacy->id)
            ->where('status', 'ready')
            ->with(['patient.user'])
            ->orderBy('ready_at')
            ->get();
    }

    public function getUpcomingDispensesProperty()
    {
        if (!$this->pharmacy) return collect();

        return SparDispenseRecord::whereHas('journey', fn ($q) => $q->where('spar_pharmacy_id', $this->pharmacy->id))
            ->where('status', 'upcoming')
            ->where('due_date', '<=', now()->addDays(14))
            ->with(['patient.user', 'journey'])
            ->orderBy('due_date')
            ->limit(20)
            ->get();
    }

    public function startPreparing(int $orderId): void
    {
        $order = SparOrder::forPharmacy($this->pharmacy->id)->findOrFail($orderId);
        $this->logOrderAction($orderId, 'start_preparing', $order->status, 'preparing');
        $order->markPreparing();
    }

    public function markReady(int $orderId): void
    {
        $order = SparOrder::forPharmacy($this->pharmacy->id)->findOrFail($orderId);
        $this->logOrderAction($orderId, 'mark_ready', $order->status, 'ready');
        $order->markReady();
    }

    public function markCompleted(int $orderId): void
    {
        $order = SparOrder::forPharmacy($this->pharmacy->id)->findOrFail($orderId);
        $this->logOrderAction($orderId, 'mark_completed', $order->status, 'completed');
        $order->markCompleted();
    }

    public function render()
    {
        return view('livewire.spar.pharmacy-dashboard')
            ->layout('layouts.app');
    }
}
