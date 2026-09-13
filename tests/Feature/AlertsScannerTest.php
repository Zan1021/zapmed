<?php

namespace Tests\Feature;

use App\Enums\AlertStatus;
use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\AlertDefinition;
use App\Models\Appointment;
use App\Models\CrmLead;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Alerts\AlertScanner;
use App\Services\Crm\LeadFunnel;
use Database\Seeders\AlertDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 4 — Alerts / SLA scanner + lifecycle. Behaviour + parity.
 */
class AlertsScannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AlertDefinitionSeeder::class);
    }

    private function patient(): User
    {
        return User::factory()->create(['role' => UserRole::Patient]);
    }

    private function scan(): array
    {
        return app(AlertScanner::class)->scan();
    }

    // ---- seed / definitions --------------------------------------------------------------------

    public function test_seeds_the_ten_reference_definitions(): void
    {
        $this->assertSame(10, AlertDefinition::count());
        foreach ([
            'orders.stale', 'orders.stale_critical', 'orders.pending_booking', 'orders.abandoned_carts',
            'payments.failed', 'payments.repeat_failed', 'consults.no_show', 'consults.awaiting_info',
            'subs.churn_risk', 'crm.cold_signup',
        ] as $code) {
            $this->assertDatabaseHas('alerts_definitions', ['code' => $code]);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(AlertDefinitionSeeder::class); // run again
        $this->assertSame(10, AlertDefinition::count());
    }

    // ---- detectors -----------------------------------------------------------------------------

    public function test_failed_payment_raises_a_warning_alert(): void
    {
        $p = $this->patient();
        Payment::create(['patient_id' => $p->id, 'provider' => 'payfast', 'amount' => 5000, 'status' => 'failed']);

        $this->scan();

        $this->assertDatabaseHas('alerts_alerts', [
            'definition_code' => 'payments.failed',
            'severity' => 'warning',
            'status' => 'open',
        ]);
    }

    public function test_stale_order_raises_after_threshold(): void
    {
        $o = Order::create(['patient_id' => $this->patient()->id, 'status' => 'InReview', 'total_minor' => 100, 'ordered_at' => now()->subDays(3)]);
        // Push created_at back so "no status change in 24h" holds (no history rows → uses created_at).
        $o->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->scan();

        $this->assertDatabaseHas('alerts_alerts', ['definition_code' => 'orders.stale', 'subject_id' => (string) $o->id]);
        // 3 days > 72h critical threshold too.
        $this->assertDatabaseHas('alerts_alerts', ['definition_code' => 'orders.stale_critical', 'subject_id' => (string) $o->id]);
    }

    public function test_fresh_order_does_not_raise_stale(): void
    {
        Order::create(['patient_id' => $this->patient()->id, 'status' => 'InReview', 'total_minor' => 100, 'ordered_at' => now()]);
        $this->scan();
        $this->assertDatabaseMissing('alerts_alerts', ['definition_code' => 'orders.stale']);
    }

    public function test_no_show_consult_raises(): void
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $a = Appointment::create([
            'patient_id' => $this->patient()->id, 'doctor_id' => $doctor->id, 'status' => 'no_show',
            'appointment_date' => now()->subDay(), 'start_time' => '09:00', 'end_time' => '09:30', 'fee_amount' => 0,
        ]);

        $this->scan();

        $this->assertDatabaseHas('alerts_alerts', ['definition_code' => 'consults.no_show', 'subject_id' => (string) $a->id]);
    }

    public function test_cold_signup_raises_after_threshold(): void
    {
        $lead = app(LeadFunnel::class)->ensureLead($this->patient());
        app(LeadFunnel::class)->advance($lead, \App\Enums\FunnelStage::SignedUp);
        $lead->forceFill(['stage_entered_at' => now()->subDays(10)])->save();

        $this->scan();

        $this->assertDatabaseHas('alerts_alerts', ['definition_code' => 'crm.cold_signup', 'subject_id' => (string) $lead->id]);
    }

    public function test_abandoned_carts_rolls_up_into_one_alert(): void
    {
        foreach (range(1, 3) as $i) {
            $o = Order::create(['patient_id' => $this->patient()->id, 'status' => 'PendingPayment', 'total_minor' => 100, 'ordered_at' => now()->subDays(2)]);
            $o->forceFill(['created_at' => now()->subDays(2)])->save();
        }

        $this->scan();

        $cohort = Alert::where('definition_code', 'orders.abandoned_carts')->get();
        $this->assertCount(1, $cohort);
        $this->assertSame(3, $cohort->first()->metadata['count']);
    }

    // ---- idempotency / re-raise / auto-resolve -------------------------------------------------

    public function test_rescanning_the_same_condition_is_idempotent(): void
    {
        $p = $this->patient();
        Payment::create(['patient_id' => $p->id, 'provider' => 'payfast', 'amount' => 5000, 'status' => 'failed']);

        $this->scan();
        $this->scan();

        $this->assertSame(1, Alert::where('definition_code', 'payments.failed')->count());
    }

    public function test_auto_resolves_when_condition_clears(): void
    {
        $lead = app(LeadFunnel::class)->ensureLead($this->patient());
        app(LeadFunnel::class)->advance($lead, \App\Enums\FunnelStage::SignedUp);
        $lead->forceFill(['stage_entered_at' => now()->subDays(10)])->save();

        $this->scan();
        $alert = Alert::where('definition_code', 'crm.cold_signup')->firstOrFail();
        $this->assertSame(AlertStatus::Open, $alert->status);

        // Condition clears: lead progresses out of the cold stages.
        app(LeadFunnel::class)->advance($lead->fresh(), \App\Enums\FunnelStage::Paid);
        $this->scan();

        $this->assertSame(AlertStatus::AutoClosed, $alert->fresh()->status);
    }

    public function test_reopening_a_cleared_condition_counts_as_a_re_raise(): void
    {
        $p = $this->patient();
        $payment = Payment::create(['patient_id' => $p->id, 'provider' => 'payfast', 'amount' => 5000, 'status' => 'failed']);

        $this->scan();
        $alert = Alert::where('dedupe_key', "payments.failed:{$payment->id}")->firstOrFail();

        // Manually resolve it (ops handled it), then the condition recurs on next scan.
        $alert->resolve();
        $this->scan();

        $reopened = Alert::where('dedupe_key', "payments.failed:{$payment->id}")
            ->whereIn('status', AlertStatus::activeValues())->firstOrFail();
        $this->assertSame(1, $reopened->re_raise_count);
        $this->assertNotNull($reopened->last_re_raised_at);
    }

    public function test_raise_dedupe_holds_across_concurrent_calls(): void
    {
        $a1 = Alert::raise([
            'definition_code' => 'payments.failed', 'severity' => 'warning', 'title' => 't',
            'subject_type' => 'payment', 'subject_id' => 99, 'dedupe_key' => 'payments.failed:99',
        ]);
        $a2 = Alert::raise([
            'definition_code' => 'payments.failed', 'severity' => 'warning', 'title' => 't',
            'subject_type' => 'payment', 'subject_id' => 99, 'dedupe_key' => 'payments.failed:99',
        ]);

        $this->assertTrue($a1->is($a2));
        $this->assertSame(1, Alert::where('dedupe_key', 'payments.failed:99')->count());
    }
}
