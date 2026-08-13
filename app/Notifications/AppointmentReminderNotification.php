<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Appointment $appointment,
        private string $timing, // '24h', '1h', '15m'
        private string $recipientType, // 'patient' or 'doctor'
    ) {}

    public function via(object $notifiable): array
    {
        return match ($this->timing) {
            '24h' => ['mail'],
            '1h', '15m' => ['mail'], // SMS handled directly in command
            default => ['mail'],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment;
        $date = $appointment->appointment_date->format('l, j F Y');
        $time = substr($appointment->start_time, 0, 5);

        if ($this->recipientType === 'doctor') {
            return $this->buildDoctorEmail($appointment, $date, $time);
        }

        return $this->buildPatientEmail($appointment, $date, $time);
    }

    private function buildPatientEmail(Appointment $appointment, string $date, string $time): MailMessage
    {
        $doctorName = 'Dr. ' . $appointment->doctor->last_name;
        $commPref = match ($appointment->communication_preference) {
            'audio' => 'audio call',
            'text' => 'text chat',
            default => 'video call',
        };

        $message = (new MailMessage)
            ->subject("Reminder: Your consultation is tomorrow — {$time}")
            ->greeting("Hi {$appointment->patient->first_name},")
            ->line("This is a reminder that your **{$commPref}** consultation with **{$doctorName}** is scheduled for:")
            ->line("**{$date} at {$time}** ({$appointment->duration_minutes} minutes)")
            ->line('**To prepare:**')
            ->line('• Find a quiet, private space')
            ->line('• Make sure you have good internet/signal')
            ->line('• Have your ID or medical aid details ready')
            ->action('Go to Dashboard', url('/dashboard'))
            ->line('If you need to cancel, please do so at least 2 hours before your appointment.');

        if ($appointment->communication_preference !== 'text') {
            $message->line('• Test your camera/microphone beforehand');
        }

        return $message;
    }

    private function buildDoctorEmail(Appointment $appointment, string $date, string $time): MailMessage
    {
        $patientName = $appointment->patient->first_name . ' ' . $appointment->patient->last_name;
        $commPref = match ($appointment->communication_preference) {
            'audio' => 'Audio only',
            'text' => 'Text chat',
            default => 'Video',
        };

        return (new MailMessage)
            ->subject("Tomorrow: {$patientName} at {$time}")
            ->greeting("Good day, Dr. {$appointment->doctor->last_name}")
            ->line("Reminder of your consultation tomorrow:")
            ->line("**Patient:** {$patientName}")
            ->line("**Date:** {$date} at {$time}")
            ->line("**Duration:** {$appointment->duration_minutes} minutes")
            ->line("**Type:** {$appointment->type_label}")
            ->line("**Communication:** {$commPref}")
            ->line($appointment->reason ? "**Reason:** {$appointment->reason}" : '')
            ->action('View Schedule', url('/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'timing' => $this->timing,
            'type' => 'appointment_reminder',
        ];
    }
}
