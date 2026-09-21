<?php

namespace Tests\Feature\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Enums\SparActionableStatus;
use Zapmed\SparCore\Livewire\Admin\SparExceptions;
use Zapmed\SparCore\Models\SparConversation;
use Zapmed\SparCore\Models\SparPatient as BaseSparPatient;
use Zapmed\SparCore\Services\MessagingDispatcher;
use Zapmed\SparCore\Services\SparActionService;
use Zapmed\SparCore\Services\SparCoachService;

/**
 * SPAR Close-the-Loop, Wave B6: staff action buttons on the Exceptions screen.
 *
 * Verifies the SparExceptions Livewire component correctly drives
 * SparActionService (nudge / snooze / message) for the actionable subject on a
 * row, honours the consent gate, and refuses a subject class that isn't a
 * whitelisted actionable model (the class-string comes from the browser).
 */
class SparExceptionsActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind a deterministic SparActionService (fake dispatcher, real coach)
        // so the component's app(SparActionService::class) resolves it. The fake
        // honours the real consent guard, so refusal is genuinely exercised.
        $this->app->singleton(SparActionService::class, function ($app) {
            $dispatcher = new class extends MessagingDispatcher {
                public function __construct()
                {
                    parent::__construct([]); // no channels
                }

                public function send(BaseSparPatient $patient, array $payload): bool
                {
                    return $patient->hasConsented();
                }
            };

            $coach = new SparCoachService(
                $app->make(\Zapmed\SparCore\Services\SparPatientView::class),
                $dispatcher,
                $app->make(\Zapmed\SparCore\Services\SparOrderService::class),
            );

            return new SparActionService($dispatcher, $coach);
        });
    }

    private function pharmacy(): SparPharmacy
    {
        return SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '6000001',
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

    private function overdueDispense(SparPatient $patient, SparPharmacy $pharmacy): SparDispenseRecord
    {
        $journey = SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => '778899',
            'status' => 'active',
            'total_dispenses' => 6,
            'dispenses_completed' => 1,
            'start_date' => now()->subMonths(2),
            'medications' => [['name' => 'CO-COPALIA 10MG TAB 28']],
        ]);

        return SparDispenseRecord::create([
            'journey_id' => $journey->id,
            'spar_patient_id' => $patient->id,
            'dispense_number' => 2,
            'status' => 'reminded',
            'reminded_at' => now()->subDays(12),
            'due_date' => now()->subDays(5), // overdue
        ]);
    }

    public function test_nudge_action_sends_and_moves_item_to_awaiting_patient(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->patient($pharmacy);
        $dispense = $this->overdueDispense($patient, $pharmacy);

        Livewire::test(SparExceptions::class)
            ->call('nudgeItem', SparDispenseRecord::class, $dispense->id)
            ->assertSet('actionNotice', 'Nudge sent — the loop is now awaiting the patient.');

        $this->assertSame(SparActionableStatus::AwaitingPatient, $dispense->fresh()->actionStatus());
    }

    public function test_nudge_action_reports_when_patient_not_consented(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->patient($pharmacy, consent: 'pending');
        $dispense = $this->overdueDispense($patient, $pharmacy);

        Livewire::test(SparExceptions::class)
            ->call('nudgeItem', SparDispenseRecord::class, $dispense->id)
            ->assertSet('actionNotice', 'Nudge not sent (no consent or no reachable channel).');

        // No consent => no state change.
        $this->assertSame(SparActionableStatus::Open, $dispense->fresh()->actionStatus());
    }

    public function test_snooze_action_defers_item(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->patient($pharmacy);
        $dispense = $this->overdueDispense($patient, $pharmacy);

        Livewire::test(SparExceptions::class)
            ->call('snoozeItem', SparDispenseRecord::class, $dispense->id, 7);

        $fresh = $dispense->fresh();
        $this->assertSame(SparActionableStatus::Snoozed, $fresh->actionStatus());
        $this->assertTrue($fresh->snoozed_until->isFuture());
    }

    public function test_message_action_opens_modal_and_sends_to_coach_thread(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->patient($pharmacy);
        $dispense = $this->overdueDispense($patient, $pharmacy);

        $staff = User::factory()->create(['role' => 'pharmacy_staff']);

        Livewire::actingAs($staff)
            ->test(SparExceptions::class)
            ->call('messageItem', SparDispenseRecord::class, $dispense->id)
            ->assertSet('showMessageModal', true)
            ->set('messageBody', 'Hi Thabo, just checking in on your repeat.')
            ->call('sendMessage')
            ->assertSet('showMessageModal', false)
            ->assertSet('actionNotice', 'Message sent to the patient.');

        $conversation = SparConversation::where('spar_patient_id', $patient->id)->first();
        $this->assertNotNull($conversation, 'A coach conversation should have been opened.');
        $this->assertSame(SparActionableStatus::Actioned, $dispense->fresh()->actionStatus());
    }

    public function test_send_message_requires_a_body(): void
    {
        $pharmacy = $this->pharmacy();
        $patient = $this->patient($pharmacy);
        $dispense = $this->overdueDispense($patient, $pharmacy);

        Livewire::test(SparExceptions::class)
            ->call('messageItem', SparDispenseRecord::class, $dispense->id)
            ->set('messageBody', '')
            ->call('sendMessage')
            ->assertHasErrors(['messageBody' => 'required']);
    }

    public function test_action_rejects_a_non_whitelisted_subject_class(): void
    {
        // A crafted class-string from the browser must NOT be instantiated.
        Livewire::test(SparExceptions::class)
            ->call('nudgeItem', User::class, 1)
            ->assertSet('actionNotice', 'That item is no longer available.');
    }
}
