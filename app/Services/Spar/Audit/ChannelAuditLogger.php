<?php

namespace App\Services\Spar\Audit;

use Zapmed\SparCore\Contracts\AuditLogger;
use Illuminate\Support\Facades\Log;

/**
 * Writes SPAR audit events to the `spar_audit` log channel (spec FR-5.2).
 */
class ChannelAuditLogger implements AuditLogger
{
    public function log(string $event, string $message, array $context = []): void
    {
        Log::channel('spar_audit')->info($message, array_merge(['event' => $event], $context));
    }
}
