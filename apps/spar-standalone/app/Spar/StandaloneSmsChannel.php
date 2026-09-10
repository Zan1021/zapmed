<?php

namespace App\Spar;

use Zapmed\SparCore\Contracts\MessagingChannel;
use Zapmed\SparCore\Models\SparPatient;
use Illuminate\Support\Facades\Log;

/**
 * Standalone SMS channel (spec FR-12.2, design §5.1). The permanent fallback
 * text channel for the standalone deploy.
 *
 * PILOT NOTE: no dedicated SMS provider is provisioned for the standalone app
 * yet, so this logs the outbound SMS to the `spar_audit` channel (deliverable
 * record) and reports success. Swap the send() body for a real BulkSMS/WhatsApp
 * client when a provider is provisioned — no other code changes needed, since
 * everything routes through the MessagingChannel contract.
 */
class StandaloneSmsChannel implements MessagingChannel
{
    public function key(): string
    {
        return 'sms';
    }

    public function canReach(SparPatient $patient): bool
    {
        return !empty($patient->primaryPhone());
    }

    public function send(SparPatient $patient, array $payload): bool
    {
        $phone = $patient->primaryPhone();
        if (empty($phone)) {
            return false;
        }

        $body = $payload['body'] ?? '';
        if (!empty($payload['link'])) {
            $body = rtrim($body) . "\n" . $payload['link'];
        }

        Log::channel(config('logging.channels.spar_audit') ? 'spar_audit' : 'stack')->info(
            'SPAR standalone SMS (pilot: logged, no provider yet)',
            ['spar_patient_id' => $patient->id, 'phone' => $phone, 'body' => $body]
        );

        return true;
    }
}
