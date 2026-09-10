<?php

namespace Zapmed\SparCore\Livewire;

use Zapmed\SparCore\Concerns\LogsSparActivity;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Livewire\Component;

class PharmacyDashboard extends Component
{
    use LogsSparActivity;

    /**
     * Super-admins and group-admins are NOT scoped to a single pharmacy, so the
     * store-level dashboard has nothing to render for them. Send them to the
     * admin dashboard (platform/group overview) instead of the "not linked to a
     * pharmacy" notice. Pharmacy-admins and pharmacy-staff stay here.
     *
     * Safe for both hosts: the redirect only fires for admin roles, and the
     * target route name (admin.spar.dashboard) is registered by the same
     * package routes file in every host.
     */
    public function mount()
    {
        $identity = app(SparIdentityProvider::class);

        if ($identity->isSuperAdmin() || $identity->currentRole() === 'group_admin') {
            return $this->redirectRoute('admin.spar.dashboard', navigate: false);
        }

        return null;
    }

    /**
     * The pharmacy scope for the current staff actor, resolved via the host's
     * identity provider (never off App\Models\User directly).
     */
    public function getPharmacyIdProperty(): ?int
    {
        return app(SparIdentityProvider::class)->currentPharmacyId();
    }

    public function getPharmacyProperty()
    {
        $id = $this->pharmacyId;

        return $id ? SparPharmacy::find($id) : null;
    }

    public function getStatsProperty(): array
    {
        $pharmacyId = $this->pharmacyId;

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
        if (!$this->pharmacyId) return collect();

        return SparOrder::forPharmacy($this->pharmacyId)
            ->pending()
            ->with($this->patientWith(['dispenseRecord']))
            ->orderBy('created_at')
            ->limit(20)
            ->get();
    }

    public function getReadyOrdersProperty()
    {
        if (!$this->pharmacyId) return collect();

        return SparOrder::forPharmacy($this->pharmacyId)
            ->where('status', 'ready')
            ->with($this->patientWith())
            ->orderBy('ready_at')
            ->get();
    }

    public function getUpcomingDispensesProperty()
    {
        if (!$this->pharmacyId) return collect();

        return SparDispenseRecord::whereHas('journey', fn ($q) => $q->where('spar_pharmacy_id', $this->pharmacyId))
            ->where('status', 'upcoming')
            ->where('due_date', '<=', now()->addDays(14))
            ->with($this->patientWith(['journey']))
            ->orderBy('due_date')
            ->limit(20)
            ->get();
    }

    public function startPreparing(int $orderId): void
    {
        $order = SparOrder::forPharmacy($this->pharmacyId)->findOrFail($orderId);
        $this->logOrderAction($orderId, 'start_preparing', $order->status, 'preparing');
        $order->markPreparing();
    }

    public function markReady(int $orderId): void
    {
        $order = SparOrder::forPharmacy($this->pharmacyId)->findOrFail($orderId);
        $this->logOrderAction($orderId, 'mark_ready', $order->status, 'ready');
        $order->markReady();
    }

    public function markCompleted(int $orderId): void
    {
        $order = SparOrder::forPharmacy($this->pharmacyId)->findOrFail($orderId);
        $this->logOrderAction($orderId, 'mark_completed', $order->status, 'completed');
        $order->markCompleted();
    }

    /**
     * Eager-load list including the integrated `patient.user` relation only
     * when the SparPatient model defines it (portable to standalone).
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
        return view('spar::livewire.pharmacy-dashboard')
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
