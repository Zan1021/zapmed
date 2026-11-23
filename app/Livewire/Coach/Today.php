<?php

namespace App\Livewire\Coach;

use App\Models\CoachProfile;
use App\Models\WaterEntry;
use App\Services\Coach\CoachCalls;
use App\Services\Coach\CoachClock;
use App\Services\Coach\CoachOnboarding;
use App\Services\Coach\CoachUnits;
use App\Services\Coach\CycleCalendar;
use App\Services\Coach\MedicationLevel;
use App\Services\Coach\NextReminder;
use App\Services\Coach\WaterLogger;
use App\Services\Coach\WeightStats;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Coach App "Track" home (design screen 3): next reminder, weight, medication, cycle (women who switched it on)
 * and water. Meals and activity cards arrive with the v2 nutrition work.
 */
class Today extends Component
{
    public function mount(CoachOnboarding $onboarding)
    {
        // New members set up their plan first (design screens 1 and 2).
        if (! $onboarding->isOnboarded(Auth::user())) {
            return $this->redirectRoute('coach.start');
        }
    }

    /** Quick add from the water card. Only the amounts offered in the app are accepted. */
    public function addWater(int $ml): void
    {
        if (! in_array($ml, config('coach.water.quick_adds_ml', []), true)) {
            return;
        }

        app(WaterLogger::class)->add(Auth::user(), $ml);
        $this->dispatch('coach-toast', message: "Added {$ml} mL");
    }

    public function render()
    {
        $user = Auth::user();
        $profile = CoachProfile::where('user_id', $user->id)->first();
        $units = CoachUnits::normalise($profile?->units);

        $calls = app(CoachCalls::class);
        $nextCall = $calls->upcomingFor($user);
        $weight = WeightStats::forUser($user);
        $med = MedicationLevel::forUser($user);
        [$from, $to] = CoachClock::todayRangeUtc();
        $waterToday = (int) WaterEntry::where('user_id', $user->id)->where('logged_at', '>=', $from)->where('logged_at', '<', $to)->sum('ml');
        $waterGoal = (int) config('coach.targets.water_ml');

        return view('livewire.coach.today', [
            'firstName' => $user->first_name,
            'units' => $units,
            'weight' => $weight,
            'weightText' => fn (?float $kg) => CoachUnits::format($kg, $units),
            'weightSpark' => $this->sparkline(array_column($weight->series, 'kg')),
            'med' => $med,
            'medCurve' => $this->curvePoints($med->curve),
            'cycleOn' => CycleCalendar::enabled($profile),
            'cycle' => CycleCalendar::status($profile),
            'reminders' => NextReminder::phrases($user),
            'nextCall' => $nextCall,
            'nextCallText' => $nextCall ? $calls->when($nextCall) : null,
            'waterToday' => $waterToday,
            'waterGoal' => $waterGoal,
            'quickAdd' => (int) (config('coach.water.quick_adds_ml')['Glass'] ?? 250),
        ])->layout('layouts.coach', ['title' => 'Today']);
    }

    /** SVG polyline points (130 x 46) for a list of values, or null with fewer than 2 points. */
    private function sparkline(array $values, int $w = 130, int $h = 46): ?string
    {
        if (count($values) < 2) {
            return null;
        }
        $min = min($values);
        $max = max($values);
        $range = ($max - $min) ?: 1;
        $n = count($values) - 1;

        return collect($values)->map(fn ($v, $i) => round($i * $w / $n, 1).','.round(4 + ($max - $v) / $range * ($h - 8), 1))->implode(' ');
    }

    /** @return array{past:?string,future:?string} polyline point strings for the medication mini-curve. */
    private function curvePoints(array $curve, int $w = 130, int $h = 46): array
    {
        if (! $curve) {
            return ['past' => null, 'future' => null];
        }

        $first = $curve[0]['t'];
        $span = (end($curve)['t'] - $first) ?: 1;
        $pt = fn ($p) => round(($p['t'] - $first) / $span * $w, 1).','.round(4 + (1 - $p['level']) * ($h - 8), 1);

        $past = collect($curve)->filter(fn ($p) => $p['t'] <= 0)->map($pt)->implode(' ');
        $future = collect($curve)->filter(fn ($p) => $p['t'] >= 0)->map($pt)->implode(' ');

        return ['past' => $past ?: null, 'future' => $future ?: null];
    }
}
