<?php

namespace App\Console\Commands;

use App\Services\Operations\IntegrationLog;
use Illuminate\Console\Command;

/**
 * Sends prescriptions to the pharmacy again when the earlier attempt definitely failed (they refused it, or we could not reach
 * them), up to ten attempts with growing waits. Never touches an attempt we are unsure about, and never sends a prescription the
 * pharmacy already has. Runs every five minutes (routes/console.php).
 */
class IntegrationsRetry extends Command
{
    protected $signature = 'integrations:retry';

    protected $description = 'Send failed pharmacy messages again (only the ones that are safe to repeat)';

    public function handle(IntegrationLog $log): int
    {
        $this->info('Sent again: '.$log->retryDue().'.');

        return self::SUCCESS;
    }
}
