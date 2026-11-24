<?php

namespace App\Services\Doctor;

use App\Enums\UserRole;
use App\Models\FollowUpRequest;
use App\Models\Order;
use App\Models\OrderAuthorisation;
use App\Models\User;
use App\Services\Coach\CoachAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Doctor authorisations (Craig's guide 4.1): a doctor authorises an order's repeats for six months. Around month
 * five the doctor reviews it and either renews it for six more months or asks for a follow-up first (which holds the
 * order's next repeat until a doctor has reviewed and submitted again).
 *
 *  - Only a doctor who treats the patient grants, renews or asks for a follow-up. Operations gets a read-only report
 *    in the operations phase, not here.
 *  - A renewal is only possible once the authorisation is within its review window (or has run out), and never
 *    while a follow-up on that order is still open.
 *  - Dates are South African dates. An authorisation is valid on every day before its "expires on" date.
 *  - An order that has authorisation records but no valid one has its next repeat held back (config switch).
 *    Orders with no authorisation record at all are never held.
 */
class AuthorisationService
{
    public function __construct(
        private readonly CoachAccess $access,
        private readonly FollowUpService $followUps,
    ) {
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('doctor.time.timezone'))->startOfDay();
    }

    public function isValid(OrderAuthorisation $a): bool
    {
        return $a->status === OrderAuthorisation::ACTIVE && $a->expires_on->format('Y-m-d') > $this->today()->format('Y-m-d');
    }

    public function validFor(Order $order): ?OrderAuthorisation
    {
        return OrderAuthorisation::active()->where('order_id', $order->id)
            ->whereDate('expires_on', '>', $this->today()->format('Y-m-d'))->latest('id')->first();
    }

    /** First authorisation for an order (what the signed first prescription will call). */
    public function grant(User $doctor, Order $order, ?string $startsOn = null): OrderAuthorisation
    {
        $this->assertTreating($doctor, $order);

        if ($this->validFor($order)) {
            throw ValidationException::withMessages(['order' => 'This order is already authorised. Renew it when it is due.']);
        }

        $start = $startsOn ? CarbonImmutable::parse($startsOn, config('doctor.time.timezone'))->startOfDay() : $this->today();

        return OrderAuthorisation::create([
            'order_id' => $order->id,
            'patient_id' => $order->patient_id,
            'doctor_id' => $doctor->id,
            'starts_on' => $start->format('Y-m-d'),
            'expires_on' => $start->addMonthsNoOverflow((int) config('doctor.authorisations.months'))->format('Y-m-d'),
            'status' => OrderAuthorisation::ACTIVE,
        ]);
    }

    /** Authorisations to look at: ending within the review window, or already ended without a renewal. */
    public function dueForReview()
    {
        $limit = $this->today()->addDays((int) config('doctor.authorisations.review_days_before'))->format('Y-m-d');

        return OrderAuthorisation::query()
            ->whereIn('status', [OrderAuthorisation::ACTIVE, OrderAuthorisation::EXPIRED])
            ->whereDate('expires_on', '<=', $limit)
            ->orderBy('expires_on');
    }

    /** True when the authorisation can be renewed now: inside the review window or already ended. */
    public function isDueForReview(OrderAuthorisation $a): bool
    {
        if (! in_array($a->status, [OrderAuthorisation::ACTIVE, OrderAuthorisation::EXPIRED], true)) {
            return false;
        }

        return $a->expires_on->format('Y-m-d') <= $this->today()->addDays((int) config('doctor.authorisations.review_days_before'))->format('Y-m-d');
    }

    public function renew(User $doctor, OrderAuthorisation $a, ?string $note = null): OrderAuthorisation
    {
        $this->assertTreating($doctor, $a->order);

        if (! in_array($a->status, [OrderAuthorisation::ACTIVE, OrderAuthorisation::EXPIRED], true)) {
            throw ValidationException::withMessages(['status' => 'This authorisation has already been renewed.']);
        }
        if (! $this->isDueForReview($a)) {
            throw ValidationException::withMessages(['status' => 'It is not time to review this one yet.']);
        }
        if (FollowUpRequest::open()->where('order_id', $a->order_id)->exists()) {
            throw ValidationException::withMessages(['order' => 'A follow-up is still open on this order. Finish it first.']);
        }

        return DB::transaction(function () use ($doctor, $a, $note) {
            $today = $this->today();
            // Carries on from the old end date while it is still valid, so no days are lost or doubled.
            $start = $a->expires_on->format('Y-m-d') > $today->format('Y-m-d')
                ? CarbonImmutable::parse($a->expires_on->format('Y-m-d'), config('doctor.time.timezone'))
                : $today;

            $a->update(['status' => OrderAuthorisation::RENEWED, 'renewed_at' => now()]);

            return OrderAuthorisation::create([
                'order_id' => $a->order_id,
                'patient_id' => $a->patient_id,
                'doctor_id' => $doctor->id,
                'previous_id' => $a->id,
                'starts_on' => $start->format('Y-m-d'),
                'expires_on' => $start->addMonthsNoOverflow((int) config('doctor.authorisations.months'))->format('Y-m-d'),
                'status' => OrderAuthorisation::ACTIVE,
                'note' => $note ? trim($note) : null,
            ]);
        });
    }

    /** Ask for a follow-up instead of renewing now. The order's next repeat is held until it is done. */
    public function requestFollowUp(User $doctor, OrderAuthorisation $a, int $days, ?string $note = null): FollowUpRequest
    {
        $this->assertTreating($doctor, $a->order);

        if (! in_array($a->status, [OrderAuthorisation::ACTIVE, OrderAuthorisation::EXPIRED], true)) {
            throw ValidationException::withMessages(['status' => 'This authorisation has already been renewed.']);
        }

        $followUp = $this->followUps->request($doctor, $a->order, $days, $note);
        $a->update(['follow_up_request_id' => $followUp->id]);

        return $followUp;
    }

    /** Marks authorisations whose end date has passed as expired. Returns how many. Run daily. */
    public function expireLapsed(): int
    {
        return OrderAuthorisation::active()
            ->whereDate('expires_on', '<=', $this->today()->format('Y-m-d'))
            ->update(['status' => OrderAuthorisation::EXPIRED, 'updated_at' => now()]);
    }

    /** True when this order has authorisation records but none is valid today. */
    public function blocksRepeat(Order $order): bool
    {
        if (! config('doctor.authorisations.block_when_expired')) {
            return false;
        }

        return OrderAuthorisation::where('order_id', $order->id)->exists() && ! $this->validFor($order);
    }

    private function assertTreating(User $doctor, Order $order): void
    {
        $patient = User::where('role', UserRole::Patient->value)->find($order->patient_id);

        abort_unless($doctor->role === UserRole::Doctor && $patient && $this->access->canView($doctor, $patient), 403);
    }
}
