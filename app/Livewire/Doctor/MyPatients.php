<?php

namespace App\Livewire\Doctor;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class MyPatients extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function getPatientsProperty()
    {
        $patientIds = Appointment::where('doctor_id', Auth::id())
            ->distinct()
            ->pluck('patient_id');

        $query = User::whereIn('id', $patientIds)
            ->with('patientProfile');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                  ->orWhere('last_name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        return $query->orderBy('first_name')->paginate(15);
    }

    public function getStatsProperty(): array
    {
        $doctorId = Auth::id();
        $patientIds = Appointment::where('doctor_id', $doctorId)->distinct()->pluck('patient_id');

        return [
            'total' => $patientIds->count(),
            'this_month' => Appointment::where('doctor_id', $doctorId)
                ->whereMonth('created_at', now()->month)
                ->distinct('patient_id')
                ->count('patient_id'),
        ];
    }

    public function render()
    {
        return view('livewire.doctor.my-patients')
            ->layout('layouts.app');
    }
}
