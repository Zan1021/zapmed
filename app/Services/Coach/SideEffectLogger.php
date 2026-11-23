<?php

namespace App\Services\Coach;

use App\Models\CustomSymptom;
use App\Models\SideEffectEntry;
use App\Models\User;
use InvalidArgumentException;

/**
 * The symptom list and side-effect entries (build guide 5.5): nine defaults, the member's own symptoms (kept for
 * next time), defaults the member hid (restorable), scores 0 to 10, and the doctor flag.
 */
class SideEffectLogger
{
    public function __construct(private readonly CoachEscalation $escalation)
    {
    }

    /** @return string[] defaults the member has not hidden, then their own, in a stable order. */
    public function visible(User $user): array
    {
        $rows = CustomSymptom::where('user_id', $user->id)->orderBy('id')->get();
        $hidden = $rows->where('kind', CustomSymptom::HIDDEN_DEFAULT)->pluck('name')->all();
        $custom = $rows->where('kind', CustomSymptom::CUSTOM)->pluck('name')->all();

        return array_values(array_merge(
            array_values(array_diff(config('coach.default_symptoms', []), $hidden)),
            $custom
        ));
    }

    public function hiddenCount(User $user): int
    {
        return CustomSymptom::where('user_id', $user->id)->where('kind', CustomSymptom::HIDDEN_DEFAULT)->count();
    }

    public function addCustom(User $user, string $name): void
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        if ($name === '' || mb_strlen($name) > 60) {
            throw new InvalidArgumentException('Please type a symptom name (up to 60 characters).');
        }

        $all = array_merge(config('coach.default_symptoms', []), CustomSymptom::where('user_id', $user->id)->pluck('name')->all());
        foreach ($all as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($name)) {
                // A hidden default typed again simply comes back; anything else is already in the list.
                CustomSymptom::where('user_id', $user->id)->where('name', $existing)->where('kind', CustomSymptom::HIDDEN_DEFAULT)->delete();

                return;
            }
        }

        CustomSymptom::create(['user_id' => $user->id, 'name' => $name, 'kind' => CustomSymptom::CUSTOM]);
    }

    /** Defaults are hidden (restorable). The member's own symptoms are simply removed from their list. */
    public function hide(User $user, string $name): void
    {
        if (in_array($name, config('coach.default_symptoms', []), true)) {
            CustomSymptom::firstOrCreate(['user_id' => $user->id, 'name' => $name], ['kind' => CustomSymptom::HIDDEN_DEFAULT]);

            return;
        }

        CustomSymptom::where('user_id', $user->id)->where('name', $name)->where('kind', CustomSymptom::CUSTOM)->delete();
    }

    public function restoreHidden(User $user): void
    {
        CustomSymptom::where('user_id', $user->id)->where('kind', CustomSymptom::HIDDEN_DEFAULT)->delete();
    }

    /**
     * Save one check-in. Only scores above 0 are stored. Returns the flagged symptoms (name => score).
     *
     * @param  array<string,mixed>  $scores symptom name => 0..10
     * @return array{saved:int,flagged:array<string,int>}
     */
    public function save(User $user, array $scores): array
    {
        $visible = $this->visible($user);
        $clean = [];

        foreach ($scores as $name => $score) {
            if (! in_array($name, $visible, true)) {
                throw new InvalidArgumentException('Unknown symptom.');
            }
            if (! is_numeric($score) || (int) $score != $score || $score < 0 || $score > 10) {
                throw new InvalidArgumentException('Scores go from 0 to 10.');
            }
            if ((int) $score > 0) {
                $clean[$name] = (int) $score;
            }
        }

        if (! $clean) {
            throw new InvalidArgumentException('Give at least one symptom a score above 0.');
        }

        $today = CoachClock::today()->toDateString();
        $flagged = [];

        foreach ($clean as $name => $score) {
            $isFlag = CoachEscalation::isFlagLevel($name, $score);
            SideEffectEntry::create([
                'user_id' => $user->id, 'logged_on' => $today, 'symptom' => $name, 'score' => $score, 'flagged' => $isFlag,
            ]);
            if ($isFlag) {
                $flagged[$name] = $score;
            }
        }

        foreach ($flagged as $name => $score) {
            $this->escalation->flagSymptom($user, $name, $score);
        }

        return ['saved' => count($clean), 'flagged' => $flagged];
    }
}