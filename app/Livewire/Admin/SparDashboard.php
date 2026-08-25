<?php

namespace App\Livewire\Admin;

use App\Models\SparDispenseRecord;
use App\Models\SparImportBatch;
use App\Models\SparOrder;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Livewire\Component;

class SparDashboard extends Component
{
    public function getStatsProperty(): array
    {
        return [
            'total_pharmacies' => SparPharmacy::active()->count(),
            'total_patients' => SparPatient::active()->count(),
            'consented_patients' => SparPatient::consented()->count(),
            'active_journeys' => SparPrescriptionJourney::active()->count(),
            'renewal_due' => SparPrescriptionJourney::renewalDue()->count(),
            'pending_orders' => SparOrder::pending()->count(),
            'orders_today' => SparOrder::whereDate('created_at', today())->count(),
            'overdue_dispenses' => SparDispenseRecord::overdue()->count(),
            'last_import' => SparImportBatch::latest()->first()?->completed_at?->diffForHumans() ?? 'Never',
        ];
    }

    public function getRecentOrdersProperty()
    {
        return SparOrder::with(['patient.user', 'pharmacy'])
            ->latest()
            ->limit(10)
            ->get();
    }

    public function getRenewalsDueProperty()
    {
        return SparPrescriptionJourney::renewalDue()
            ->with(['patient.user', 'pharmacy'])
            ->latest('renewal_due_date')
            ->limit(10)
            ->get();
    }

    public function getPharmacySummaryProperty()
    {
        return SparPharmacy::active()
            ->withCount([
                'patients as active_patients_count' => fn ($q) => $q->where('is_active', true),
                'orders as pending_orders_count' => fn ($q) => $q->whereIn('status', ['requested', 'preparing']),
            ])
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.spar-dashboard')
            ->layout('layouts.app');
    }
}
