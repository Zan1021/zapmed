<?php

namespace Zapmed\SparCore\Services\Channels;

use Zapmed\SparCore\Contracts\MessagingChannel;
use Zapmed\SparCore\Models\SparPatient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email delivery channel. Uses the app's configured mailer (Brevo in ZapMed).
 * A push channel — counts as "reaching" a patient when they have an email.
 */
class EmailChannel implements MessagingChannel
{
    public function key(): string
    {
        return 'email';
    }

    public function canReach(SparPatient $patient): bool
    {
        return !empty($patient->primaryEmail());
    }

    public function send(SparPatient $patient, array $payload): bool
    {
        $email = $patient->primaryEmail();
        if (empty($email)) {
            return false;
        }

        $subject = $payload['subject'] ?? (config('spar.branding.name', 'SPAR Pharmacy') . ' — Medication update');
        $body = $payload['body'] ?? '';
        if (!empty($payload['link'])) {
            $body = rtrim($body) . "\n\n" . $payload['link'];
        }

        try {
            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('SPAR email channel failed', [
                'spar_patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
