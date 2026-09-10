<?php

namespace Zapmed\SparCore\Livewire;

use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Services\SparPatientSession;
use Zapmed\SparCore\Services\SparPatientView;
use Livewire\Component;

class MyMedsHistory extends Component
{
    /**
     * Resolve the session patient (no User login — spec FR-9), rolling up to
     * the primary member of the profile via the shared SparPatientView.
     */
    public function getSparPatientProperty(): ?SparPatient
    {
        $patient = app(SparPatientSession::class)->patient();
        if (!$patient) {
            return null;
        }

        return app(SparPatientView::class)->primary($patient);
    }

    public function getHistoryProperty()
    {
        $patient = app(SparPatientSession::class)->patient();
        if (!$patient) {
            return collect();
        }

        // History across the whole profile (self + dependants, spec FR-8).
        return app(SparPatientView::class)->history($patient);
    }

    public function render()
    {
        return view('spar::livewire.my-meds-history')
            ->layout(config('spar.layouts.patient', 'layouts.spar-meds'));
    }
}
