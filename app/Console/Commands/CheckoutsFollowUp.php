<?php

namespace App\Console\Commands;

use App\Services\Operations\AbandonedCheckouts;
use Illuminate\Console\Command;

/**
 * Reminds patients who left an order unpaid for a day (once per order). Does nothing until OPS_ABANDONED_NOTIFY=true.
 * Runs hourly (routes/console.php).
 */
class CheckoutsFollowUp extends Command
{
    protected $signature = 'checkouts:follow-up';

    protected $description = 'Remind patients who did not finish paying for an order';

    public function handle(AbandonedCheckouts $checkouts): int
    {
        $this->info('Reminded '.$checkouts->run().' patient(s).');

        return self::SUCCESS;
    }
}
