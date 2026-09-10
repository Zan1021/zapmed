<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparImportBatch;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
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
        return SparOrder::with($this->patientWith(['pharmacy']))
            ->latest()
            ->limit(10)
            ->get();
    }

    public function getRenewalsDueProperty()
    {
        return SparPrescriptionJourney::renewalDue()
            ->with($this->patientWith(['pharmacy']))
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

    /**
     * Eager-load list including `patient.user` only when the model defines it.
     *
     * @param  array<int, string>  $extra
     * @return array<int, string>
     */
    private function patientWith(array $extra = []): array
    {
        $patientLoad = method_exists(SparPatient::class, 'user') ? 'patient.user' : 'patient';

        return array_merge([$patientLoad], $extra);
    }

    public function render()
    {
        return view('spar::livewire.admin.spar-dashboard')
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
