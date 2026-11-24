<?php

namespace App\Services\Doctor;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The doctor's appointment lists (Craig's guide 4.1): New appointments, Past appointments and the Patient History
 * board. Every query is limited to the signed-in doctor's own appointments, so a doctor never sees another doctor's
 * patients here. Dates are South African dates.
 */
class DoctorWorkboard
{
    public const NEW_STATUSES = ['pending', 'confirmed', 'in_progress'];
    public const PAST_STATUSES = ['completed', 'no_show', 'cancelled'];

    private function tz(): string
    {
        return config('doctor.time.timezone');
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->tz())->startOfDay();
    }

    /** Start and end date (inclusive) for a calendar view anchored on a day: day, week (Mon to Sun) or month. */
    public function range(string $view, ?string $anchor = null): array
    {
        $day = $anchor ? $this->date($anchor, 'anchor') : $this->today();

        return match ($view) {
            'day' => [$day, $day],
            'week' => [$day->startOfWeek(), $day->endOfWeek()->startOfDay()],
            'month' => [$day->startOfMonth(), $day->endOfMonth()->startOfDay()],
            default => throw ValidationException::withMessages(['view' => 'Choose day, week or month.']),
        };
    }

    /**
     * @param string $sort 'booked' = newest booked first (the guide's newest-first list), 'date' = by appointment date
     */
    public function newAppointments(User $doctor, ?string $from = null, ?string $to = null, ?string $type = null, string $sort = 'booked'): Collection
    {
        $q = $this->base($doctor, self::NEW_STATUSES, $from, $to, $type);

        $sort === 'date'
            ? $q->orderBy('appointment_date')->orderBy('start_time')
            : $q->orderByDesc('created_at')->orderByDesc('id');

        return $q->limit(300)->get();
    }

    /** Completed (and missed or cancelled) appointments, newest first, with the prescriptions and the next repeat date. */
    public function pastAppointments(User $doctor, ?string $from = null, ?string $to = null, ?string $type = null): Collection
    {
        $rows = $this->base($doctor, self::PAST_STATUSES, $from, $to, $type)
            ->with('consultation')
            ->orderByDesc('appointment_date')->orderByDesc('start_time')->limit(300)->get();

        $consultationIds = $rows->pluck('consultation.id')->filter();
        $submitted = $consultationIds->isEmpty() ? collect()
            : Prescription::whereIn('consultation_id', $consultationIds)->whereIn('status', ['signed', 'dispensed'])->get()->groupBy('consultation_id');
        $repeat = Order::whereIn('patient_id', $rows->pluck('patient_id')->unique())->whereNotNull('next_repeat_date')
            ->orderBy('next_repeat_date')->get()->groupBy('patient_id');

        return $rows->each(function (Appointment $a) use ($submitted, $repeat) {
            $a->setAttribute('submitted_prescriptions', $submitted->get($a->consultation?->id, collect())->count());
            $a->setAttribute('next_repeat', $repeat->get($a->patient_id)?->first()?->next_repeat_date);
        });
    }

    /**
     * Patient history board: one row per patient the doctor has seen in the date range, with how often, when last, and how
     * many notes the doctor may read. Search by name, email or member number.
     *
     * @return Collection<int,array{patient:User, visits:int, last_visit:string, notes:int, latest_appointment_id:int}>
     */
    public function patientHistory(User $doctor, ?string $from = null, ?string $to = null, ?string $search = null): Collection
    {
        $rows = $this->base($doctor, ['completed', 'in_progress', 'confirmed', 'pending', 'no_show'], $from, $to, null)
            ->with('patient')->orderByDesc('appointment_date')->orderByDesc('id')->limit(2000)->get();

        $term = trim((string) $search);
        if ($term !== '') {
            $rows = $rows->filter(fn (Appointment $a) => $a->patient && (
                stripos($a->patient->name, $term) !== false
                || stripos((string) $a->patient->email, $term) !== false
                || stripos((string) $a->patient->member_number, $term) !== false));
        }

        $notes = app(PatientNoteService::class);

        return $rows->groupBy('patient_id')->map(function (Collection $visits) use ($doctor, $notes) {
            $latest = $visits->first();

            return [
                'patient' => $latest->patient,
                'visits' => $visits->count(),
                'last_visit' => $latest->appointment_date->format('Y-m-d'),
                'notes' => $notes->readableFor($doctor, $latest->patient)->count(),
                'latest_appointment_id' => $latest->id,
            ];
        })->values();
    }

    private function base(User $doctor, array $statuses, ?string $from, ?string $to, ?string $type)
    {
        abort_unless($doctor->isDoctor(), 403);

        $q = Appointment::query()->with('patient')->where('doctor_id', $doctor->id)->whereIn('status', $statuses);

        if ($from) {
            $q->whereDate('appointment_date', '>=', $this->date($from, 'from')->format('Y-m-d'));
        }
        if ($to) {
            $q->whereDate('appointment_date', '<=', $this->date($to, 'to')->format('Y-m-d'));
        }
        if ($from && $to && $this->date($from, 'from')->gt($this->date($to, 'to'))) {
            throw ValidationException::withMessages(['to' => 'The end date is before the start date.']);
        }
        if ($type) {
            $q->where('type', $type);
        }

        return $q;
    }

    private function date(string $value, string $field): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw ValidationException::withMessages([$field => 'Choose a valid date.']);
        }
        try {
            $d = CarbonImmutable::createFromFormat('Y-m-d', $value, $this->tz())->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Choose a valid date.']);
        }

        // 2026-02-31 would silently become 3 March: refuse dates that do not exist.
        if ($d->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Choose a valid date.']);
        }

        return $d;
    }
}
