<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\RepeatCycle;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Repeat-cycle settings (Craig's guide 6.4). Operations edits them; the repeat scheduler reads them.
 *
 *  - A pack of N days first repeats on the first_repeat_days of an ENABLED cycle with that length (28 days -> day 23).
 *    With no matching cycle the old rule applies (pack days minus the delivery buffer in config subscriptions.php), so a
 *    pack length nobody configured still schedules sensibly.
 *  - Switching a cycle off or editing it never touches repeats that are already scheduled; it only affects the next
 *    "first repeat" that is worked out.
 */
class RepeatCycles
{
    /** Days from the prescription to the first repeat for a pack of this length. */
    public function firstRepeatDays(int $packDays): int
    {
        $cycle = RepeatCycle::enabled()->where('days', $packDays)->orderBy('same_day')->orderBy('id')->first();

        if ($cycle) {
            return max(1, min($cycle->first_repeat_days, $cycle->days));
        }

        return max(1, $packDays - (int) config('subscriptions.first_repeat_buffer_days', 5));
    }

    public function save(User $ops, ?RepeatCycle $cycle, string $description, int $days, int $firstRepeatDays, bool $sameDay, bool $enabled): RepeatCycle
    {
        abort_unless($ops->role === UserRole::Admin, 403);

        $description = trim($description);
        if ($description === '') {
            throw ValidationException::withMessages(['description' => 'Give the cycle a name.']);
        }
        if ($days < 1 || $days > 365) {
            throw ValidationException::withMessages(['days' => 'A pack lasts between 1 and 365 days.']);
        }
        if ($firstRepeatDays < 1 || $firstRepeatDays > $days) {
            throw ValidationException::withMessages(['first_repeat_days' => 'The first repeat must fall within the pack, from day 1 to day '.$days.'.']);
        }
        if ($sameDay && $firstRepeatDays !== $days) {
            throw ValidationException::withMessages(['first_repeat_days' => 'A same-day cycle repeats when the pack runs out, so the first repeat is day '.$days.'.']);
        }

        $values = [
            'description' => mb_substr($description, 0, 120), 'days' => $days, 'first_repeat_days' => $firstRepeatDays,
            'same_day' => $sameDay, 'is_enabled' => $enabled, 'updated_by' => $ops->id,
        ];

        // Two enabled cycles of the same length and the same kind would be ambiguous.
        if ($enabled) {
            $clash = RepeatCycle::enabled()->where('days', $days)->where('same_day', $sameDay)->when($cycle, fn ($q) => $q->where('id', '!=', $cycle->id))->exists();
            if ($clash) {
                throw ValidationException::withMessages(['days' => 'There is already an enabled '.($sameDay ? 'same-day ' : '').$days.' day cycle. Edit that one or switch it off first.']);
            }
        }

        if ($cycle) {
            $cycle->update($values);

            return $cycle->fresh();
        }

        return RepeatCycle::create($values);
    }

    public function toggle(User $ops, RepeatCycle $cycle): RepeatCycle
    {
        return $this->save($ops, $cycle, $cycle->description, $cycle->days, $cycle->first_repeat_days, $cycle->same_day, ! $cycle->is_enabled);
    }
}
