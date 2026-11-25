<?php

namespace App\Livewire\Doctor;

use App\Models\Prescription;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class MyPrescriptions extends Component
{
    use WithPagination;

    public string $filter = 'all';

    public function getPrescriptionsProperty()
    {
        $query = Prescription::where('doctor_id', Auth::id())
            ->whereNull('refill_of_id')   // a paid repeat is a copy for the pharmacy, not something the doctor signed again
            ->with(['patient', 'items'])
            ->orderByDesc('signed_at');

        if ($this->filter !== 'all') {
            $query->where('pharmacy_status', $this->filter);
        }

        return $query->paginate(15);
    }

    public function getStatsProperty(): array
    {
        $doctorId = Auth::id();
        return [
            'total' => Prescription::where('doctor_id', $doctorId)->whereNull('refill_of_id')->count(),
            'this_month' => Prescription::where('doctor_id', $doctorId)->whereNull('refill_of_id')->whereMonth('signed_at', now()->month)->count(),
            'chronic' => Prescription::where('doctor_id', $doctorId)->whereNull('refill_of_id')->where('is_chronic', true)->count(),
        ];
    }

    public function render()
    {
        return view('livewire.doctor.my-prescriptions')
            ->layout('layouts.app');
    }
}
