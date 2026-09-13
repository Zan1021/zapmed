<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Mail\Mailable as LaravelMailable;

/**
 * A channel-agnostic description of one message to send. The dispatcher builds
 * this once; each channel reads the parts it needs (email uses the Mailable or
 * subject+body; SMS/WhatsApp use the plain-text body).
 */
class OutboundMessage
{
    public function __construct(
        public readonly string $templateKey,          // e.g. 'payment.received'
        public readonly string $category,             // 'transactional' | 'marketing'
        public readonly ?User $user,                  // recipient user (for preference checks/logging)
        public readonly ?string $email = null,        // explicit email (falls back to user->email)
        public readonly ?string $phone = null,        // explicit phone (falls back to user phone)
        public readonly ?string $subject = null,      // email subject (raw path)
        public readonly ?string $body = null,         // plain-text body (SMS/WhatsApp + raw email)
        public readonly ?LaravelMailable $mailable = null, // optional rich Mailable for email
        public readonly array $meta = [],             // logged as-is (appointment_id, etc.)
    ) {}

    public function resolvedEmail(): ?string
    {
        return $this->email ?? $this->user?->email;
    }
}
