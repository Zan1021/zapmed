<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 4 — Payments extensions for Contro (blueprint §2.6).
 */
class PaymentControExtensionsTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): User
    {
        return User::factory()->create();
    }

    public function test_payment_carries_contro_fields(): void
    {
        $p = Payment::create([
            'patient_id' => $this->patient()->id,
            'amount' => 45000, 'currency' => 'ZAR', 'status' => 'completed',
            'payment_type' => 'medical_aid',
            'retry_count' => 2,
            'is_repeat_charge' => true,
            'medical_aid_claim_status' => 'submitted',
            'failure_reason' => null,
            'upstream_id' => 'pay_1',
        ]);

        $fresh = $p->fresh();
        $this->assertSame('medical_aid', $fresh->payment_type);
        $this->assertSame(2, $fresh->retry_count);
        $this->assertTrue($fresh->is_repeat_charge);
        $this->assertSame('submitted', $fresh->medical_aid_claim_status);
        $this->assertSame('contro', $fresh->upstream_source);
        $this->assertSame('payfast', $fresh->provider); // default gateway
    }

    public function test_zero_amount_pending_payment_is_allowed(): void
    {
        // Contro sends zero-amount pending rows — must NOT be rejected.
        $p = Payment::create([
            'patient_id' => $this->patient()->id,
            'amount' => 0, 'currency' => 'ZAR', 'status' => 'pending',
            'upstream_id' => 'pay_zero',
        ]);

        $this->assertSame(0, $p->fresh()->amount);
    }

    public function test_idempotency_key_is_unique(): void
    {
        $pid = $this->patient()->id;
        Payment::create(['patient_id' => $pid, 'amount' => 100, 'status' => 'pending', 'idempotency_key' => 'idem-1']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Payment::create(['patient_id' => $pid, 'amount' => 100, 'status' => 'pending', 'idempotency_key' => 'idem-1']);
    }

    public function test_upstream_crosswalk_is_idempotent(): void
    {
        $pid = $this->patient()->id;
        Payment::create(['patient_id' => $pid, 'amount' => 100, 'status' => 'pending', 'upstream_id' => 'pay_x', 'upstream_source' => 'contro']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Payment::create(['patient_id' => $pid, 'amount' => 100, 'status' => 'pending', 'upstream_id' => 'pay_x', 'upstream_source' => 'contro']);
    }

    public function test_payment_children_relationships(): void
    {
        $order = Order::create(['patient_id' => $this->patient()->id]);
        $p = Payment::create([
            'patient_id' => $order->patient_id, 'order_id' => $order->id,
            'amount' => 500, 'status' => 'completed',
        ]);

        $p->attempts()->create(['attempt_number' => 1, 'status' => 'failed', 'failure_reason' => 'insufficient_funds']);
        $p->attempts()->create(['attempt_number' => 2, 'status' => 'completed']);
        $p->refunds()->create(['amount_minor' => 500, 'status' => 'completed', 'reason' => 'goodwill']);

        $this->assertSame(2, $p->attempts()->count());
        $this->assertSame(1, $p->refunds()->count());
        $this->assertSame($order->id, $p->order->id);
    }

    public function test_webhook_events_dedup_on_provider_event_id(): void
    {
        PaymentWebhookEvent::create(['provider' => 'payfast', 'provider_event_id' => 'evt_1', 'event_type' => 'ITN']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        PaymentWebhookEvent::create(['provider' => 'payfast', 'provider_event_id' => 'evt_1', 'event_type' => 'ITN']);
    }

    public function test_payment_child_tables_carry_crosswalk_where_expected(): void
    {
        foreach (['payment_attempts', 'refunds'] as $table) {
            $this->assertTrue(
                Schema::hasColumns($table, ['upstream_id', 'upstream_source', 'upstream_synced_at']),
                "{$table} missing crosswalk columns"
            );
        }
        // webhook events are provider callbacks, NOT a Contro recipient — no crosswalk expected.
        $this->assertFalse(Schema::hasColumn('payment_webhook_events', 'upstream_id'));
    }
}
