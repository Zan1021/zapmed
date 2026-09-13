<?php

namespace App\Services\Notifications;

/**
 * Outcome of a single channel send attempt. Returned by every NotificationChannel
 * so the dispatcher can write an accurate NotificationLog row.
 */
class ChannelResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $provider,
        public readonly ?string $providerRef = null,
        public readonly ?string $error = null,
    ) {}

    public static function sent(string $provider, ?string $providerRef = null): self
    {
        return new self(true, $provider, $providerRef, null);
    }

    public static function failed(string $provider, string $error): self
    {
        return new self(false, $provider, null, $error);
    }
}
