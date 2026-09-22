<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Contracts\MessagingChannel;
use Zapmed\SparCore\Livewire\Admin\SparBroadcast;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\MessagingDispatcher;

/**
 * SPAR Close-the-Loop Wave C4 (FR-C4) — Pharmacy internal broadcast.
 * Reaches ONLY the actor's consented, in-scope patients (NFR-1).
 */
class SparBroadcastTest extends TestCase
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
        Auth::login(PharmacyUser::create([
            'name' => 'Joy', 'email' => 'joy' . uniqid() . '@t.test', 'password' => Hash::make('x'),
            'role' => 'pharmacy_staff', 'spar_pharmacy_id' => $this->a->id, 'is_active' => true,
        ]));
    }

    private function patient(SparPharmacy $pharmacy, string $consent): SparPatient
    {
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => (string) random_int(100000, 999999), 'dependent_code' => '00',
            'first_name' => 'Pat', 'last_name' => 'Test', 'cellphone' => '0721234567',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => $consent,
            'consent_given_at' => $consent === 'opted_in' ? now() : null,
        ]);
        // A journey so the patient resolves within the pharmacy scope.
        SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id, 'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => 'J-' . uniqid(), 'status' => 'active',
            'total_dispenses' => 6, 'dispenses_completed' => 1, 'start_date' => now()->subMonth(),
        ]);

        return $patient;
    }

    private function spyDispatcher(): object
    {
        $spy = new class implements MessagingChannel {
            public array $patients = [];
            public function key(): string { return 'inapp'; }
            public function canReach(SparPatient $patient): bool { return true; }
            public function send(SparPatient $patient, array $payload): bool { $this->patients[$patient->id] = true; return true; }
        };
        config(['spar.channels' => ['inapp']]);
        app()->bind(MessagingDispatcher::class, fn () => new MessagingDispatcher(['inapp' => $spy]));

        return $spy;
    }

    public function test_broadcast_reaches_only_consented_in_scope_patients(): void
    {
        $this->staffAtA();
        $spy = $this->spyDispatcher();

        $c1 = $this->patient($this->a, 'opted_in');
        $c2 = $this->patient($this->a, 'opted_in');
        $this->patient($this->a, 'opted_out');   // consented? no → excluded
        $this->patient($this->b, 'opted_in');     // out of scope → excluded

        Livewire::test(SparBroadcast::class)
            ->set('subject', 'Flu season special')
            ->set('body', '20% off vitamin C this week at your SPAR pharmacy.')
            ->call('send');

        $this->assertEqualsCanonicalizing([$c1->id, $c2->id], array_keys($spy->patients));
    }

    public function test_audience_count_reflects_consented_scope(): void
    {
        $this->staffAtA();
        $this->patient($this->a, 'opted_in');
        $this->patient($this->a, 'opted_out');

        Livewire::test(SparBroadcast::class)->assertSet('subject', '')
            ->assertSee('1'); // one consented patient in the banner
    }

    public function test_validation_requires_subject_and_body(): void
    {
        $this->staffAtA();
        $this->spyDispatcher();

        Livewire::test(SparBroadcast::class)
            ->set('subject', '')
            ->set('body', '')
            ->call('send')
            ->assertHasErrors(['subject', 'body']);
    }
}
