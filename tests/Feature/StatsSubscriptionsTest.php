<?php

namespace Tests\Feature;

use App\Enums\CycleStatus;
use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Stats\StatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T7.3 — subscription metrics: active/churn via Craig's 35d+5d rule, MRR, lifecycle + cycle success.
 */
class StatsSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    private StatsService $stats;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stats = app(StatsService::class);
        $this->plan = SubscriptionPlan::create([
            'name' => 'Weight Loss Monthly', 'slug' => 'wl-monthly', 'price' => 45000,
            'billing_cycle' => 'monthly', 'is_active' => true,
        ]);
    }

    private function sub(array $attr): Subscription
    {
        return Subscription::create(array_merge([
            'user_id' => User::factory()->create(['role' => UserRole::Patient])->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ], $attr));
    }

    public function test_active_uses_last_payment_within_inactivity_window(): void
    {
        // Recent payment -> active.
        $this->sub(['status' => 'active', 'last_payment_at' => now()->subDays(3)]);
        // Payment 50 days ago (> 35+5) -> lapsed/churned, not active.
        $this->sub(['status' => 'active', 'last_payment_at' => now()->subDays(50)]);
        // Explicitly cancelled -> churned.
        $this->sub(['status' => 'cancelled', 'last_payment_at' => now()->subDays(2), 'cancelled_at' => now()->subDay()]);

        $m = $this->stats->subscriptions('month');

        $this->assertSame(1, $m['active']);
        $this->assertSame(1, $m['lapsed']);
        $this->assertSame(1, $m['cancelled_total']);
        $this->assertSame(2, $m['churned']); // lapsed + cancelled
        $this->assertSame(35, $m['rule']['inactivity_days']);
        $this->assertSame(5, $m['rule']['grace_days']);
    }

    public function test_mrr_sums_active_plan_prices_monthly_normalised(): void
    {
        $this->sub(['status' => 'active', 'last_payment_at' => now()->subDays(1)]);
        $this->sub(['status' => 'active', 'last_payment_at' => now()->subDays(1)]);

        $m = $this->stats->subscriptions('month');

        // Two active monthly subs @ R450 -> R900 = 90000 cents.
        $this->assertSame(90000, $m['mrr_cents']);
    }

    public function test_mrr_normalises_yearly_plan_to_monthly(): void
    {
        $yearly = SubscriptionPlan::create(['name' => 'Annual', 'slug' => 'annual', 'price' => 120000, 'billing_cycle' => 'yearly', 'is_active' => true]);
        Subscription::create([
            'user_id' => User::factory()->create(['role' => UserRole::Patient])->id,
            'subscription_plan_id' => $yearly->id, 'status' => 'active',
            'starts_at' => now(), 'last_payment_at' => now()->subDays(1),
        ]);

        $m = $this->stats->subscriptions('month');

        // R1200/yr -> R100/mo = 10000 cents.
        $this->assertSame(10000, $m['mrr_cents']);
    }

    public function test_cycle_success_and_failure_counts(): void
    {
        $sub = $this->sub(['status' => 'active', 'last_payment_at' => now()->subDay()]);
        SubscriptionCycle::create(['subscription_id' => $sub->id, 'sequence_no' => 1, 'status' => CycleStatus::Fulfilled->value, 'scheduled_for' => now()->subDays(2)]);
        SubscriptionCycle::create(['subscription_id' => $sub->id, 'sequence_no' => 2, 'status' => CycleStatus::PaymentFailed->value, 'scheduled_for' => now()->subDay()]);

        $m = $this->stats->subscriptions('month');

        $this->assertSame(1, $m['cycles_fulfilled']);
        $this->assertSame(1, $m['cycles_failed']);
    }

    public function test_new_and_cancelled_in_period(): void
    {
        $this->sub(['status' => 'active', 'starts_at' => now(), 'last_payment_at' => now()]);
        $this->sub(['status' => 'cancelled', 'starts_at' => now()->subMonths(4), 'cancelled_at' => now(), 'last_payment_at' => now()->subMonths(4)]);

        $m = $this->stats->subscriptions('month');

        $this->assertGreaterThanOrEqual(1, $m['new_in_period']);
        $this->assertSame(1, $m['cancelled_in_period']);
    }
}
