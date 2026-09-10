<?php

namespace Zapmed\SparCore\Livewire;

use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Services\SparPatientSession;
use Livewire\Component;

class MyMedsHistory extends Component
{
    /**
     * Resolve the session patient (no User login — spec FR-9), rolling up to
     * the primary member of the profile.
     */
    public function getSparPatientProperty(): ?SparPatient
    {
        $patient = app(SparPatientSession::class)->patient();
        if (!$patient) {
            return null;
        }

        if (!$patient->is_primary_member) {
            $primary = SparPatient::where('spar_pharmacy_id', $patient->spar_pharmacy_id)
                ->where('is_primary_member', true)
                ->get()
                ->first(fn (SparPatient $p) => $p->profile_code === $patient->profile_code);
            $patient = $primary ?? $patient;
        }

        return $patient;
    }

    public function getHistoryProperty()
    {
        $patient = $this->sparPatient;
        if (!$patient) {
            return collect();
        }

        // History across the whole profile (self + dependants, spec FR-8).
        // profile_code is encrypted, so match on the decrypted value in PHP.
        $memberIds = SparPatient::where('spar_pharmacy_id', $patient->spar_pharmacy_id)
            ->get()
            ->filter(fn (SparPatient $p) => $p->profile_code === $patient->profile_code)
            ->pluck('id');

        return SparDispenseRecord::whereIn('spar_patient_id', $memberIds)
            ->whereIn('status', ['collected', 'delivered'])
            ->with('journey.pharmacy')
            ->orderByDesc('completed_at')
            ->limit(50)
            ->get();
    }

    public function render()
    {
        return view('spar::livewire.my-meds-history')
            ->layout(config('spar.layouts.patient', 'layouts.spar-meds'));
    }
}
