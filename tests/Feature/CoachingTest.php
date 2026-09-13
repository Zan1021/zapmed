<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\TouchpointChannel;
use App\Enums\TouchpointKind;
use App\Enums\UserRole;
use App\Models\CoachingAssignment;
use App\Models\CoachingOffer;
use App\Models\User;
use App\Services\Coaching\CoachingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Task 5 — Coaching (assignment/touchpoint/offer) behaviour + parity.
 */
class CoachingTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): User
    {
        return User::factory()->create(['role' => UserRole::Patient]);
    }

    private function coach(): User
    {
        return User::factory()->create(['role' => UserRole::HealthCoach]);
    }

    private function svc(): CoachingService
    {
        return app(CoachingService::class);
    }

    // ---- role -----------------------------------------------------------------------------------

    public function test_health_coach_role_exists_and_helper_works(): void
    {
        $this->assertSame('health_coach', UserRole::HealthCoach->value);
        $this->assertTrue($this->coach()->isHealthCoach());
        $this->assertSame('Health Coach', UserRole::HealthCoach->label());
    }

    // ---- assignment -----------------------------------------------------------------------------

    public function test_assign_creates_active_assignment(): void
    {
        $assignment = $this->svc()->assign($this->patient(), $this->coach(), 'language match');

        $this->assertTrue($assignment->isActive());
        $this->assertSame('language match', $assignment->reason);
    }

    public function test_only_one_active_assignment_per_patient(): void
    {
        $patient = $this->patient();
        $coachA = $this->coach();
        $coachB = $this->coach();

        $this->svc()->assign($patient, $coachA);
        $this->svc()->assign($patient, $coachB); // reassign

        $active = CoachingAssignment::where('patient_id', $patient->id)->whereNull('ended_at')->get();
        $this->assertCount(1, $active);
        $this->assertSame($coachB->id, $active->first()->coach_id);
        // Prior assignment was ended, not deleted.
        $this->assertSame(2, CoachingAssignment::where('patient_id', $patient->id)->count());
    }

    public function test_reassigning_same_coach_is_a_noop(): void
    {
        $patient = $this->patient();
        $coach = $this->coach();

        $first = $this->svc()->assign($patient, $coach);
        $second = $this->svc()->assign($patient, $coach);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, CoachingAssignment::where('patient_id', $patient->id)->count());
    }

    public function test_cannot_assign_a_non_coach(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->svc()->assign($this->patient(), User::factory()->create(['role' => UserRole::Doctor]));
    }

    public function test_active_coach_lookup(): void
    {
        $patient = $this->patient();
        $coach = $this->coach();
        $this->svc()->assign($patient, $coach);

        $this->assertTrue($this->svc()->activeCoach($patient)->is($coach));

        $this->svc()->endAssignment($patient);
        $this->assertNull($this->svc()->activeCoach($patient->fresh()));
    }

    // ---- touchpoints ----------------------------------------------------------------------------

    public function test_log_touchpoint(): void
    {
        $patient = $this->patient();
        $coach = $this->coach();

        $tp = $this->svc()->logTouchpoint($patient, $coach, TouchpointKind::CheckIn, TouchpointChannel::WhatsApp, 'outbound', [
            'summary' => 'Checked on adherence', 'sentiment' => 'positive', 'successful' => true,
        ]);

        $this->assertSame(TouchpointKind::CheckIn, $tp->kind);
        $this->assertSame(TouchpointChannel::WhatsApp, $tp->channel);
        $this->assertSame('outbound', $tp->direction);
        $this->assertTrue($tp->successful);
    }

    public function test_touchpoint_rejects_bad_direction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->svc()->logTouchpoint($this->patient(), $this->coach(), TouchpointKind::Support, TouchpointChannel::Phone, 'sideways');
    }

    public function test_touchpoint_rejects_negative_duration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->svc()->logTouchpoint($this->patient(), $this->coach(), TouchpointKind::Support, TouchpointChannel::Phone, 'inbound', ['duration_seconds' => -5]);
    }

    // ---- offers ---------------------------------------------------------------------------------

    public function test_offer_lifecycle_accept(): void
    {
        $offer = $this->svc()->makeOffer($this->patient(), $this->coach(), 'weight-loss');
        $this->assertSame(OfferStatus::Open, $offer->status);

        $this->svc()->acceptOffer($offer);
        $this->assertSame(OfferStatus::Accepted, $offer->fresh()->status);
        $this->assertNotNull($offer->fresh()->accepted_at);
    }

    public function test_offer_decline_and_withdraw_guard_open_only(): void
    {
        $offer = $this->svc()->makeOffer($this->patient(), $this->coach(), 'hair');
        $this->svc()->declineOffer($offer, 'not interested');
        $this->assertSame(OfferStatus::Declined, $offer->fresh()->status);

        // Cannot change a non-open offer.
        $this->expectException(InvalidArgumentException::class);
        $this->svc()->withdrawOffer($offer->fresh());
    }

    public function test_expire_due_offers(): void
    {
        $coach = $this->coach();
        $this->svc()->makeOffer($this->patient(), $coach, 'a', null, null, now()->subDay());   // due
        $this->svc()->makeOffer($this->patient(), $coach, 'b', null, null, now()->addWeek());   // not due
        $this->svc()->makeOffer($this->patient(), $coach, 'c');                                  // no expiry

        $expired = $this->svc()->expireDueOffers();

        $this->assertSame(1, $expired);
        $this->assertSame(1, CoachingOffer::where('status', OfferStatus::Expired->value)->count());
    }
}
