<?php

namespace App\Livewire\Patient;

use App\Models\PatientGoal;
use App\Models\ProgressLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProgressTracker extends Component
{
    // Daily log form
    public string $logDate = '';
    public ?string $weight = '';
    public ?string $waist = '';
    public ?int $energyLevel = null;
    public ?int $mood = null;
    public ?int $sleepHours = null;
    public ?int $sleepQuality = null;
    public ?int $waterGlasses = null;
    public bool $medicationTaken = false;
    public bool $exercised = false;
    public string $exerciseType = '';
    public ?int $exerciseMinutes = null;
    public string $mealsSummary = '';
    public string $symptoms = '';
    public string $notes = '';

    // Goal form
    public bool $showGoalForm = false;
    public string $goalType = 'weight';
    public string $goalTarget = '';
    public string $goalDate = '';

    // View state
    public string $period = '30'; // days to show in chart

    public bool $logSaved = false;

    public function mount(): void
    {
        $this->logDate = now()->format('Y-m-d');
        $this->loadExistingLog();
    }

    /**
     * Load existing log for selected date.
     */
    public function loadExistingLog(): void
    {
        $log = ProgressLog::where('user_id', Auth::id())
            ->where('log_date', $this->logDate)
            ->first();

        if ($log) {
            $this->weight = $log->weight_kg ? (string) $log->weight_kg : '';
            $this->waist = $log->waist_cm ? (string) $log->waist_cm : '';
            $this->energyLevel = $log->energy_level;
            $this->mood = $log->mood;
            $this->sleepHours = $log->sleep_hours;
            $this->sleepQuality = $log->sleep_quality;
            $this->waterGlasses = $log->water_glasses;
            $this->medicationTaken = $log->medication_taken;
            $this->exercised = $log->exercised;
            $this->exerciseType = $log->exercise_type ?? '';
            $this->exerciseMinutes = $log->exercise_minutes;
            $this->mealsSummary = $log->meals_summary ?? '';
            $this->symptoms = $log->symptoms ?? '';
            $this->notes = $log->notes ?? '';
        } else {
            $this->resetLogForm();
        }
    }

    public function updatedLogDate(): void
    {
        $this->logSaved = false;
        $this->loadExistingLog();
    }

    /**
     * Save the daily progress log.
     */
    public function saveLog(): void
    {
        $data = [
            'user_id' => Auth::id(),
            'log_date' => $this->logDate,
            'weight_kg' => $this->weight ?: null,
            'waist_cm' => $this->waist ?: null,
            'energy_level' => $this->energyLevel,
            'mood' => $this->mood,
            'sleep_hours' => $this->sleepHours,
            'sleep_quality' => $this->sleepQuality,
            'water_glasses' => $this->waterGlasses,
            'medication_taken' => $this->medicationTaken,
            'exercised' => $this->exercised,
            'exercise_type' => $this->exercised ? $this->exerciseType : null,
            'exercise_minutes' => $this->exercised ? $this->exerciseMinutes : null,
            'meals_summary' => $this->mealsSummary ?: null,
            'symptoms' => $this->symptoms ?: null,
            'notes' => $this->notes ?: null,
        ];

        ProgressLog::updateOrCreate(
            ['user_id' => Auth::id(), 'log_date' => $this->logDate],
            $data
        );

        // Check weight goals
        if ($this->weight) {
            $goals = PatientGoal::where('user_id', Auth::id())
                ->where('type', 'weight')
                ->active()
                ->get();

            foreach ($goals as $goal) {
                $goal->checkAchievement((float) $this->weight);
            }
        }

        $this->logSaved = true;
    }

    /**
     * Add a new goal.
     */
    public function addGoal(): void
    {
        $this->validate([
            'goalType' => 'required|in:weight,waist,exercise,water,sleep',
            'goalTarget' => 'required|numeric|min:0',
        ]);

        $units = [
            'weight' => 'kg',
            'waist' => 'cm',
            'exercise' => 'min/day',
            'water' => 'glasses',
            'sleep' => 'hours',
        ];

        // Get current value as start
        $startValue = match ($this->goalType) {
            'weight' => ProgressLog::latestWeight(Auth::id()),
            default => null,
        };

        PatientGoal::create([
            'user_id' => Auth::id(),
            'type' => $this->goalType,
            'target_value' => (float) $this->goalTarget,
            'unit' => $units[$this->goalType],
            'target_date' => $this->goalDate ?: null,
            'start_value' => $startValue,
        ]);

        $this->showGoalForm = false;
        $this->goalTarget = '';
        $this->goalDate = '';
    }

    /**
     * Delete a goal.
     */
    public function deleteGoal(int $goalId): void
    {
        PatientGoal::where('id', $goalId)
            ->where('user_id', Auth::id())
            ->delete();
    }

    /**
     * Get weight chart data for the selected period.
     */
    public function getChartDataProperty(): array
    {
        $days = (int) $this->period;

        $logs = ProgressLog::where('user_id', Auth::id())
            ->whereNotNull('weight_kg')
            ->where('log_date', '>=', now()->subDays($days))
            ->orderBy('log_date')
            ->get(['log_date', 'weight_kg']);

        return [
            'labels' => $logs->pluck('log_date')->map(fn ($d) => $d->format('d M'))->toArray(),
            'weights' => $logs->pluck('weight_kg')->map(fn ($w) => (float) $w)->toArray(),
        ];
    }

    /**
     * Get active goals.
     */
    public function getGoalsProperty()
    {
        return PatientGoal::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get stats summary.
     */
    public function getStatsProperty(): array
    {
        $userId = Auth::id();
        $currentWeight = ProgressLog::latestWeight($userId);
        $weightChange30 = ProgressLog::weightChange($userId, 30);
        $streakDays = $this->calculateStreak();
        $totalLogs = ProgressLog::where('user_id', $userId)->count();

        return [
            'current_weight' => $currentWeight,
            'weight_change_30d' => $weightChange30,
            'streak_days' => $streakDays,
            'total_logs' => $totalLogs,
        ];
    }

    /**
     * Calculate consecutive days logged.
     */
    private function calculateStreak(): int
    {
        $logs = ProgressLog::where('user_id', Auth::id())
            ->orderByDesc('log_date')
            ->pluck('log_date');

        $streak = 0;
        $expectedDate = now()->startOfDay();

        foreach ($logs as $logDate) {
            if ($logDate->startOfDay()->eq($expectedDate)) {
                $streak++;
                $expectedDate = $expectedDate->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    private function resetLogForm(): void
    {
        $this->weight = '';
        $this->waist = '';
        $this->energyLevel = null;
        $this->mood = null;
        $this->sleepHours = null;
        $this->sleepQuality = null;
        $this->waterGlasses = null;
        $this->medicationTaken = false;
        $this->exercised = false;
        $this->exerciseType = '';
        $this->exerciseMinutes = null;
        $this->mealsSummary = '';
        $this->symptoms = '';
        $this->notes = '';
    }

    public function render()
    {
        return view('livewire.patient.progress-tracker')
            ->layout('layouts.app');
    }
}
