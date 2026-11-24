<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\OrderControlsPanel;
use App\Livewire\Patient\MyOrders;
use App\Models\Order;
use App\Models\OrderAuditEvent;
use App\Models\OrderTracking;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Operations\OrderTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4 leftover: courier tracking (Craig's guide 6.2). Operations record it, the patient sees the newest update, and the
 * DPS Laser feed can fill the same table later.
 */
class OperationsTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;
    private User $patient;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-27 12:00', 'Africa/Johannesburg'));
        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
        $this->patient = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $this->patient->id, 'onboarding_complete' => true]);
        $this->order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Despatched', 'total_minor' => 1, 'ordered_at' => now(), 'pharmacy_script_ref' => 'PH-123']);
    }

    private function svc(): OrderTrackingService
    {
        return app(OrderTrackingService::class);
    }

    public function test_tracking_is_recorded_with_defaults_and_the_pharmacy_reference_from_the_order(): void
    {
        $t = $this->svc()->record($this->ops, $this->order, 'DPS-778899', 'in_transit', null, 'https://track.example.test/DPS-778899');

        $this->assertSame('DPS Laser', $t->courier);
        $this->assertSame('PH-123', $t->pharmacy_reference);
        $this->assertSame('manual', $t->source);
        $this->assertSame($this->ops->id, $t->created_by);
        $this->assertSame($t->id, $this->svc()->latest($this->order)->id);
        $this->assertSame(1, OrderAuditEvent::where('order_id', $this->order->id)->where('event', 'tracking')->count());
    }

    public function test_the_newest_update_is_the_current_one_and_history_is_kept(): void
    {
        $this->svc()->record($this->ops, $this->order, 'T1', 'collected', null, null, null, '2026-11-25 08:00');
        $this->svc()->record($this->ops, $this->order, 'T1', 'delivered', null, null, null, '2026-11-26 14:00');
        $this->svc()->record($this->ops, $this->order, 'T1', 'in_transit', null, null, null, '2026-11-25 12:00');   // added late, older time

        $this->assertSame('delivered', $this->svc()->latest($this->order)->status, 'Newest by time, not by when it was typed in.');
        $this->assertSame(3, OrderTracking::where('order_id', $this->order->id)->count());
    }

    public function test_the_link_is_made_from_the_template_when_there_is_one(): void
    {
        config(['operations.tracking.url_template' => 'https://dps.example.test/track?no={number}']);

        $t = $this->svc()->record($this->ops, $this->order, 'AB 12/3', 'created');

        $this->assertSame('https://dps.example.test/track?no=AB%2012%2F3', $t->url);

        $own = $this->svc()->record($this->ops, $this->order, 'AB 12/3', 'created', null, 'https://other.example.test/x');
        $this->assertSame('https://other.example.test/x', $own->url, 'A typed link wins.');
    }

    public function test_validation(): void
    {
        $cases = [
            'tracking_number' => fn () => $this->svc()->record($this->ops, $this->order, '  ', 'created'),
            'tracking_number ' => fn () => $this->svc()->record($this->ops, $this->order, '<script>', 'created'),
            'status' => fn () => $this->svc()->record($this->ops, $this->order, 'T1', 'teleported'),
            'url' => fn () => $this->svc()->record($this->ops, $this->order, 'T1', 'created', null, 'javascript:alert(1)'),
            'url ' => fn () => $this->svc()->record($this->ops, $this->order, 'T1', 'created', null, 'not a link'),
            'tracked_at' => fn () => $this->svc()->record($this->ops, $this->order, 'T1', 'created', null, null, null, 'whenever'),
            'tracked_at ' => fn () => $this->svc()->record($this->ops, $this->order, 'T1', 'created', null, null, null, now()->addDay()->format('Y-m-d H:i')),
            'courier' => fn () => $this->svc()->record($this->ops, $this->order, 'T1', 'created', str_repeat('x', 61)),
        ];
        foreach ($cases as $field => $try) {
            try {
                $try();
                $this->fail("Should be refused: $field");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(trim($field), $e->errors());
            }
        }
        $this->assertSame(0, OrderTracking::count());
    }

    public function test_only_operations_can_record_tracking(): void
    {
        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach, UserRole::PharmacyStaff] as $role) {
            try {
                $this->svc()->record(User::factory()->create(['role' => $role]), $this->order, 'T1', 'created');
                $this->fail('Expected 403');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertSame(0, OrderTracking::count());
    }

    public function test_the_patient_sees_the_newest_update_on_my_orders_in_plain_words(): void
    {
        $this->svc()->record($this->ops, $this->order, 'DPS-1', 'collected', null, null, null, '2026-11-25 08:00');
        $this->svc()->record($this->ops, $this->order, 'DPS-1', 'out_for_delivery', null, 'https://track.example.test/DPS-1', null, '2026-11-26 09:00');

        Livewire::actingAs($this->patient)->test(MyOrders::class)
            ->assertSeeHtml('data-tracking')->assertSee('Out for delivery')->assertSee('DPS Laser DPS-1')->assertSee('Track your delivery')
            ->assertDontSee('Collected from the pharmacy');
    }

    public function test_an_old_style_tracking_link_on_the_order_still_shows(): void
    {
        $this->order->update(['metadata' => ['tracking_url' => 'https://old.example.test/abc']]);

        Livewire::actingAs($this->patient)->test(MyOrders::class)->assertSee('Track your delivery')->assertDontSeeHtml('data-tracking');
    }

    public function test_one_patient_never_sees_another_patients_tracking(): void
    {
        $this->svc()->record($this->ops, $this->order, 'SECRET-TRACK', 'in_transit');
        $other = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $other->id, 'onboarding_complete' => true]);

        Livewire::actingAs($other)->test(MyOrders::class)->assertDontSee('SECRET-TRACK');
    }

    public function test_the_panel_in_the_drawer_saves_tracking(): void
    {
        $this->actingAs($this->ops);

        Livewire::test(OrderControlsPanel::class, ['orderId' => $this->order->id])
            ->assertSeeHtml('data-section="tracking"')
            ->call('recordTracking')->assertHasErrors('tracking_number')
            ->set('trackNumber', 'DPS-42')->set('trackStatus', 'collected')->call('recordTracking')->assertHasNoErrors()
            ->assertSeeHtml('data-current-tracking')->assertSee('Collected from the pharmacy')
            ->assertSeeHtml('data-audit="tracking"');

        $this->assertSame('DPS-42', OrderTracking::first()->tracking_number);
    }
}
