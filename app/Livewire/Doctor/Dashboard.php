<?php

namespace App\Livewire\Doctor;

use App\Models\Appointment;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function startConsultation(int $appointmentId): void
    {
        $appointment = Appointment::where('doctor_id', Auth::id())
            ->findOrFail($appointmentId);

        $appointment->update(['status' => 'in_progress']);
    }

    public function completeConsultation(int $appointmentId): void
    {
        $appointment = Appointment::where('doctor_id', Auth::id())
            ->findOrFail($appointmentId);

        $appointment->update(['status' => 'completed']);
    }

    public function confirmAppointment(int $appointmentId): void
    {
        $appointment = Appointment::where('doctor_id', Auth::id())
            ->findOrFail($appointmentId);

        $appointment->update(['status' => 'confirmed']);
    }

    public function cancelAppointment(int $appointmentId): void
    {
        $appointment = Appointment::where('doctor_id', Auth::id())
            ->findOrFail($appointmentId);

        if ($appointment->canBeCancelled()) {
            $appointment->cancel(Auth::id(), 'Cancelled by doctor');
        }
    }

    public function getTodayAppointmentsProperty()
    {
        return Appointment::where('doctor_id', Auth::id())
            ->today()
            ->with('patient')
            ->orderBy('start_time')
            ->get();
    }

    public function getUpcomingAppointmentsProperty()
    {
        return Appointment::where('doctor_id', Auth::id())
            ->where('appointment_date', '>', today())
            ->whereIn('status', ['pending', 'confirmed'])
            ->with('patient')
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->limit(10)
            ->get();
    }

    public function getStatsProperty(): array
    {
        $doctorId = Auth::id();

        return [
            'today_total' => Appointment::where('doctor_id', $doctorId)->today()->count(),
            'today_completed' => Appointment::where('doctor_id', $doctorId)->today()->where('status', 'completed')->count(),
            'today_remaining' => Appointment::where('doctor_id', $doctorId)->today()->whereIn('status', ['pending', 'confirmed'])->count(),
            'week_total' => Appointment::where('doctor_id', $doctorId)
                ->whereBetween('appointment_date', [now()->startOfWeek(), now()->endOfWeek()])
                ->whereNotIn('status', ['cancelled'])
                ->count(),
            'total_patients' => Appointment::where('doctor_id', $doctorId)
                ->where('status', 'completed')
                ->distinct('patient_id')
                ->count('patient_id'),
            'pending_actions' => Appointment::where('doctor_id', $doctorId)
                ->where('status', 'pending')
                ->count(),
        ];
    }

    public function render()
    {
        return view('livewire.doctor.dashboard')
            ->layout('layouts.app');
    }
}
