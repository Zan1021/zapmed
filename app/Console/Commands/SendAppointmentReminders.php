<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentReminderNotification;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Send appointment reminders: email at 24h, SMS at 1h and 15min';

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        // ─── 24 HOUR REMINDERS (Email to patient + doctor) ───────────────
        $sent += $this->send24hReminders($now);

        // ─── 1 HOUR REMINDERS (SMS to patient + doctor) ──────────────────
        $sent += $this->send1hReminders($now);

        // ─── 15 MINUTE REMINDERS (SMS to patient only) ───────────────────
        $sent += $this->send15mReminders($now);

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }

    private function send24hReminders(Carbon $now): int
    {
        $tomorrow = $now->copy()->addHours(24);

        $appointments = Appointment::query()
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereNull('reminder_24h_sent_at')
            ->whereDate('appointment_date', $tomorrow->toDateString())
            ->with(['patient', 'doctor'])
            ->get();

        $count = 0;

        foreach ($appointments as $appointment) {
            // Email to patient
            $appointment->patient->notify(
                new AppointmentReminderNotification($appointment, '24h', 'patient')
            );

            // Email to doctor
            $appointment->doctor->notify(
                new AppointmentReminderNotification($appointment, '24h', 'doctor')
            );

            $appointment->update(['reminder_24h_sent_at' => now()]);
            $count += 2;
        }

        return $count;
    }

    private function send1hReminders(Carbon $now): int
    {
        $appointments = $this->getAppointmentsWithinWindow($now, 60, 'reminder_1h_sent_at');
        $sms = app(SmsService::class);
        $count = 0;

        foreach ($appointments as $appointment) {
            $time = substr($appointment->start_time, 0, 5);

            // SMS to patient
            if ($appointment->patient->phone) {
                $sms->send(
                    $appointment->patient->phone,
                    "Reminder: Your Zapmed consultation is in 1 hour ({$time}). Be ready! Your doctor will connect with you shortly. — Zapmed"
                );
                $count++;
            }

            // SMS to doctor
            if ($appointment->doctor->phone) {
                $patientName = $appointment->patient->first_name . ' ' . $appointment->patient->last_name;
                $sms->send(
                    $appointment->doctor->phone,
                    "Reminder: You have a consultation with {$patientName} in 1 hour ({$time}). — Zapmed"
                );
                $count++;
            }

            $appointment->update(['reminder_1h_sent_at' => now()]);
        }

        return $count;
    }

    private function send15mReminders(Carbon $now): int
    {
        $appointments = $this->getAppointmentsWithinWindow($now, 15, 'reminder_15m_sent_at');
        $sms = app(SmsService::class);
        $count = 0;

        foreach ($appointments as $appointment) {
            // SMS to patient only — doctor doesn't need another ping
            if ($appointment->patient->phone) {
                $sms->send(
                    $appointment->patient->phone,
                    "Your Zapmed consultation starts in 15 minutes! Make sure you're in a quiet space with good signal. — Zapmed"
                );
                $count++;
            }

            $appointment->update(['reminder_15m_sent_at' => now()]);
        }

        return $count;
    }

    /**
     * Get appointments that start within X minutes from now and haven't been reminded yet.
     */
    private function getAppointmentsWithinWindow(Carbon $now, int $minutesBefore, string $sentColumn)
    {
        $targetTime = $now->copy()->addMinutes($minutesBefore);

        return Appointment::query()
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereNull($sentColumn)
            ->whereDate('appointment_date', $now->toDateString())
            ->whereTime('start_time', '<=', $targetTime->format('H:i:s'))
            ->whereTime('start_time', '>', $now->format('H:i:s'))
            ->with(['patient', 'doctor'])
            ->get();
    }
}
