<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Contracts\MessagingChannel;
use Zapmed\SparCore\Enums\SparPatientSignalType;
use Zapmed\SparCore\Livewire\Admin\SparWinBack;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPatientSignal;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\MessagingDispatcher;
use Zapmed\SparCore\Services\SparWinBackService;

/**
 * SPAR Close-the-Loop Wave C5 (FR-C5) — Lost-customer / win-back.
 * Latest opt-out signal → lost queue; win-back is consent-gated + scoped.
 */
class SparWinBackTest extends TestCase
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

    private function patient(SparPharmacy $pharmacy, string $consent = 'opted_in'): SparPatient
    {
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => (string) random_int(100000, 999999), 'dependent_code' => '00',
            'first_name' => 'Pat', 'last_name' => 'Test', 'cellphone' => '0721234567',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => $consent,
            'consent_given_at' => $consent === 'opted_in' ? now() : null,
        ]);
        SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id, 'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => 'J-' . uniqid(), 'status' => 'active',
            'total_dispenses' => 6, 'dispenses_completed' => 1, 'start_date' => now()->subMonth(),
        ]);

        return $patient;
    }

    private function signal(SparPatient $patient, SparPatientSignalType $type): void
    {
        SparPatientSignal::create([
            'spar_patient_id' => $patient->id,
            'signal' => $type,
            'channel' => 'inapp',
        ]);
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

    public function test_latest_opt_out_signal_puts_patient_in_the_queue(): void
    {
        $this->staffAtA();
        $lost = $this->patient($this->a, 'opted_in');
        $this->signal($lost, SparPatientSignalType::StopReminders);

        $ids = app(SparWinBackService::class)->lostPatientIds();
        $this->assertSame([$lost->id], $ids);
    }

    public function test_a_later_positive_signal_removes_them_from_the_queue(): void
    {
        $this->staffAtA();
        $patient = $this->patient($this->a, 'opted_in');
        $this->signal($patient, SparPatientSignalType::StopReminders);
        $this->signal($patient, SparPatientSignalType::YesCollect); // newer, not an opt-out

        $this->assertSame([], app(SparWinBackService::class)->lostPatientIds());
    }

    public function test_win_back_is_consent_gated(): void
    {
        $this->staffAtA();
        $spy = $this->spyDispatcher();

        // Fully opted-out patient whose latest signal is ignore_future.
        $optedOut = $this->patient($this->a, 'opted_out');
        $this->signal($optedOut, SparPatientSignalType::IgnoreFuture);

        Livewire::test(SparWinBack::class)
            ->set('incentive.' . $optedOut->id, '15% off')
            ->call('sendWinBack', $optedOut->id);

        $this->assertEmpty($spy->patients, 'A fully opted-out patient must not be messaged.');
    }

    public function test_win_back_reaches_a_muted_but_consented_patient(): void
    {
        $this->staffAtA();
        $spy = $this->spyDispatcher();

        // stop_reminders but still consent opted_in (muted, not withdrawn).
        $muted = $this->patient($this->a, 'opted_in');
        $this->signal($muted, SparPatientSignalType::StopReminders);

        Livewire::test(SparWinBack::class)
            ->set('incentive.' . $muted->id, 'Come back for a free BP check')
            ->call('sendWinBack', $muted->id);

        $this->assertArrayHasKey($muted->id, $spy->patients);
    }

    public function test_queue_is_scoped_to_the_actor(): void
    {
        $this->staffAtA();
        // Lost customer at pharmacy B — must not appear for A staff.
        $lostB = $this->patient($this->b, 'opted_in');
        $this->signal($lostB, SparPatientSignalType::StopReminders);

        $this->assertSame([], app(SparWinBackService::class)->lostPatientIds());
    }
}
