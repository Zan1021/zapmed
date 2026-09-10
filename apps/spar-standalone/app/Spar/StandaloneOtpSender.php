<?php

namespace App\Spar;

use Zapmed\SparCore\Contracts\OtpSender;
use Illuminate\Support\Facades\Log;

/**
 * Standalone OTP transport (spec FR-9). Sends the patient link re-verification
 * code. PILOT NOTE: logs the OTP (no SMS provider provisioned yet); swap for a
 * real client when provisioned.
 */
class StandaloneOtpSender implements OtpSender
{
    public function sendOtp(string $phone, string $code): bool
    {
        Log::channel(config('logging.channels.spar_audit') ? 'spar_audit' : 'stack')->info(
            'SPAR standalone OTP (pilot: logged, no provider yet)',
            ['phone' => $phone, 'code' => $code]
        );

        return true;
    }
}
