<?php

namespace App\Console\Commands;

use Zapmed\SparCore\Services\SparReminderService;
use Illuminate\Console\Command;

/**
 * Standalone daily reminder run (spec FR-12). Thin wrapper over the package
 * SparReminderService — identical behaviour to the integrated host, minus the
 * telehealth renewal option (NullTelehealthBridge is bound).
 */
class SparProcessReminders extends Command
{
    protected $signature = 'spar:process-reminders';
    protected $description = 'Process due SPAR medication + renewal reminders';

    public function handle(SparReminderService $service): int
    {
        $stats = $service->processReminders();

        $this->info(sprintf(
            'SPAR reminders: %d sent, %d renewal, %d errors.',
            $stats['reminders_sent'],
            $stats['renewal_reminders_sent'],
            $stats['errors']
        ));

        return self::SUCCESS;
    }
}
