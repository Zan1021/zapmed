<?php

namespace App\Console\Commands;

use App\Services\Alerts\AlertScanner;
use Illuminate\Console\Command;

/**
 * Run the alert detectors + auto-resolve sweep (Task 4, alerts module parity).
 *
 * The reference ran detectors on an in-process setInterval; we run them on the Laravel scheduler
 * (see routes/console.php — every five minutes). Idempotent: re-running never duplicates active alerts.
 *
 *   php artisan alerts:scan
 */
class AlertsScan extends Command
{
    protected $signature = 'alerts:scan';

    protected $description = 'Scan for SLA/ops conditions and raise or auto-resolve alerts';

    public function handle(AlertScanner $scanner): int
    {
        $result = $scanner->scan();

        $this->info(sprintf(
            'Alerts scan complete — raised/kept: %d, auto-closed: %d, conditions seen: %d.',
            $result['raised'],
            $result['auto_closed'],
            $result['seen'],
        ));

        return self::SUCCESS;
    }
}
