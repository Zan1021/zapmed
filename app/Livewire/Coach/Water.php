<?php

namespace App\Livewire\Coach;

use App\Models\CoachProfile;
use App\Services\Coach\CycleCalendar;
use App\Services\Coach\WaterLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Water (design screen 10): a drop that fills, four quick adds, an exact amount, today's last entries with undo.
 */
class Water extends Component
{
    public string $exact = '';

    public ?string $error = null;

    public function add(int $ml, WaterLogger $logger): void
    {
        if (! in_array($ml, config('coach.water.quick_adds_ml', []), true)) {
            return;
        }

        $logger->add(Auth::user(), $ml);
        $this->error = null;
    }

    public function addExact(WaterLogger $logger): void
    {
        $value = trim($this->exact);

        if (! ctype_digit($value)) {
            $this->error = 'Enter a whole number of mL, for example 330.';

            return;
        }

        try {
            $logger->add(Auth::user(), (int) $value);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->exact = '';
        $this->error = null;
    }

    public function undo(WaterLogger $logger): void
    {
        $logger->undoLast(Auth::user());
        $this->error = null;
    }

    public function render(WaterLogger $logger)
    {
        $user = Auth::user();
        $profile = CoachProfile::where('user_id', $user->id)->first();
        $goal = (int) config('coach.targets.water_ml');
        $total = $logger->todayTotal($user);
        $pct = $goal > 0 ? min(1, $total / $goal) : 0;
        $tz = config('coach.timezone');

        return view('livewire.coach.water', [
            'total' => $total,
            'goal' => $goal,
            'dropY' => round(144 - $pct * 136, 1),
            'goalReached' => $goal > 0 && $total >= $goal,
            'quickAdds' => config('coach.water.quick_adds_ml', []),
            'recent' => $logger->recent($user)->map(fn ($e) => [
                'time' => CarbonImmutable::parse($e->logged_at)->setTimezone($tz)->format('H:i'),
                'ml' => $e->ml,
            ]),
            'lutealTip' => (CycleCalendar::status($profile)['phase'] ?? null) === 'luteal' ? config('coach.water.luteal_tip') : null,
            'min' => (int) config('coach.water.exact_min_ml'),
            'max' => (int) config('coach.water.exact_max_ml'),
        ])->layout('layouts.coach', ['title' => 'Water']);
    }
}