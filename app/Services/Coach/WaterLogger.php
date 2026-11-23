<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Models\WaterEntry;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Water entries (build guide 5.6). "Today" is the South African day. Amounts are whole mL.
 * Quick adds are the four buttons; an exact amount must sit between the configured minimum and maximum.
 */
class WaterLogger
{
    public function add(User $user, int $ml): WaterEntry
    {
        $min = (int) config('coach.water.exact_min_ml', 10);
        $max = (int) config('coach.water.exact_max_ml', 3000);

        if ($ml < $min || $ml > $max) {
            throw new InvalidArgumentException("Enter an amount between {$min} and {$max} mL.");
        }

        return WaterEntry::create(['user_id' => $user->id, 'logged_at' => now(), 'ml' => $ml]);
    }

    /** Remove the latest entry of TODAY only. Yesterday's entries are never touched. */
    public function undoLast(User $user): bool
    {
        $last = $this->todayQuery($user)->latest('logged_at')->latest('id')->first();

        return $last ? (bool) $last->delete() : false;
    }

    public function todayTotal(User $user): int
    {
        return (int) $this->todayQuery($user)->sum('ml');
    }

    /** @return Collection<int,WaterEntry> newest first */
    public function recent(User $user, ?int $limit = null): Collection
    {
        return $this->todayQuery($user)->latest('logged_at')->latest('id')
            ->limit($limit ?? (int) config('coach.water.recent_log_count', 5))->get();
    }

    private function todayQuery(User $user)
    {
        [$from, $to] = CoachClock::todayRangeUtc();

        return WaterEntry::where('user_id', $user->id)->where('logged_at', '>=', $from)->where('logged_at', '<', $to);
    }
}