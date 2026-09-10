<?php

namespace Zapmed\SparCore\Contracts;

use Zapmed\SparCore\Models\SparPatient;

/**
 * A single delivery channel for SPAR patient messaging (spec FR-5.1, FR-12).
 *
 * Implementations: SmsChannel (fallback, available now), EmailChannel,
 * InAppChannel, and later WhatsAppChannel (primary, deferred). The
 * MessagingDispatcher tries channels in the configured priority order
 * (`spar.channels`) until one reports success.
 */
interface MessagingChannel
{
    /**
     * Machine key for this channel: 'whatsapp' | 'sms' | 'email' | 'inapp'.
     */
    public function key(): string;

    /**
     * Whether this channel can currently reach the given patient
     * (has the required contact detail AND is configured to send).
     */
    public function canReach(SparPatient $patient): bool;

    /**
     * Attempt to deliver a message to the patient.
     *
     * @param  array  $payload  ['subject' => ?string, 'body' => string,
     *                           'template' => ?string, 'vars' => array,
     *                           'link' => ?string]
     * @return bool  True on successful hand-off to the channel.
     */
    public function send(SparPatient $patient, array $payload): bool;
}
