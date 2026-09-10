<?php

namespace Zapmed\SparCore\Contracts;

/**
 * Delivers a short numeric OTP to a phone number for SPAR patient link
 * re-verification (spec FR-9). This is a low-level transport seam distinct from
 * the consent-gated MessagingChannel/dispatcher: OTPs are sent BEFORE consent
 * is granted, so they must not route through the consent-gated dispatcher.
 *
 * Host binds the concrete transport:
 *   - ZapMed (integrated): wraps the existing SmsService (BulkSMS/Clickatell).
 *   - Standalone: its own SMS provider.
 */
interface OtpSender
{
    /**
     * Send the OTP code to the given phone number. Returns true on hand-off.
     */
    public function sendOtp(string $phone, string $code): bool;
}
