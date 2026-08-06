<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewAppointmentForDoctor extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Appointment $appointment
    ) {
        $this->appointment->loadMissing(['patient']);
    }

    public function envelope(): Envelope
    {
        $patientName = $this->appointment->patient->name;
        $date = $this->appointment->appointment_date->format('d M Y');

        return new Envelope(
            subject: "New Appointment — {$patientName} on {$date}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-appointment-doctor',
        );
    }
}
