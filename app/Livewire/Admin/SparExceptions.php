<?php

namespace App\Livewire\Admin;

use App\Models\SparDispenseRecord;
use App\Models\SparPrescriptionJourney;
use App\Services\SparReminderService;
use Livewire\Component;

class SparExceptions extends Component
{
    public string $filter = 'all';

    public function getOverdueDispensesProperty()
    {
        return SparDispenseRecord::overdue()
            ->with(['patient.user', 'journey.pharmacy'])
            ->orderBy('due_date')
            ->limit(50)
            ->get();
    }

    public function getMissingRenewalsProperty()
    {
        return SparPrescriptionJourney::where('status', 'renewal_due')
            ->where('renewal_due_date', '<', now()->subDays(7))
            ->with(['patient.user', 'pharmacy'])
            ->orderBy('renewal_due_date')
            ->limit(50)
            ->get();
    }

    public function getUnresponsivePatientsProperty()
    {
        $service = new SparReminderService();
        return $service->getUnresponsivePatients(10);
    }

    public function render()
    {
        return view('livewire.admin.spar-exceptions')
            ->layout('layouts.app');
    }
}
