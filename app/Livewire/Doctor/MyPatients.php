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
        // patients with an appointment plus the ones Operations assigned to this doctor
        $patientIds = app(\App\Services\Coach\CoachAccess::class)->doctorPatientIds(Auth::user());

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
        $patientIds = collect(app(\App\Services\Coach\CoachAccess::class)->doctorPatientIds(Auth::user()));

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
