<?php

namespace App\Livewire\Coach;

use App\Models\CoachProfile;
use App\Models\ReminderSetting;
use App\Models\WeightEntry;
use App\Services\Coach\CoachClock;
use App\Services\Coach\CoachUnits;
use App\Services\Coach\CycleCalendar;
use App\Services\Coach\WeightChart;
use App\Services\Coach\WeightLogger;
use App\Services\Coach\WeightStats;
use App\Services\Coach\WeightTip;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Weight tracker (design screen 4): chart (14/30/90 days), tip, four stat tiles, history, goal.
 */
class Weight extends Component
{
    public int $range = 30;

    public bool $showGoal = false;

    public float $goalInput = 0;

    public ?string $goalError = null;

    public function setRange(int $days): void
    {
        if (in_array($days, config('coach.weight.ranges_days', [14, 30, 90]), true)) {
            $this->range = $days;
        }
    }

    public function openGoal(): void
    {
        $stats = WeightStats::forUser(Auth::user());
        $units = $this->units();

        if ($stats->current === null) {
            $this->goalError = 'Log your weight first, then set a goal.';
            $this->showGoal = true;
            $this->goalInput = 0;

            return;
        }

        $base = $stats->goal ?? max((float) config('coach.weight.min_goal_kg', 45), round($stats->current - 5));
        $this->goalInput = round(CoachUnits::fromKg($base, $units));
        $this->goalError = null;
        $this->showGoal = true;
    }

    public function stepGoal(int $delta): void
    {
        $this->goalInput = max(0, $this->goalInput + ($delta > 0 ? 1 : -1));
        $this->goalError = null;
    }

    public function saveGoal(WeightLogger $logger): void
    {
        try {
            $logger->setGoal(Auth::user(), CoachUnits::toKg($this->goalInput, $this->units()));
        } catch (InvalidArgumentException $e) {
            $this->goalError = $e->getMessage();

            return;
        }

        $this->showGoal = false;
        $this->goalError = null;
        $this->dispatch('coach-toast', message: 'Goal saved');
    }

    public function closeGoal(): void
    {
        $this->showGoal = false;
        $this->goalError = null;
    }

    private function units(): string
    {
        return CoachUnits::normalise(CoachProfile::where('user_id', Auth::id())->value('units'));
    }

    public function render()
    {
        $user = Auth::user();
        $profile = CoachProfile::where('user_id', $user->id)->first();
        $units = CoachUnits::normalise($profile?->units);
        $stats = WeightStats::forUser($user);
        $cycle = CycleCalendar::status($profile);

        $change = ($stats->current !== null && $stats->start !== null) ? $stats->current - $stats->start : null;
        $changeText = $change === null ? '-' : (($change < 0 ? '-' : ($change > 0 ? '+' : '')).number_format(abs(CoachUnits::fromKg($change, $units)), 1).' '.$units);

        $weighIn = ReminderSetting::where('user_id', $user->id)->where('type', 'weigh_in')->first();

        return view('livewire.coach.weight', [
            'units' => $units,
            'stats' => $stats,
            'chart' => WeightChart::build($user, $this->range, $profile),
            'ranges' => config('coach.weight.ranges_days', [14, 30, 90]),
            'tip' => WeightTip::for($stats, $cycle['phase'] ?? null, $units),
            'trackCycle' => CycleCalendar::enabled($profile),
            'changeText' => $changeText,
            'current' => CoachUnits::format($stats->current, $units),
            'goalText' => CoachUnits::format($stats->goal, $units),
            'history' => WeightEntry::where('user_id', $user->id)->orderByDesc('logged_on')->limit(8)->get(),
            'weighInDay' => ($weighIn && $weighIn->enabled) ? ucfirst((string) ($weighIn->settings['day'] ?? 'monday')) : null,
            'bmiNote' => config('coach.disclaimers.bmi'),
            'minGoalKg' => config('coach.weight.min_goal_kg'),
        ])->layout('layouts.coach', ['title' => 'Weight']);
    }
}