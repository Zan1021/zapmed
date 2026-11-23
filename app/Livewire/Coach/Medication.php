<?php

namespace App\Livewire\Coach;

use App\Models\CoachProfile;
use App\Models\DoseEntry;
use App\Models\ReminderSetting;
use App\Services\Coach\CoachClock;
use App\Services\Coach\CycleCalendar;
use App\Services\Coach\MedicationChart;
use App\Services\Coach\MedicationLevel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Medication tracker (design screen 6): illustrative level curve, next and last dose, tip, history, dose reminder.
 * Never names the medication, always says the curve is an estimate, never advises a dose change.
 */
class Medication extends Component
{
    public function render()
    {
        $user = Auth::user();
        $profile = CoachProfile::where('user_id', $user->id)->first();
        $level = MedicationLevel::forUser($user);
        $cycle = CycleCalendar::status($profile);
        $reminder = ReminderSetting::where('user_id', $user->id)->where('type', 'dose_day')->first();
        $tz = config('coach.timezone');

        $history = DoseEntry::where('user_id', $user->id)->orderByDesc('taken_at')
            ->limit((int) config('coach.medication.history_count', 8))->get()
            ->map(fn ($d) => [
                'when' => CarbonImmutable::parse($d->taken_at)->setTimezone($tz)->format('D j M'),
                'site' => $d->site,
                'pain' => $d->pain,
            ]);

        $progress = null;
        if ($level->daysToNext !== null) {
            $interval = (int) config('coach.medication.dose_interval_days', 7);
            $progress = (int) max(0, min(100, round(($interval - $level->daysToNext) / $interval * 100)));
        }

        return view('livewire.coach.medication', [
            'level' => $level,
            'chart' => MedicationChart::build($level),
            'progress' => $progress,
            'history' => $history,
            'showLutealTip' => ($cycle['phase'] ?? null) === 'luteal',
            'reminderText' => ($reminder && $reminder->enabled)
                ? 'Evening before, '.($reminder->settings['time'] ?? '20:00')
                : 'Off',
            'disclaimer' => config('coach.disclaimers.medication_curve'),
            'tips' => config('coach.medication.tips'),
            'today' => CoachClock::today(),
        ])->layout('layouts.coach', ['title' => 'Medication']);
    }
}