<?php

namespace Zapmed\SparCore\Services\Channels;

use Zapmed\SparCore\Contracts\MessagingChannel;
use Zapmed\SparCore\Models\SparPatient;
use Illuminate\Support\Facades\Log;

/**
 * In-app channel — records the message so it surfaces on the patient's mobile
 * web app (pull-based). It is NOT a push channel: on its own it cannot notify
 * a patient who isn't looking, so canReach() is false and the dispatcher will
 * still seek a push channel (SMS/email/WhatsApp). It records regardless via
 * recordAlways() so the renewal/reminder card always appears in-app.
 *
 * NOTE: a dedicated spar_notifications table is a later task; for now the
 * record is written to the spar_audit log channel so nothing is lost.
 */
class InAppChannel implements MessagingChannel
{
    public function key(): string
    {
        return 'inapp';
    }

    public function canReach(SparPatient $patient): bool
    {
        // Pull-based surface — does not actively reach the patient.
        return false;
    }

    public function send(SparPatient $patient, array $payload): bool
    {
        return $this->recordAlways($patient, $payload);
    }

    /**
     * Always record the in-app notification (companion to any push channel).
     */
    public function recordAlways(SparPatient $patient, array $payload): bool
    {
        Log::channel(config('logging.channels.spar_audit') ? 'spar_audit' : 'stack')->info(
            'SPAR in-app notification',
            [
                'spar_patient_id' => $patient->id,
                'subject' => $payload['subject'] ?? null,
                'body' => $payload['body'] ?? null,
                'link' => $payload['link'] ?? null,
            ]
        );

        return true;
    }
}
