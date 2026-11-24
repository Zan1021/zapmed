<?php

namespace App\Console\Commands;

use App\Services\Coach\CoachAssigner;
use Illuminate\Console\Command;

/**
 * Gives a coach to every paying add-on member who does not have one yet (nobody had room when they paid, or their
 * coach left). Runs hourly (routes/console.php). Safe to run any time: members who have a coach are skipped.
 */
class CoachAssignPending extends Command
{
    protected $signature = 'coach:assign-pending';

    protected $description = 'Assign a coach to paying coach add-on members who are still waiting for one';

    public function handle(CoachAssigner $assigner): int
    {
        $n = $assigner->assignPending();
        $this->info("Assigned a coach to {$n} member(s).");

        return self::SUCCESS;
    }
}
