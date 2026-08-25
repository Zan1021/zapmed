<?php

namespace App\Livewire\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparPatient;
use Livewire\Component;

class MyMedsHistory extends Component
{
    public function getSparPatientProperty(): ?SparPatient
    {
        return SparPatient::where('user_id', auth()->id())
            ->where('is_active', true)
            ->first();
    }

    public function getHistoryProperty()
    {
        if (!$this->sparPatient) return collect();

        return SparDispenseRecord::where('spar_patient_id', $this->sparPatient->id)
            ->whereIn('status', ['collected', 'delivered'])
            ->with('journey')
            ->orderByDesc('completed_at')
            ->limit(50)
            ->get();
    }

    public function render()
    {
        return view('livewire.spar.my-meds-history')
            ->layout('layouts.spar-meds');
    }
}
