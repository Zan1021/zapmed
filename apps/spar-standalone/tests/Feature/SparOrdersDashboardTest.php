<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Contracts\MessagingChannel;
use Zapmed\SparCore\Livewire\Admin\SparOrders;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;

/**
 * SPAR Close-the-Loop Wave C2 (FR-C2) — Pharmacy Orders dashboard.
 * Staff process orders through the lifecycle; patient gets a consent-gated
 * "ready" alert; a pharmacy cannot see or process another pharmacy's orders.
 */
class SparOrdersDashboardTest extends TestCase
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

    private function staff(SparPharmacy $pharmacy): PharmacyUser
    {
        $user = PharmacyUser::create([
            'name' => 'Nurse Joy', 'email' => 'joy' . uniqid() . '@t.test', 'password' => Hash::make('x'),
            'role' => 'pharmacy_staff', 'spar_pharmacy_id' => $pharmacy->id, 'is_active' => true,
        ]);
        Auth::login($user);

        return $user;
    }

    private function patient(SparPharmacy $pharmacy, string $consent = 'opted_in'): SparPatient
    {
        return SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => (string) random_int(100000, 999999), 'dependent_code' => '00',
            'first_name' => 'Pat', 'last_name' => 'Test', 'cellphone' => '0721234567',
            'is_primary_member' => true, 'is_active' => true,
            'consent_status' => $consent,
            'consent_given_at' => $consent === 'opted_in' ? now() : null,
        ]);
    }

    private function order(SparPharmacy $pharmacy, SparPatient $patient, string $status = 'requested'): SparOrder
    {
        return SparOrder::create([
            'spar_patient_id' => $patient->id, 'spar_pharmacy_id' => $pharmacy->id,
            'type' => 'collection', 'fulfilment_mode' => SparOrder::MODE_COLLECT_PAY_STORE,
            'status' => $status, 'payment_status' => 'pay_at_store',
        ]);
    }

    /** Spy channel to capture consent-gated sends without hitting a provider. */
    private function spyChannel(): object
    {
        $spy = new class implements MessagingChannel {
            public array $sent = [];
            public function key(): string { return 'inapp'; }
            public function canReach(SparPatient $patient): bool { return true; }
            public function send(SparPatient $patient, array $payload): bool
            {
                $this->sent[] = $payload;
                return true;
            }
        };
        // Bind as the dispatcher's only channel via config factory.
        config(['spar.channels' => ['inapp']]);
        app()->bind(\Zapmed\SparCore\Services\MessagingDispatcher::class, fn () =>
            new \Zapmed\SparCore\Services\MessagingDispatcher(['inapp' => $spy]));

        return $spy;
    }

    public function test_staff_moves_an_order_through_the_lifecycle(): void
    {
        $this->staff($this->a);
        $patient = $this->patient($this->a);
        $order = $this->order($this->a, $patient);

        $component = Livewire::test(SparOrders::class)
            ->call('startPreparing', $order->id);
        $this->assertSame('preparing', $order->fresh()->status);

        $component->call('markReady', $order->id);
        $this->assertSame('ready', $order->fresh()->status);

        $component->call('completeOrder', $order->id);
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_ready_sends_a_consent_gated_alert_to_the_patient(): void
    {
        $this->staff($this->a);
        $spy = $this->spyChannel();
        $patient = $this->patient($this->a, 'opted_in');
        $order = $this->order($this->a, $patient, 'preparing');

        Livewire::test(SparOrders::class)->call('markReady', $order->id);

        $this->assertNotEmpty($spy->sent, 'A consented patient should receive the ready alert.');
        $this->assertStringContainsString($order->reference, $spy->sent[0]['body']);
    }

    public function test_non_consented_patient_gets_no_alert(): void
    {
        $this->staff($this->a);
        $spy = $this->spyChannel();
        $patient = $this->patient($this->a, 'opted_out');
        $order = $this->order($this->a, $patient, 'preparing');

        Livewire::test(SparOrders::class)->call('markReady', $order->id);

        $this->assertEmpty($spy->sent, 'A non-consented patient must not be messaged (NFR-1).');
    }

    public function test_pharmacy_cannot_see_or_process_another_pharmacys_order(): void
    {
        // Staff at pharmacy A; an order belongs to pharmacy B.
        $this->staff($this->a);
        $patientB = $this->patient($this->b);
        $orderB = $this->order($this->b, $patientB);

        $component = Livewire::test(SparOrders::class)->set('filter', 'all');

        // Not visible in A staff's scoped list.
        $this->assertFalse($component->get('orders')->contains('id', $orderB->id));

        // And cannot be processed (fail-closed → no state change).
        $component->call('startPreparing', $orderB->id);
        $this->assertSame('requested', $orderB->fresh()->status);
    }
}
