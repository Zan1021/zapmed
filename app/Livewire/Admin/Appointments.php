<?php

namespace App\Livewire\Admin;

use App\Models\Appointment;
use App\Models\User;
use App\Enums\UserRole;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Appointments extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $doctorFilter = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public string $sortBy = 'appointment_date';
    public string $sortDirection = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function cancelAppointment(int $appointmentId): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        if ($appointment->canBeCancelled()) {
            $appointment->cancel(auth()->id(), 'Cancelled by administrator');
            session()->flash('message', 'Appointment cancelled.');
        }
    }

    public function getDoctorsProperty()
    {
        return User::role(UserRole::Doctor)->orderBy('first_name')->get();
    }

    public function render()
    {
        $appointments = Appointment::query()
            ->with(['patient', 'doctor'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('reference', 'like', "%{$this->search}%")
                      ->orWhereHas('patient', function ($pq) {
                          $pq->where('first_name', 'like', "%{$this->search}%")
                             ->orWhere('last_name', 'like', "%{$this->search}%");
                      });
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->doctorFilter, function ($query) {
                $query->where('doctor_id', $this->doctorFilter);
            })
            ->when($this->dateFrom, function ($query) {
                $query->where('appointment_date', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->where('appointment_date', '<=', $this->dateTo);
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);

        return view('livewire.admin.appointments', [
            'appointments' => $appointments,
        ])->layout('layouts.app');
    }
}
