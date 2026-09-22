<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Contracts\MessagingChannel;
use Zapmed\SparCore\Livewire\Admin\SparRenewals;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\MessagingDispatcher;

/**
 * SPAR Close-the-Loop Wave C3 (FR-C3) — Renewals workflow.
 * Window filters, per-patient + bulk "prepare your script?" comms, consent gate.
 */
class SparRenewalsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacy $a;
    private SparPharmacy $b;

    protected function setUp(): void
    {
        parent::setUp();

        $groupA = SparPharmacyGroup::create(['name' => 'GA']);
        $groupB = SparPharmacyGroup::create(['name' => 'GB']);
        $this->a = SparPharmacy::create(['group_id' => $groupA->id, 'name' => 'Plett', 'spar_store_id' => 'A', 'is_active' => true]);
        $this->b = SparPharmacy::create(['group_id' => $groupB->id, 'name' => 'Faraway', 'spar_store_id' => 'B', 'is_active' => true]);
    }

    private function staffAtA(): void
    {
        $user = PharmacyUser::create([
            'name' => 'Joy', 'email' => 'joy' . uniqid() . '@t.test', 'password' => Hash::make('x'),
            'role' => 'pharmacy_staff', 'spar_pharmacy_id' => $this->a->id, 'is_active' => true,
        ]);
        Auth::login($user);
    }

    private function renewalJourney(SparPharmacy $pharmacy, ?\DateTimeInterface $dueDate, string $consent = 'opted_in'): SparPrescriptionJourney
    {
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => (string) random_int(100000, 999999), 'dependent_code' => '00',
            'first_name' => 'Pat', 'last_name' => 'Test', 'cellphone' => '0721234567',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => $consent,
            'consent_given_at' => $consent === 'opted_in' ? now() : null,
        ]);

        return SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id, 'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => 'R-' . uniqid(), 'status' => 'renewal_due',
            'total_dispenses' => 6, 'dispenses_completed' => 6,
            'start_date' => now()->subMonths(6),
            'renewal_due_date' => $dueDate,
        ]);
    }

    private function spyDispatcher(): object
    {
        $spy = new class implements MessagingChannel {
            public array $patients = [];
            public function key(): string { return 'inapp'; }
            public function canReach(SparPatient $patient): bool { return true; }
            public function send(SparPatient $patient, array $payload): bool
            {
                $this->patients[$patient->id] = true;
                return true;
            }
        };
        config(['spar.channels' => ['inapp']]);
        app()->bind(MessagingDispatcher::class, fn () => new MessagingDispatcher(['inapp' => $spy]));

        return $spy;
    }

    public function test_week_window_excludes_month_only_renewals(): void
    {
        $this->staffAtA();
        $this->renewalJourney($this->a, now()->addDays(3));   // in week
        $this->renewalJourney($this->a, now()->addDays(20));  // in month, not week

        $component = Livewire::test(SparRenewals::class)->set('window', 'week');
        $this->assertCount(1, $component->get('renewals'));

        $component->set('window', 'month');
        $this->assertCount(2, $component->get('renewals'));
    }

    public function test_bulk_reminders_only_reach_consented_patients(): void
    {
        $this->staffAtA();
        $spy = $this->spyDispatcher();
        $this->renewalJourney($this->a, now()->addDays(2), 'opted_in');
        $this->renewalJourney($this->a, now()->addDays(2), 'opted_in');
        $this->renewalJourney($this->a, now()->addDays(2), 'opted_out'); // must be skipped

        Livewire::test(SparRenewals::class)->set('window', 'week')->call('remindAll');

        $this->assertCount(2, $spy->patients, 'Only the two consented patients should be messaged.');
    }

    public function test_remind_one_moves_journey_to_awaiting_patient(): void
    {
        $this->staffAtA();
        $this->spyDispatcher();
        $journey = $this->renewalJourney($this->a, now()->addDays(2), 'opted_in');

        Livewire::test(SparRenewals::class)->set('window', 'week')->call('remindOne', $journey->id);

        $this->assertSame('awaiting_patient', $journey->fresh()->action_status->value);
    }

    public function test_renewals_are_scoped_to_the_actor(): void
    {
        $this->staffAtA();
        // A renewal at pharmacy B must not appear for A staff.
        $this->renewalJourney($this->b, now()->addDays(2));

        $component = Livewire::test(SparRenewals::class)->set('window', 'all');
        $this->assertCount(0, $component->get('renewals'));
    }
}
