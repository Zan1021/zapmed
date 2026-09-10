<?php

namespace App\Spar;

use Zapmed\SparCore\Contracts\AuditLogger;
use Illuminate\Support\Facades\Log;

/**
 * Standalone POPIA audit logger (spec FR-5.2) — writes to the `spar_audit`
 * channel, identical semantics to the integrated host.
 */
class LogAuditLogger implements AuditLogger
{
    public function log(string $event, string $message, array $context = []): void
    {
        Log::channel('spar_audit')->info($message, array_merge(['event' => $event], $context));
    }
}
