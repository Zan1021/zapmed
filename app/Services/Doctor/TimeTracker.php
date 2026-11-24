<?php

namespace App\Services\Doctor;

use App\Enums\UserRole;
use App\Models\DoctorRequest;
use App\Models\TimeEntry;
use App\Models\TimeEntryAdjustment;
use App\Models\User;
use App\Services\Coach\CoachAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Doctor time tracking (Craig's guide 4.2 and 8.1).
 *
 *  - Only doctors log time, and only on a patient they may see or a request they are on.
 *  - One timer at a time. Starting a second one stops the first, so no minutes are counted twice.
 *  - A forgotten timer is cut at config doctor.time.max_timer_minutes and flagged "capped".
 *  - The doctor can change the minutes of their own entry. A reason is required and the old value is kept.
 *  - Operations (admin) can read a doctor's summary for the invoice but cannot change any entry.
 */
class TimeTracker
{
    public function __construct(private readonly CoachAccess $access, private readonly DoctorRequestService $requests)
    {
    }

    public function running(User $doctor): ?TimeEntry
    {
        return TimeEntry::running()->where('user_id', $doctor->id)->latest('id')->first();
    }

    public function start(User $doctor, ?int $patientId = null, ?int $requestId = null, ?string $note = null): TimeEntry
    {
        $this->assertDoctor($doctor);
        $this->checkTarget($doctor, $patientId, $requestId);

        // Same target already running: keep that timer instead of starting a twin.
        $current = $this->running($doctor);
        if ($current && $current->patient_id === $patientId && $current->doctor_request_id === $requestId) {
            return $current;
        }
        if ($current) {
            $this->stop($doctor, $current);
        }

        return TimeEntry::create([
            'user_id' => $doctor->id,
            'patient_id' => $patientId,
            'doctor_request_id' => $requestId,
            'started_at' => now(),
            'source' => 'timer',
            'note' => $note ? mb_substr($note, 0, 255) : null,
        ]);
    }

    public function stop(User $doctor, TimeEntry $entry): TimeEntry
    {
        $this->assertOwner($doctor, $entry);

        if (! $entry->isRunning()) {
            return $entry;
        }

        $minutes = max(1, (int) ceil($entry->started_at->diffInSeconds(now(), true) / 60));
        $cap = (int) config('doctor.time.max_timer_minutes');
        $capped = $minutes > $cap;

        $entry->update(['ended_at' => now(), 'minutes' => min($minutes, $cap), 'capped' => $capped]);

        return $entry->fresh();
    }

    /** A finished entry typed in by hand. $date is the day the work was done (SA time). */
    public function addManual(User $doctor, int $minutes, string $date, ?int $patientId = null, ?int $requestId = null, ?string $note = null): TimeEntry
    {
        $this->assertDoctor($doctor);
        $this->checkTarget($doctor, $patientId, $requestId);
        $this->checkMinutes($minutes, 'minutes');

        $tz = config('doctor.time.timezone');
        try {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new \InvalidArgumentException('format');
            }
            $day = CarbonImmutable::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
            if ($day->format('Y-m-d') !== $date) {
                throw new \InvalidArgumentException('overflow');
            }
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => 'Choose a valid date.']);
        }

        $today = CarbonImmutable::now($tz)->startOfDay();
        if ($day->gt($today)) {
            throw ValidationException::withMessages(['date' => 'You cannot log time in the future.']);
        }
        if ($day->lt($today->subDays((int) config('doctor.time.manual_back_days')))) {
            throw ValidationException::withMessages(['date' => 'That date is too far back to add by hand.']);
        }

        $start = $day->setTime(12, 0)->utc();

        return TimeEntry::create([
            'user_id' => $doctor->id,
            'patient_id' => $patientId,
            'doctor_request_id' => $requestId,
            'started_at' => $start,
            'ended_at' => $start->addMinutes($minutes),
            'minutes' => $minutes,
            'source' => 'manual',
            'note' => $note ? mb_substr($note, 0, 255) : null,
        ]);
    }

    /** Change the minutes of one of your own finished entries. The reason is required and the old value is kept. */
    public function adjust(User $doctor, TimeEntry $entry, int $newMinutes, string $reason): TimeEntry
    {
        $this->assertOwner($doctor, $entry);

        if ($entry->isRunning()) {
            throw ValidationException::withMessages(['entry' => 'Stop the timer before you change its time.']);
        }
        $this->checkMinutes($newMinutes, 'minutes');

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Say why you are changing the time.']);
        }
        if ($newMinutes === $entry->minutes) {
            throw ValidationException::withMessages(['minutes' => 'That is the same time as before.']);
        }

        TimeEntryAdjustment::create([
            'time_entry_id' => $entry->id,
            'user_id' => $doctor->id,
            'old_minutes' => $entry->minutes,
            'new_minutes' => $newMinutes,
            'reason' => mb_substr($reason, 0, 255),
            'created_at' => now(),
        ]);

        $entry->update(['minutes' => $newMinutes, 'capped' => false]);

        return $entry->fresh();
    }

    /** Admin may read any doctor's summary; a doctor only their own. */
    public function canSeeSummary(User $viewer, User $doctor): bool
    {
        return $viewer->role === UserRole::Admin || ($viewer->role === UserRole::Doctor && $viewer->is($doctor));
    }

    /**
     * Monthly summary for the invoice: totals, per day, per patient and per request. Only finished entries count;
     * a running timer is listed separately so it is not missed. Month is "YYYY-MM" in SA time.
     *
     * @return array{month:string, total_minutes:int, total_hours:float, entries:int, adjusted:int, capped:int, running:int, days:Collection, patients:Collection, requests:Collection, rows:Collection}
     */
    public function monthlySummary(User $doctor, string $month): array
    {
        $tz = config('doctor.time.timezone');
        try {
            $start = CarbonImmutable::createFromFormat('Y-m', $month, $tz)->startOfMonth();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['month' => 'Choose a month like 2026-11.']);
        }
        $end = $start->endOfMonth();

        $rows = TimeEntry::with(['patient', 'request', 'adjustments'])
            ->where('user_id', $doctor->id)
            ->whereBetween('started_at', [$start->utc(), $end->utc()])
            ->orderBy('started_at')->get();

        $done = $rows->filter(fn (TimeEntry $e) => ! $e->isRunning());
        $total = (int) $done->sum('minutes');

        return [
            'month' => $start->format('Y-m'),
            'total_minutes' => $total,
            'total_hours' => round($total / 60, 2),
            'entries' => $done->count(),
            'adjusted' => $done->filter(fn (TimeEntry $e) => $e->adjustments->isNotEmpty())->count(),
            'capped' => $done->where('capped', true)->count(),
            'running' => $rows->count() - $done->count(),
            'days' => $done->groupBy(fn (TimeEntry $e) => $e->started_at->setTimezone($tz)->format('Y-m-d'))
                ->map(fn ($g) => (int) $g->sum('minutes'))->sortKeys(),
            'patients' => $done->whereNotNull('patient_id')->groupBy('patient_id')
                ->map(fn ($g) => ['name' => $g->first()->patient?->name ?? 'Patient', 'minutes' => (int) $g->sum('minutes')])->values(),
            'requests' => $done->whereNotNull('doctor_request_id')->groupBy('doctor_request_id')
                ->map(fn ($g) => ['title' => $g->first()->request?->title ?? 'Request', 'minutes' => (int) $g->sum('minutes')])->values(),
            'rows' => $done->values(),
        ];
    }

    /** CSV for the invoice: one line per finished entry plus the total. Cells that start like a formula are neutralised. */
    public function csv(User $doctor, string $month): string
    {
        $s = $this->monthlySummary($doctor, $month);
        $tz = config('doctor.time.timezone');

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Date', 'Patient', 'Request', 'Source', 'Minutes', 'Adjusted', 'Note']);
        foreach ($s['rows'] as $e) {
            fputcsv($out, array_map([$this, 'safeCell'], [
                $e->started_at->setTimezone($tz)->format('Y-m-d'),
                $e->patient?->name ?? '',
                $e->request?->title ?? '',
                $e->source,
                $e->minutes,
                $e->adjustments->isNotEmpty() ? 'yes' : 'no',
                $e->note ?? '',
            ]));
        }
        fputcsv($out, ['Total', '', '', '', $s['total_minutes'], '', $s['total_hours'].' hours']);
        rewind($out);

        return (string) stream_get_contents($out);
    }

    private function safeCell(mixed $v): string
    {
        $v = (string) $v;

        return $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;
    }

    private function checkTarget(User $doctor, ?int $patientId, ?int $requestId): void
    {
        if (! $patientId && ! $requestId) {
            throw ValidationException::withMessages(['target' => 'Choose a patient or a request to log time on.']);
        }

        if ($patientId) {
            $patient = User::where('role', UserRole::Patient->value)->find($patientId);
            if (! $patient || ! $this->access->canView($doctor, $patient)) {
                throw ValidationException::withMessages(['target' => 'You can only log time on your own patients.']);
            }
        }

        if ($requestId) {
            $request = DoctorRequest::find($requestId);
            if (! $request || ! $this->requests->canView($doctor, $request)) {
                throw ValidationException::withMessages(['target' => 'You can only log time on your own requests.']);
            }
        }
    }

    private function checkMinutes(int $minutes, string $field): void
    {
        $max = (int) config('doctor.time.max_entry_minutes');
        if ($minutes < 1 || $minutes > $max) {
            throw ValidationException::withMessages([$field => "Minutes must be between 1 and {$max}."]);
        }
    }

    private function assertDoctor(User $user): void
    {
        abort_unless($user->role === UserRole::Doctor, 403);
    }

    private function assertOwner(User $doctor, TimeEntry $entry): void
    {
        abort_unless($doctor->role === UserRole::Doctor && $entry->user_id === $doctor->id, 403);
    }
}
