<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparReminderService;
use Livewire\Component;

class SparExceptions extends Component
{
    public string $filter = 'all';

    public function getOverdueDispensesProperty()
    {
        return SparDispenseRecord::overdue()
            ->with($this->patientWith(['journey.pharmacy']))
            ->orderBy('due_date')
            ->limit(50)
            ->get();
    }

    public function getMissingRenewalsProperty()
    {
        return SparPrescriptionJourney::where('status', 'renewal_due')
            ->where('renewal_due_date', '<', now()->subDays(7))
            ->with($this->patientWith(['pharmacy']))
            ->orderBy('renewal_due_date')
            ->limit(50)
            ->get();
    }

    public function getUnresponsivePatientsProperty()
    {
        $service = new SparReminderService();
        return $service->getUnresponsivePatients(10);
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
        return view('spar::livewire.admin.spar-exceptions')
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
