<?php

namespace App\Console\Commands;

use App\Mail\AppointmentReminder;
use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Send reminder emails for confirmed appointments scheduled for tomorrow';

    public function handle(): int
    {
        $appointments = Appointment::where('status', 'confirmed')
            ->whereDate('appointment_date', now()->addDay()->toDateString())
            ->with(['patient', 'doctor'])
            ->get();

        $count = 0;

        foreach ($appointments as $appointment) {
            Mail::to($appointment->patient)->queue(new AppointmentReminder($appointment));
            $count++;
        }

        $this->info("Sent {$count} appointment reminder(s).");

        return self::SUCCESS;
    }
}
