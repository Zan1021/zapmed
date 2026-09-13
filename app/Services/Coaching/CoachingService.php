<?php

namespace App\Services\Coaching;

use App\Enums\OfferStatus;
use App\Enums\TouchpointChannel;
use App\Enums\TouchpointKind;
use App\Enums\UserRole;
use App\Models\CoachingAssignment;
use App\Models\CoachingOffer;
use App\Models\CoachingTouchpoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Coaching service — behaviour parity with Mark's coaching module.
 *
 * Owns the "one active coach per patient" invariant (assign() ends any prior active assignment in the
 * same transaction before creating the new one), touchpoint logging, and the cross-sell offer
 * lifecycle. Non-clinical: nothing here touches orders/consultations directly.
 */
class CoachingService
{
    /**
     * Assign (or re-assign) a coach to a patient. Idempotent when the same coach is already active.
     * Ends any different active assignment first, so the partial-unique index is never violated.
     */
    public function assign(User $patient, User $coach, ?string $reason = null, ?int $actorId = null): CoachingAssignment
    {
        if ($coach->role !== UserRole::HealthCoach && $coach->role !== UserRole::Admin) {
            throw new InvalidArgumentException('Assigned coach must have the health_coach (or admin) role.');
        }

        return DB::transaction(function () use ($patient, $coach, $reason, $actorId) {
            $active = CoachingAssignment::where('patient_id', $patient->id)->whereNull('ended_at')->first();

            if ($active && $active->coach_id === $coach->id) {
                return $active; // already assigned to this coach — no-op
            }

            if ($active) {
                $active->update(['ended_at' => now(), 'ended_reason' => 'reassigned']);
            }

            return CoachingAssignment::create([
                'patient_id' => $patient->id,
                'coach_id' => $coach->id,
                'reason' => $reason,
                'started_at' => now(),
                'created_by' => $actorId,
            ]);
        });
    }

    /** End a patient's active assignment (e.g. coach leaves, patient churns). No-op if none active. */
    public function endAssignment(User $patient, string $reason = 'ended', ?int $actorId = null): void
    {
        CoachingAssignment::where('patient_id', $patient->id)->whereNull('ended_at')->get()
            ->each(fn (CoachingAssignment $a) => $a->update(['ended_at' => now(), 'ended_reason' => $reason]));
    }

    /** The patient's current active coach, or null. */
    public function activeCoach(User $patient): ?User
    {
        return CoachingAssignment::where('patient_id', $patient->id)->whereNull('ended_at')->first()?->coach;
    }

    /**
     * Log a touchpoint.
     *
     * @param  array{successful?:?bool,summary?:?string,sentiment?:?string,duration_seconds?:?int,occurred_at?:mixed,metadata?:array}  $context
     */
    public function logTouchpoint(
        User $patient,
        User $coach,
        TouchpointKind|string $kind,
        TouchpointChannel|string $channel,
        string $direction,
        array $context = [],
    ): CoachingTouchpoint {
        $kind = $kind instanceof TouchpointKind ? $kind : TouchpointKind::from($kind);
        $channel = $channel instanceof TouchpointChannel ? $channel : TouchpointChannel::from($channel);

        if (! in_array($direction, ['outbound', 'inbound'], true)) {
            throw new InvalidArgumentException("Touchpoint direction must be outbound or inbound, got '{$direction}'.");
        }

        $duration = $context['duration_seconds'] ?? null;
        if ($duration !== null && $duration < 0) {
            throw new InvalidArgumentException('Touchpoint duration must be non-negative.');
        }

        return CoachingTouchpoint::create([
            'patient_id' => $patient->id,
            'coach_id' => $coach->id,
            'kind' => $kind->value,
            'channel' => $channel->value,
            'direction' => $direction,
            'successful' => $context['successful'] ?? null,
            'summary' => $context['summary'] ?? null,
            'sentiment' => $context['sentiment'] ?? null,
            'duration_seconds' => $duration,
            'metadata' => $context['metadata'] ?? null,
            'occurred_at' => $context['occurred_at'] ?? now(),
            'created_by' => $coach->id,
        ]);
    }

    /** Create a cross-sell offer (status open). */
    public function makeOffer(
        User $patient,
        User $coach,
        string $serviceLine,
        ?int $catalogItemId = null,
        ?string $notes = null,
        ?\DateTimeInterface $expiresAt = null,
    ): CoachingOffer {
        return CoachingOffer::create([
            'patient_id' => $patient->id,
            'coach_id' => $coach->id,
            'service_line' => $serviceLine,
            'catalog_item_id' => $catalogItemId,
            'notes' => $notes,
            'status' => OfferStatus::Open->value,
            'expires_at' => $expiresAt,
            'created_by' => $coach->id,
        ]);
    }

    public function acceptOffer(CoachingOffer $offer): CoachingOffer
    {
        $this->guardOpen($offer);
        $offer->update(['status' => OfferStatus::Accepted->value, 'accepted_at' => now()]);

        return $offer;
    }

    public function declineOffer(CoachingOffer $offer, ?string $reason = null): CoachingOffer
    {
        $this->guardOpen($offer);
        $offer->update(['status' => OfferStatus::Declined->value, 'declined_at' => now(), 'declined_reason' => $reason]);

        return $offer;
    }

    public function withdrawOffer(CoachingOffer $offer): CoachingOffer
    {
        $this->guardOpen($offer);
        $offer->update(['status' => OfferStatus::Withdrawn->value]);

        return $offer;
    }

    /** Expire all open offers past their expiry — for a scheduled sweep. Returns the count expired. */
    public function expireDueOffers(): int
    {
        $due = CoachingOffer::where('status', OfferStatus::Open->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($due as $offer) {
            $offer->update(['status' => OfferStatus::Expired->value]);
        }

        return $due->count();
    }

    private function guardOpen(CoachingOffer $offer): void
    {
        if ($offer->status !== OfferStatus::Open) {
            throw new InvalidArgumentException("Offer is already {$offer->status->value}; cannot change it.");
        }
    }
}
