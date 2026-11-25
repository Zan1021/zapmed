<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\OperationsOverview;
use App\Models\CoachFlag;
use App\Models\CoachingAssignment;
use App\Models\CoachMessage;
use App\Models\DoctorProfile;
use App\Models\DoctorRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WeightEntry;
use App\Services\Operations\OperationsDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4 leftover: the Operations overview (Craig's guide 6.1). Counts and totals only.
 */
class OperationsOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
    }

    private function dash(array $filters = ['month' => '2026-11']): array
    {
        return app(OperationsDashboard::class)->build($this->ops, $filters);
    }

    private function patient(array $a = []): User
    {
        return User::factory()->create(['role' => UserRole::Patient] + $a);
    }

    private function order(User $p, string $when, array $a = []): Order
    {
        return Order::create($a + ['patient_id' => $p->id, 'status' => 'Delivered', 'total_minor' => 100, 'ordered_at' => CarbonImmutable::parse($when, 'Africa/Johannesburg')->utc()]);
    }

    public function test_only_operations_can_build_the_dashboard(): void
    {
        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach, UserRole::PharmacyStaff] as $role) {
            try {
                app(OperationsDashboard::class)->build(User::factory()->create(['role' => $role]));
                $this->fail('Expected 403');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_bad_dates_are_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->dash(['from' => '2026-02-31']);
    }

    public function test_order_counts_and_the_daily_series_have_no_gaps(): void
    {
        $p = $this->patient();
        $this->order($p, '2026-11-25 09:00');
        $this->order($p, '2026-11-25 23:30');   // still today in South African time
        $this->order($p, '2026-11-02 10:00');
        $this->order($p, '2026-10-30 10:00');   // another month

        $o = $this->dash()['orders'];

        $this->assertSame(2, $o['today']);
        $this->assertSame(3, $o['this_month']);
        $this->assertSame(3, $o['in_range']);
        $this->assertCount(30, $o['per_day'], 'Every day of November, including the empty ones.');
        $this->assertSame(2, $o['per_day']['2026-11-25']);
        $this->assertSame(1, $o['per_day']['2026-11-02']);
        $this->assertSame(0, $o['per_day']['2026-11-10']);
    }

    public function test_orders_by_service_use_the_date_range(): void
    {
        $p = $this->patient();
        $this->order($p, '2026-11-03 10:00', ['service_category' => 'Weight Loss']);
        $this->order($p, '2026-11-04 10:00', ['service_category' => 'Weight Loss']);
        $this->order($p, '2026-11-05 10:00', ['service_category' => 'Hair Loss']);
        $this->order($p, '2026-11-06 10:00', ['service_category' => null]);
        $this->order($p, '2026-09-06 10:00', ['service_category' => 'Acne']);

        $this->assertSame(['Weight Loss' => 2, 'Hair Loss' => 1, 'Other' => 1], $this->dash()['orders']['by_category']);
        $this->assertSame(['Acne' => 1], $this->dash(['from' => '2026-09-01', 'to' => '2026-09-30'])['orders']['by_category']);
    }

    public function test_signups_split_active_inactive_and_repeat_versus_one_off(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Repeat', 'slug' => 'od-'.uniqid(), 'price' => 1, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $a = $this->patient(['created_at' => '2026-11-03 10:00:00']);
        $b = $this->patient(['created_at' => '2026-11-04 10:00:00', 'is_active' => false]);
        $c = $this->patient(['created_at' => '2026-05-04 10:00:00']);
        Subscription::create(['user_id' => $a->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'total_paid' => 0, 'payment_count' => 0]);
        Subscription::create(['user_id' => $c->id, 'subscription_plan_id' => $plan->id, 'status' => 'cancelled', 'total_paid' => 0, 'payment_count' => 0]);

        $s = $this->dash()['signups'];

        $this->assertSame(['total' => 3, 'active' => 2, 'inactive' => 1, 'subscription' => 1, 'one_off' => 2], $s['all_time']);
        $this->assertSame(['total' => 2, 'active' => 1, 'inactive' => 1, 'subscription' => 1, 'one_off' => 1], $s['in_range']);
    }

    public function test_revenue_received_and_pending(): void
    {
        $p = $this->patient();
        Payment::create(['patient_id' => $p->id, 'amount' => 30000, 'status' => 'completed', 'paid_at' => CarbonImmutable::parse('2026-11-10 10:00', 'Africa/Johannesburg')->utc()]);
        Payment::create(['patient_id' => $p->id, 'amount' => 20000, 'status' => 'completed', 'paid_at' => CarbonImmutable::parse('2026-10-10 10:00', 'Africa/Johannesburg')->utc()]);
        Payment::create(['patient_id' => $p->id, 'amount' => 5000, 'status' => 'failed']);
        $this->order($p, '2026-11-12 10:00', ['status' => 'PendingPayment', 'total_minor' => 45000]);
        $this->order($p, '2026-11-13 10:00', ['status' => 'PendingPayment', 'total_minor' => 5000]);

        $r = $this->dash()['revenue'];

        $this->assertSame([30000, 1, 50000, 2], [$r['received_cents'], $r['received_count'], $r['pending_cents'], $r['pending_count']]);
        $this->assertSame(1, $r['failed_count']);
    }

    public function test_cancelled_orders_with_reasons_as_counts_and_percentages(): void
    {
        $p = $this->patient();
        $when = CarbonImmutable::parse('2026-11-10 10:00', 'Africa/Johannesburg')->utc();
        $this->order($p, '2026-11-01 10:00', ['status' => 'Cancelled', 'cancelled_at' => $when, 'cancellation_reason' => 'Too Expensive']);
        $this->order($p, '2026-11-01 10:00', ['status' => 'Cancelled', 'cancelled_at' => $when, 'cancellation_reason' => '']);
        $plan = SubscriptionPlan::create(['name' => 'Repeat', 'slug' => 'od-'.uniqid(), 'price' => 1, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        Subscription::create(['user_id' => $p->id, 'subscription_plan_id' => $plan->id, 'status' => 'cancelled', 'cancelled_at' => $when, 'cancellation_reason' => 'too expensive ', 'total_paid' => 0, 'payment_count' => 0]);
        Subscription::create(['user_id' => $p->id, 'subscription_plan_id' => $plan->id, 'status' => 'cancelled', 'cancelled_at' => $when, 'cancellation_reason' => 'Moving overseas', 'total_paid' => 0, 'payment_count' => 0]);

        $c = $this->dash()['cancelled'];

        $this->assertSame([2, 2, 4], [$c['orders'], $c['repeat_orders'], $c['total']]);
        $this->assertSame(['count' => 2, 'percent' => 50], $c['reasons']['too expensive']);
        $this->assertSame(['count' => 1, 'percent' => 25], $c['reasons']['no reason given'] ?? $c['reasons']['No reason given']);
    }

    public function test_unpaid_carts_and_how_many_are_waiting_for_a_reminder(): void
    {
        $p = $this->patient();
        $this->order($p, '2026-11-25 08:00', ['status' => 'PendingPayment', 'total_minor' => 10000]);     // 3 hours: too fresh
        $this->order($p, '2026-11-24 08:00', ['status' => 'PendingPayment', 'total_minor' => 20000]);     // 27 hours
        $this->order($p, '2026-11-24 07:00', ['status' => 'PendingPayment', 'total_minor' => 5000, 'archived_at' => now()]);

        $a = $this->dash()['abandoned'];

        $this->assertSame([2, 30000, 1, 1], [$a['unpaid_now'], $a['unpaid_value_cents'], $a['older_than_a_day'], $a['waiting_for_a_reminder']]);
        $this->assertFalse($a['reminders_on']);
    }

    public function test_active_doctors_are_verified_and_switched_on(): void
    {
        foreach ([[true, true], [false, true], [true, false]] as $i => [$verified, $active]) {
            $d = User::factory()->create(['role' => UserRole::Doctor, 'is_active' => $active]);
            DoctorProfile::create(['user_id' => $d->id, 'hpcsa_number' => 'MP'.$i, 'qualification' => 'MBChB', 'is_verified' => $verified]);
        }
        User::factory()->create(['role' => UserRole::Doctor]);   // no profile

        $this->assertSame(1, $this->dash()['doctors']);
    }

    public function test_coaching_oversight_is_counts_only(): void
    {
        $coach = User::factory()->create(['role' => UserRole::HealthCoach]);
        $patients = collect(range(1, 4))->map(fn () => $this->patient());
        foreach ($patients as $p) {
            CoachingAssignment::create(['patient_id' => $p->id, 'coach_id' => $coach->id, 'started_at' => now()]);
        }
        $when = CarbonImmutable::parse('2026-11-10 10:00', 'Africa/Johannesburg')->utc();
        foreach ([0, 1, 2] as $i) {
            CoachMessage::create(['patient_id' => $patients[$i]->id, 'sender_id' => $coach->id, 'from' => 'coach', 'body' => 'Hi', 'created_at' => $when, 'updated_at' => $when]);
        }
        CoachMessage::create(['patient_id' => $patients[0]->id, 'sender_id' => $patients[0]->id, 'from' => 'member', 'body' => 'Hello back', 'created_at' => $when, 'updated_at' => $when]);
        CoachFlag::create(['patient_id' => $patients[3]->id, 'source' => 'self_report', 'status' => 'open', 'created_at' => $when, 'updated_at' => $when]);
        $req = User::factory()->create(['role' => UserRole::Doctor]);
        DoctorRequest::create(['requester_id' => $coach->id, 'title' => 'x', 'status' => 'pending']);

        $c = $this->dash()['coaching'];

        $this->assertSame(4, $c['active_coached_patients']);
        $this->assertSame([3, 3, 1, 33], [$c['outreach_messages'], $c['patients_reached'], $c['patients_replied'], $c['reply_percent']]);
        $this->assertSame([1, 1], [$c['safety_flags_in_range'], $c['safety_flags_open']]);
        $this->assertSame(1, $c['doctor_requests_open']);
        $this->assertStringNotContainsString('Hello back', json_encode($c), 'No message text.');
    }

    public function test_average_weight_lost_is_hidden_until_three_patients_are_behind_it(): void
    {
        $coach = User::factory()->create(['role' => UserRole::HealthCoach]);
        $patients = collect(range(1, 3))->map(fn () => $this->patient());
        foreach ($patients as $p) {
            CoachingAssignment::create(['patient_id' => $p->id, 'coach_id' => $coach->id, 'started_at' => now()]);
        }
        foreach ([[0, 90.0, 87.0], [1, 100.0, 98.0]] as [$i, $first, $last]) {
            WeightEntry::create(['user_id' => $patients[$i]->id, 'logged_on' => '2026-11-02', 'weight_kg' => $first]);
            WeightEntry::create(['user_id' => $patients[$i]->id, 'logged_on' => '2026-11-20', 'weight_kg' => $last]);
        }

        $w = $this->dash()['coaching']['weight'];
        $this->assertSame(2, $w['patients']);
        $this->assertNull($w['average_kg_lost'], 'Two people: could point at one person.');

        WeightEntry::create(['user_id' => $patients[2]->id, 'logged_on' => '2026-11-02', 'weight_kg' => 80.0]);
        WeightEntry::create(['user_id' => $patients[2]->id, 'logged_on' => '2026-11-22', 'weight_kg' => 79.0]);

        $w = $this->dash()['coaching']['weight'];
        $this->assertSame(3, $w['patients']);
        $this->assertSame(2.0, $w['average_kg_lost'], '(3 + 2 + 1) / 3');
    }

    public function test_the_screen_and_the_sidebar(): void
    {
        $this->actingAs($this->ops)->get(route('admin.operations'))->assertOk()->assertSee('Operations overview');
        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.operations'))->assertForbidden();
        }

        $p = $this->patient();
        $this->order($p, '2026-11-25 09:00', ['service_category' => 'Acne']);
        $this->actingAs($this->ops);

        Livewire::test(OperationsOverview::class)
            ->assertSeeHtml('data-figure="orders-today"')->assertSee('Acne')
            ->set('useMonth', '0')->set('from', '2026-02-31')->assertHasErrors('from')
            ->set('from', '2026-11-01')->set('to', '2026-11-30')->assertHasNoErrors()->assertSeeHtml('data-section="coaching"');
    }

    public function test_the_daily_chart_is_split_by_service_with_no_gaps(): void
    {
        $p = $this->patient();
        $this->order($p, '2026-11-10 10:00', ['service_category' => 'Weight loss']);
        $this->order($p, '2026-11-10 11:00', ['service_category' => 'Weight loss']);
        $this->order($p, '2026-11-10 12:00', ['service_category' => null]);
        $this->order($p, '2026-11-12 10:00', ['service_category' => 'Skincare']);

        $o = $this->dash()['orders'];

        $this->assertSame(['Weight loss' => 2, 'Other' => 1], $o['per_day_by_category']['2026-11-10']);
        $this->assertSame([], $o['per_day_by_category']['2026-11-11']);
        $this->assertSame(['Weight loss', 'Other', 'Skincare'], $o['chart_categories']);
        $this->assertSame(array_keys($o['per_day']), array_keys($o['per_day_by_category']));
    }

    public function test_money_received_is_split_by_service_and_first_versus_repeat(): void
    {
        $p = $this->patient();
        $paid = CarbonImmutable::parse('2026-11-10 10:00', 'Africa/Johannesburg')->utc();
        $first = $this->order($p, '2026-11-09 10:00', ['service_category' => 'Weight loss']);
        $repeat = $this->order($p, '2026-11-09 11:00', ['service_category' => 'Weight loss', 'source_type' => 'subscription', 'source_ref' => '1']);
        Payment::create(['patient_id' => $p->id, 'order_id' => $first->id, 'amount' => 70000, 'status' => 'completed', 'paid_at' => $paid]);
        Payment::create(['patient_id' => $p->id, 'order_id' => $repeat->id, 'amount' => 45000, 'status' => 'completed', 'paid_at' => $paid]);
        Payment::create(['patient_id' => $p->id, 'amount' => 10000, 'status' => 'completed', 'paid_at' => $paid]);

        $r = $this->dash()['revenue'];

        $this->assertSame(['Weight loss' => 115000, 'Other' => 10000], $r['by_category_cents']);
        $this->assertSame([80000, 45000], [$r['first_cents'], $r['repeat_cents']]);
        $this->assertSame($r['received_cents'], $r['first_cents'] + $r['repeat_cents']);
        $this->assertSame($r['received_cents'], array_sum($r['by_category_cents']));
    }

    public function test_the_screen_shows_the_chart_key_and_the_money_split(): void
    {
        $p = $this->patient();
        $paid = CarbonImmutable::parse('2026-11-10 10:00', 'Africa/Johannesburg')->utc();
        $o = $this->order($p, '2026-11-10 10:00', ['service_category' => 'Weight loss']);
        Payment::create(['patient_id' => $p->id, 'order_id' => $o->id, 'amount' => 70000, 'status' => 'completed', 'paid_at' => $paid]);
        $this->actingAs($this->ops);

        Livewire::test(OperationsOverview::class)->set('month', '2026-11')
            ->assertSeeHtml('data-legend')->assertSeeHtml('data-cat="Weight loss"')
            ->assertSeeHtml('data-revenue-cat="Weight loss"')->assertSeeHtml('data-figure="first"')->assertSee('R700.00');
    }}
