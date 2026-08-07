<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Livewire\Component;

class Dashboard extends Component
{
    public function getStatsProperty(): array
    {
        return [
            'total_users' => User::count(),
            'total_patients' => User::role(UserRole::Patient)->count(),
            'total_doctors' => User::role(UserRole::Doctor)->count(),
            'active_users' => User::active()->count(),
            'appointments_today' => Appointment::today()->count(),
            'appointments_this_week' => Appointment::whereBetween('appointment_date', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'appointments_this_month' => Appointment::whereMonth('appointment_date', now()->month)->whereYear('appointment_date', now()->year)->count(),
            'pending_appointments' => Appointment::where('status', 'pending')->count(),
            'revenue_this_month' => Payment::where('status', 'completed')->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount'),
            'revenue_today' => Payment::where('status', 'completed')->whereDate('paid_at', today())->sum('amount'),
            'total_revenue' => Payment::where('status', 'completed')->sum('amount'),
            'pending_payments' => Payment::where('status', 'pending')->count(),
        ];
    }

    public function getRecentAppointmentsProperty()
    {
        return Appointment::with(['patient', 'doctor'])
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    public function getRecentPaymentsProperty()
    {
        return Payment::with(['patient', 'appointment'])
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    public function getRecentUsersProperty()
    {
        return User::latest('created_at')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.dashboard')
            ->layout('layouts.app');
    }
}
