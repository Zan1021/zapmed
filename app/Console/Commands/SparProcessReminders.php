<?php

namespace App\Console\Commands;

use Zapmed\SparCore\Services\SparReminderService;
use Illuminate\Console\Command;

class SparProcessReminders extends Command
{
    protected $signature = 'spar:process-reminders';
    protected $description = 'Process SPAR medication reminders (monthly + renewal)';

    public function handle(SparReminderService $service): int
    {
        $this->info('Processing SPAR reminders...');

        $stats = $service->processReminders();

        $this->info("Reminders sent: {$stats['reminders_sent']}");
        $this->info("Renewal reminders sent: {$stats['renewal_reminders_sent']}");

        if ($stats['errors'] > 0) {
            $this->warn("Errors: {$stats['errors']}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
