<?php

namespace App\Mail;

use App\Models\Prescription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrescriptionRefillReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Prescription $prescription,
        public int $daysRemaining
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your medication is running low — reorder on Zapmed",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.prescription-refill-reminder',
        );
    }
}
