<?php

namespace App\Livewire\Coach;

use App\Services\Coach\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Activity (design screen 12): calories burned today, steps, the last 7 days as bars, today's list with delete.
 * Steps are typed in for now. The Apple Health / Google Fit switch is shown but off: it needs a native app.
 */
class Activity extends Component
{
    public string $steps = '';

    public ?string $error = null;

    public function saveSteps(ActivityLogger $logger): void
    {
        $value = str_replace([',', ' '], '', trim($this->steps));

        if ($value === '' || ! ctype_digit($value)) {
            $this->error = 'Enter your steps as a whole number, for example 6420.';

            return;
        }

        try {
            $logger->setSteps(Auth::user(), (int) $value);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->steps = '';
        $this->error = null;
    }

    public function remove(int $id, ActivityLogger $logger): void
    {
        $logger->remove(Auth::user(), $id);
    }

    public function render(ActivityLogger $logger)
    {
        $user = Auth::user();
        $week = $logger->lastSevenDays($user);
        $max = max(1, max(array_column($week, 'kcal')));

        return view('livewire.coach.activity', [
            'totals' => $logger->todayTotals($user),
            'entries' => $logger->todayEntries($user),
            'week' => $week,
            'weekMax' => $max,
            'stepsMax' => (int) config('coach.activity.steps_max'),
        ])->layout('layouts.coach', ['title' => 'Activity']);
    }
}
