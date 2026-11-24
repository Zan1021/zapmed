<?php

namespace App\Services\Patient;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\FollowUpRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Doctor\FollowUpService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Follow-Up tab (Craig's guide 3.1): a patient books a follow-up or an authorisation check-in with a doctor who
 * already treats them, for 15 or 30 minutes.
 *
 *  - A follow-up a doctor asked for (and marked free) costs nothing and is confirmed straight away.
 *  - Anything else is paid: the slot is held for the reservation time and the patient goes to the card page. The doctor's
 *    request only counts as booked once that payment completes (see onPaymentCompleted).
 *  - Slots come from the doctor's own calendar, and every choice is checked again on the server.
 */
class PatientFollowUps
{
    public function __construct(private readonly FollowUpService $followUps)
    {
    }

    /** Follow-ups a doctor has asked this patient to book (not booked yet), soonest first. */
    public function requests(User $patient): Collection
    {
        return FollowUpRequest::with(['order', 'doctor'])->where('patient_id', $patient->id)
            ->where('status', FollowUpRequest::REQUESTED)->orderBy('due_at')->get();
    }

    /** Doctors this patient has been seen by who are verified and taking appointments. */
    public function doctors(User $patient): Collection
    {
        $ids = Appointment::where('patient_id', $patient->id)->whereIn('status', ['completed', 'confirmed', 'in_progress'])->select('doctor_id');

        return User::where('role', UserRole::Doctor->value)->whereIn('id', $ids)
            ->whereHas('doctorProfile', fn ($q) => $q->verified()->acceptingPatients())
            ->with('doctorProfile')->orderBy('first_name')->get();
    }

    /** Free start times on a date for this doctor and length. Empty for past days and beyond the booking window. */
    public function slots(User $patient, int $doctorId, string $date, int $minutes): array
    {
        $doctor = $this->doctors($patient)->firstWhere('id', $doctorId);
        if (! $doctor || ! in_array($minutes, config('patient.follow_up.durations'), true)) {
            return [];
        }

        $tz = config('doctor.time.timezone');
        $day = $this->parseDate($date, $tz);
        $today = CarbonImmutable::now($tz)->startOfDay();
        if ($day->lt($today) || $day->gt($today->addDays((int) config('patient.follow_up.days_ahead')))) {
            return [];
        }

        $times = $doctor->doctorProfile->availableStartTimes($day->format('Y-m-d'), $minutes);

        if ($day->eq($today)) {
            $now = CarbonImmutable::now($tz)->format('H:i');
            $times = array_values(array_filter($times, fn ($t) => $t > $now));
        }

        return array_values($times);
    }

    /** What this booking would cost in cents: 0 when it is a follow-up the doctor asked for and marked free. */
    public function feeCents(User $doctor, int $minutes, ?FollowUpRequest $request): int
    {
        if ($request && $request->is_free) {
            return 0;
        }

        $base = (int) ($doctor->doctorProfile?->followup_fee ?? 0);

        return (int) round($base * (float) (config('patient.follow_up.fee_factor')[$minutes] ?? 1));
    }

    /**
     * @return array{appointment:Appointment, payment:?Payment} payment is null when the booking is free and already confirmed.
     */
    public function book(User $patient, int $doctorId, string $date, string $time, int $minutes, string $type, ?int $followUpRequestId = null): array
    {
        abort_unless($patient->role === UserRole::Patient, 403);

        if (! array_key_exists($type, config('patient.follow_up.types'))) {
            throw ValidationException::withMessages(['type' => 'Choose what you want to book.']);
        }
        if (! in_array($minutes, config('patient.follow_up.durations'), true)) {
            throw ValidationException::withMessages(['minutes' => 'Choose 15 or 30 minutes.']);
        }

        $doctor = $this->doctors($patient)->firstWhere('id', $doctorId);
        if (! $doctor) {
            throw ValidationException::withMessages(['doctor' => 'Choose one of your doctors.']);
        }

        $request = null;
        if ($followUpRequestId) {
            $request = FollowUpRequest::where('patient_id', $patient->id)->where('status', FollowUpRequest::REQUESTED)->findOrFail($followUpRequestId);
        }

        if (! in_array($time, $this->slots($patient, $doctorId, $date, $minutes), true)) {
            throw ValidationException::withMessages(['time' => 'That time is no longer free. Please choose another.']);
        }

        $fee = $this->feeCents($doctor, $minutes, $request);
        $free = $fee === 0;
        $end = date('H:i', strtotime($time) + $minutes * 60);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => $type,
            'status' => $free ? 'confirmed' : 'pending_payment',
            'appointment_date' => $date,
            'start_time' => $time,
            'end_time' => $end,
            'duration_minutes' => $minutes,
            'reason' => config("patient.follow_up.types.{$type}"),
            'communication_preference' => 'video',
            'fee_amount' => $fee,
            'is_paid' => $free,
            'reserved_until' => $free ? null : now()->addMinutes((int) config('onboarding.reservation_minutes', 15)),
        ]);

        $payment = null;
        if ($request) {
            $request->update(['appointment_id' => $appointment->id]);
            if ($free) {
                $this->followUps->markBooked($request, $appointment);
            }
        }

        if (! $free) {
            $payment = Payment::create(['patient_id' => $patient->id, 'appointment_id' => $appointment->id, 'amount' => $fee, 'status' => 'pending']);
        }

        return ['appointment' => $appointment, 'payment' => $payment];
    }

    /** Called when a card payment completes: a doctor's follow-up request linked to that appointment is now booked. */
    public function onPaymentCompleted(Payment $payment): void
    {
        if (! $payment->appointment_id) {
            return;
        }

        $request = FollowUpRequest::where('appointment_id', $payment->appointment_id)->where('status', FollowUpRequest::REQUESTED)->first();
        if ($request && $payment->appointment) {
            $this->followUps->markBooked($request, $payment->appointment);
        }
    }

    private function parseDate(string $date, string $tz): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw ValidationException::withMessages(['date' => 'Choose a valid date.']);
        }
        try {
            $d = CarbonImmutable::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => 'Choose a valid date.']);
        }
        if ($d->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages(['date' => 'Choose a valid date.']);
        }

        return $d;
    }
}
