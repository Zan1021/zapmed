<?php

namespace App\Livewire\Coach;

use App\Models\CoachProfile;
use App\Services\Coach\CycleCalendar;
use App\Services\Coach\CycleTracker;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Cycle (design screen 14, women only): a wheel coloured by phase, four cards for the phase (weight, hunger, water,
 * mood), Amy's tip, a mood picker, symptom chips and "Period started today". All of it is an estimate and says so.
 * When the member has the tracker off this screen does not exist for them: they are sent to the Track home.
 */
class Cycle extends Component
{
    public string $periodDate = '';

    public string $cycleLength = '';

    public string $periodLength = '';

    public ?string $error = null;

    public function mount(CycleTracker $tracker)
    {
        if (! $tracker->enabled(Auth::user())) {
            return $this->redirectRoute('coach.today');
        }

        $profile = $tracker->profile(Auth::user());
        $this->cycleLength = (string) ($profile->cycle_length ?: config('coach.cycle.default_length'));
        $this->periodLength = (string) ($profile->period_length ?: config('coach.cycle.default_period'));
    }

    public function startToday(CycleTracker $tracker): void
    {
        $this->attempt(fn () => $tracker->startPeriodToday(Auth::user()), 'Day 1 of a new cycle');
    }

    public function savePeriodDate(CycleTracker $tracker): void
    {
        $this->attempt(fn () => $tracker->setLastPeriodStart(Auth::user(), $this->periodDate), 'Saved');
    }

    public function saveLengths(CycleTracker $tracker): void
    {
        if (! ctype_digit(trim($this->cycleLength)) || ! ctype_digit(trim($this->periodLength))) {
            $this->error = 'Enter the cycle and period length as whole numbers of days.';

            return;
        }

        $this->attempt(fn () => $tracker->setLengths(Auth::user(), (int) $this->cycleLength, (int) $this->periodLength), 'Saved');
    }

    public function mood(int $mood, CycleTracker $tracker): void
    {
        $this->attempt(fn () => $tracker->setMood(Auth::user(), $mood));
    }

    public function symptom(string $key, CycleTracker $tracker): void
    {
        $this->attempt(fn () => $tracker->toggleSymptom(Auth::user(), $key));
    }

    public function render(CycleTracker $tracker)
    {
        $user = Auth::user();
        $profile = CoachProfile::where('user_id', $user->id)->first();
        $status = CycleCalendar::status($profile);
        $entry = $tracker->entryToday($user);

        return view('livewire.coach.cycle', [
            'profile' => $profile,
            'status' => $status,
            'arcs' => $status ? $this->arcs($tracker->wheel($profile, $status['day'])) : [],
            'cards' => config('coach.cycle.phase_text')[$status['phase'] ?? ''] ?? [],
            'arrows' => ['up' => '&#8599;', 'down' => '&#8600;', 'steady' => '&#8594;'],
            'tip' => $status ? config('coach.cycle.tips')[$status['phase']] ?? null : null,
            'moods' => config('coach.cycle.moods'),
            'symptoms' => config('coach.cycle.symptoms'),
            'entry' => $entry,
            'notice' => config('coach.cycle.notice'),
            'today' => now(config('coach.timezone'))->toDateString(),
        ])->layout('layouts.coach', ['title' => 'Cycle']);
    }

    /**
     * SVG arcs for the wheel (circle of radius 78 around 100,100): one per cycle day, today drawn thicker.
     *
     * @param  array<int,array{day:int,phase:string,today:bool}>  $wheel
     * @return array<int,array{d:string,colour:string,width:int,day:int,today:bool}>
     */
    private function arcs(array $wheel): array
    {
        $n = count($wheel);
        $gap = 1.2;                       // degrees between days
        $out = [];

        foreach ($wheel as $i => $w) {
            $a0 = deg2rad(-90 + $i * 360 / $n + $gap / 2);
            $a1 = deg2rad(-90 + ($i + 1) * 360 / $n - $gap / 2);
            $r = 78;
            $x0 = round(100 + $r * cos($a0), 2);
            $y0 = round(100 + $r * sin($a0), 2);
            $x1 = round(100 + $r * cos($a1), 2);
            $y1 = round(100 + $r * sin($a1), 2);

            $out[] = [
                'd' => "M {$x0} {$y0} A {$r} {$r} 0 0 1 {$x1} {$y1}",
                'colour' => config('coach.cycle.phase_colours')[$w['phase']] ?? '#DCE5DA',
                'width' => $w['today'] ? 26 : 14,
                'day' => $w['day'],
                'today' => $w['today'],
            ];
        }

        return $out;
    }

    private function attempt(callable $fn, ?string $toast = null): void
    {
        try {
            $fn();
            $this->error = null;
            if ($toast) {
                $this->dispatch('coach-toast', message: $toast);
            }
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }
}
