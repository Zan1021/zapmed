<?php

namespace App\Livewire\Patient;

use App\Models\Appointment;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MyAppointments extends Component
{
    public string $filter = 'upcoming'; // upcoming, past, cancelled

    public function cancelAppointment(int $appointmentId): void
    {
        $appointment = Appointment::where('patient_id', Auth::id())
            ->findOrFail($appointmentId);

        if ($appointment->canBeCancelled()) {
            $appointment->cancel(Auth::id(), 'Cancelled by patient');
        }
    }

    public function getAppointmentsProperty()
    {
        $query = Appointment::where('patient_id', Auth::id())
            ->with(['doctor', 'doctor.doctorProfile', 'activeVideoSession']);

        return match ($this->filter) {
            'upcoming' => $query->upcoming()->get(),
            'past' => $query->where('status', 'completed')
                ->orderByDesc('appointment_date')->get(),
            'cancelled' => $query->where('status', 'cancelled')
                ->orderByDesc('appointment_date')->get(),
            default => $query->orderByDesc('appointment_date')->get(),
        };
    }

    public function render()
    {
        return view('livewire.patient.my-appointments')
            ->layout('layouts.app');
    }
}
