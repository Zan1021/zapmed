<?php

namespace Tests\Feature\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Zapmed\SparCore\Enums\SparActionableStatus;
use Zapmed\SparCore\Models\SparConversation;
use Zapmed\SparCore\Models\SparMessage;
use Zapmed\SparCore\Models\SparPatient as BaseSparPatient;
use Zapmed\SparCore\Services\MessagingDispatcher;
use Zapmed\SparCore\Services\SparActionService;
use Zapmed\SparCore\Services\SparCoachService;

/**
 * SPAR Close-the-Loop, Wave B2: SparActionService — the single staff-action
 * seam (nudge / snooze / personalMessage / resolve) over any actionable item.
 *
 * Uses a fake MessagingDispatcher so channel wiring is irrelevant and the
 * consent gate + state transitions are asserted deterministically (mirrors the
 * consent-refusal discipline the coach tests follow — NFR-1).
 */
class SparActionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function pharmacy(): SparPharmacy
    {
        return SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '4000001',
            'supports_delivery' => true,
            'is_active' => true,
        ]);
    }

    private function patient(SparPharmacy $pharmacy, string $consent = 'opted_in'): SparPatient
    {
        return SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'cellphone' => '0821234567',
            'is_primary_member' => true,
            'consent_status' => $consent,
            'consent_given_at' => $consent === 'opted_in' ? now() : null,
            'is_active' => true,
        ]);
    }

    private function journey(SparPatient $patient): SparPrescriptionJourney
    {
        return SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $patient->spar_pharmacy_id,
            'script_number' => '422564',
            'status' => 'active',
            'total_dispenses' => 6,
            'dispenses_completed' => 1,
            'start_date' => now(),
        ]);
    }

    /**
     * A dispatcher test double that records sends and returns a fixed result,
     * so tests don't depend on channel registration.
     */
    private function fakeDispatcher(bool $result = true): MessagingDispatcher
    {
        return new class($result) extends MessagingDispatcher {
            public array $sent = [];

            public function __construct(private bool $result)
            {
                parent::__construct([]); // no channels
            }

            public function send(BaseSparPatient $patient, array $payload): bool
            {
                // Honour the real consent guard so refusal is genuinely tested.
                if (! $patient->hasConsented()) {
                    return false;
                }
                $this->sent[] = ['patient' => $patient->id, 'payload' => $payload];

                return $this->result;
            }
        };
    }

    private function service(MessagingDispatcher $dispatcher): SparActionService
    {
        // Real coach service, but with the fake dispatcher injected so its
        // internal nudge also short-circuits deterministically.
        $coach = new SparCoachService(
            app(\Zapmed\SparCore\Services\SparPatientView::class),
            $dispatcher,
            app(\Zapmed\SparCore\Services\SparOrderService::class),
        );

        return new SparActionService($dispatcher, $coach);
    }

    public function test_nudge_sends_and_moves_item_to_awaiting_patient(): void
    {
        $patient = $this->patient($this->pharmacy());
        $journey = $this->journey($patient);
        $dispatcher = $this->fakeDispatcher(result: true);

        $ok = $this->service($dispatcher)->nudge($journey, [
            'subject' => 'Your repeat is due',
            'body' => 'Time to collect your medication.',
        ]);

        $this->assertTrue($ok);
        $this->assertCount(1, $dispatcher->sent);
        $this->assertSame(SparActionableStatus::AwaitingPatient, $journey->fresh()->actionStatus());
        $this->assertNotNull($journey->fresh()->last_action_at);
    }

    public function test_nudge_refuses_and_does_not_change_state_without_consent(): void
    {
        $patient = $this->patient($this->pharmacy(), consent: 'pending');
        $journey = $this->journey($patient);
        $dispatcher = $this->fakeDispatcher(result: true);

        $ok = $this->service($dispatcher)->nudge($journey, ['body' => 'Hi']);

        $this->assertFalse($ok);
        $this->assertCount(0, $dispatcher->sent);
        // Untouched — still Open, no action stamped.
        $this->assertSame(SparActionableStatus::Open, $journey->fresh()->actionStatus());
        $this->assertNull($journey->fresh()->last_action_at);
    }

    public function test_nudge_returns_false_when_delivery_fails_on_all_channels(): void
    {
        $patient = $this->patient($this->pharmacy());
        $journey = $this->journey($patient);
        $dispatcher = $this->fakeDispatcher(result: false); // consented but undelivered

        $ok = $this->service($dispatcher)->nudge($journey, ['body' => 'Hi']);

        $this->assertFalse($ok);
        // No successful send => no awaiting_patient transition.
        $this->assertSame(SparActionableStatus::Open, $journey->fresh()->actionStatus());
    }

    public function test_snooze_defers_item_and_excludes_it_from_open_list(): void
    {
        $patient = $this->patient($this->pharmacy());
        $journey = $this->journey($patient);
        $dispatcher = $this->fakeDispatcher();

        $ok = $this->service($dispatcher)->snooze($journey, 7);

        $this->assertTrue($ok);
        $this->assertCount(0, $dispatcher->sent); // snooze sends nothing
        $this->assertSame(SparActionableStatus::Snoozed, $journey->fresh()->actionStatus());
        $this->assertTrue($journey->fresh()->snoozed_until->isFuture());

        $openIds = SparPrescriptionJourney::openItems()->pluck('id')->all();
        $this->assertNotContains($journey->id, $openIds);
    }

    public function test_snooze_refused_on_resolved_item(): void
    {
        $patient = $this->patient($this->pharmacy());
        $journey = $this->journey($patient);
        $journey->resolveActionable();

        $ok = $this->service($this->fakeDispatcher())->snooze($journey, 3);

        $this->assertFalse($ok);
        $this->assertSame(SparActionableStatus::Resolved, $journey->fresh()->actionStatus());
    }

    public function test_personal_message_posts_to_coach_thread_and_marks_actioned(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->patient($pharmacy);
        $journey = $this->journey($patient);
        $dispatcher = $this->fakeDispatcher();

        $ok = $this->service($dispatcher)->personalMessage(
            $journey,
            'Hi Thabo, just checking in on your repeat.',
            ['id' => 7, 'name' => 'Nurse Joy', 'role' => 'pharmacy_staff'],
            $pharmacy->id,
        );

        $this->assertTrue($ok);

        // Coach thread created + a staff message posted.
        $conversation = SparConversation::where('spar_patient_id', $patient->id)->first();
        $this->assertNotNull($conversation);
        $this->assertSame(1, SparMessage::where('spar_conversation_id', $conversation->id)
            ->where('direction', 'from_staff')->count());

        $this->assertSame(SparActionableStatus::Actioned, $journey->fresh()->actionStatus());
    }

    public function test_personal_message_refused_without_consent(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->patient($pharmacy, consent: 'pending');
        $journey = $this->journey($patient);

        $ok = $this->service($this->fakeDispatcher())->personalMessage(
            $journey,
            'Hi',
            ['id' => 7, 'name' => 'Nurse Joy'],
            $pharmacy->id,
        );

        $this->assertFalse($ok);
        $this->assertSame(0, SparConversation::count());
        $this->assertSame(SparActionableStatus::Open, $journey->fresh()->actionStatus());
    }

    public function test_resolve_closes_the_loop(): void
    {
        $patient = $this->patient($this->pharmacy());
        $dispense = SparDispenseRecord::create([
            'journey_id' => $this->journey($patient)->id,
            'spar_patient_id' => $patient->id,
            'dispense_number' => 1,
            'status' => 'upcoming',
            'due_date' => now()->addDays(3),
        ]);

        $ok = $this->service($this->fakeDispatcher())->resolve($dispense);

        $this->assertTrue($ok);
        $this->assertSame(SparActionableStatus::Resolved, $dispense->fresh()->actionStatus());
        $this->assertNotNull($dispense->fresh()->resolved_at);
    }
}
