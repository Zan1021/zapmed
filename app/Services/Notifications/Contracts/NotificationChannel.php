<?php

namespace App\Services\Notifications\Contracts;

use App\Services\Notifications\ChannelResult;
use App\Services\Notifications\OutboundMessage;

/**
 * A single delivery transport (email, SMS, WhatsApp, ...). Implementations are
 * resolved by NotificationDispatcher via the channel key.
 *
 * Design precedent: mirrors the SPAR MessagingChannel contract, but is
 * self-contained to the telehealth core and returns a rich ChannelResult so the
 * dispatcher can log provider refs and errors.
 */
interface NotificationChannel
{
    /** Channel key: email | sms | whatsapp. */
    public function key(): string;

    /** Is this channel configured to actually deliver (vs. log-only dev mode)? */
    public function isConfigured(): bool;

    /** Attempt delivery. Must not throw; wrap failures in ChannelResult::failed(). */
    public function send(OutboundMessage $message): ChannelResult;
}
