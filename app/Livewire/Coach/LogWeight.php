<?php

namespace App\Livewire\Coach;

use App\Models\CoachProfile;
use App\Services\Coach\CoachUnits;
use App\Services\Coach\CycleCalendar;
use App\Services\Coach\WeightLogger;
use App\Services\Coach\WeightStats;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Log weight (design screen 5): big number with minus/plus in 0.1 steps, kg/lb switch, cycle phase tag.
 */
class LogWeight extends Component
{
    public float $value = 0;

    public string $unit = 'kg';

    public ?string $error = null;

    public function mount(): void
    {
        $profile = CoachProfile::where('user_id', Auth::id())->first();
        $this->unit = CoachUnits::normalise($profile?->units);

        $stats = WeightStats::forUser(Auth::user());
        $kg = $stats->current ?? ($profile?->start_weight_kg !== null ? (float) $profile->start_weight_kg : 70.0);
        $this->value = CoachUnits::fromKg($kg, $this->unit);
    }

    public function step(float $delta): void
    {
        $this->value = round(max(0, $this->value + ($delta > 0 ? 0.1 : -0.1)), 1);
        $this->error = null;
    }

    public function setUnit(string $unit, WeightLogger $logger): void
    {
        $unit = CoachUnits::normalise($unit);

        if ($unit === $this->unit) {
            return;
        }

        // Convert what is on screen, and remember the choice for the whole app (Profile > Units).
        $kg = CoachUnits::toKg($this->value, $this->unit);
        $this->unit = $unit;
        $this->value = CoachUnits::fromKg($kg, $unit);
        $logger->setUnits(Auth::user(), $unit);
    }

    public function save(WeightLogger $logger)
    {
        try {
            $logger->log(Auth::user(), CoachUnits::toKg((float) $this->value, $this->unit));
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        return redirect()->route('coach.weight')->with('coach_toast', "Weight saved. Today's value is updated.");
    }

    public function render()
    {
        $profile = CoachProfile::where('user_id', Auth::id())->first();

        return view('livewire.coach.log-weight', [
            'cycle' => CycleCalendar::status($profile),
            'allowLb' => (bool) config('coach.units.allow_lb'),
        ])->layout('layouts.coach', ['title' => 'Log weight', 'hideTabs' => true]);
    }
}