<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Patient\Rewards;
use App\Models\CatalogCoupon;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Patient\PatientRewards;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3.5 Rewards and Community tabs (Craig's guide 3.1), plus the tab bar that ties phase 3 together.
 * A patient only ever sees coupons meant for them.
 */
class PatientRewardsAndTabsTest extends TestCase
{
    use RefreshDatabase;

    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
        $this->patient = $this->newPatient();
    }

    private function newPatient(): User
    {
        $p = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $p->id, 'onboarding_complete' => true]);

        return $p;
    }

    private function coupon(string $code, array $attrs = [], array $metadata = []): CatalogCoupon
    {
        return CatalogCoupon::create(array_merge(['code' => $code, 'type' => 'percent', 'value' => 1000, 'is_cancelled' => false, 'metadata' => $metadata ?: null], $attrs));
    }

    private function codes(?User $u = null): array
    {
        return app(PatientRewards::class)->available($u ?? $this->patient)->pluck('code')->all();
    }

    public function test_a_patient_sees_coupons_made_for_them_and_public_offers_only(): void
    {
        $other = $this->newPatient();
        $this->coupon('MINE10', [], ['patient_id' => (string) $this->patient->id]);
        $this->coupon('THEIRS10', [], ['patient_id' => (string) $other->id]);
        $this->coupon('PUBLIC5', [], ['audience' => 'all']);
        $this->coupon('IMPORTED');   // an imported Contro coupon: no owner, never shown

        $this->assertEqualsCanonicalizing(['MINE10', 'PUBLIC5'], $this->codes());
        $this->assertEqualsCanonicalizing(['THEIRS10', 'PUBLIC5'], $this->codes($other));
    }

    public function test_cancelled_expired_and_used_up_coupons_are_left_out(): void
    {
        $meta = ['patient_id' => (string) $this->patient->id];
        $this->coupon('OK', [], $meta);
        $this->coupon('CANCELLED', ['is_cancelled' => true], $meta);
        $this->coupon('EXPIRED', ['expiry_dt' => now()->subDay()], $meta);
        $this->coupon('USEDUP', ['max_uses' => 1, 'number_of_uses' => 1], $meta);
        $this->coupon('FUTURE', ['expiry_dt' => now()->addDays(10)], $meta);
        $this->coupon('SPARE', ['max_uses' => 3, 'number_of_uses' => 1], $meta);

        $this->assertEqualsCanonicalizing(['OK', 'FUTURE', 'SPARE'], $this->codes());
        $spare = app(PatientRewards::class)->available($this->patient)->firstWhere('code', 'SPARE');
        $this->assertSame(2, $spare['uses_left']);
    }

    public function test_the_value_is_shown_in_plain_words(): void
    {
        $meta = ['patient_id' => (string) $this->patient->id];
        $this->coupon('PCT', ['type' => 'percent', 'value' => 1000], $meta);
        $this->coupon('HALF', ['type' => 'percent', 'value' => 250], $meta);
        $this->coupon('RAND', ['type' => 'fixed', 'value' => 15000], $meta);

        $by = app(PatientRewards::class)->available($this->patient)->keyBy('code');
        $this->assertSame('10% off', $by['PCT']['description']);
        $this->assertSame('2.5% off', $by['HALF']['description']);
        $this->assertSame('R150.00 off', $by['RAND']['description']);
    }

    public function test_assigning_a_coupon_makes_it_private_to_that_patient(): void
    {
        $c = $this->coupon('ASSIGN', [], ['note' => 'keep me']);
        $this->assertSame([], $this->codes());

        app(PatientRewards::class)->assign($c, $this->patient);

        $this->assertSame(['ASSIGN'], $this->codes());
        $this->assertSame('keep me', $c->fresh()->metadata['note'], 'Existing metadata is kept.');
        $this->assertSame([], $this->codes($this->newPatient()));
    }

    public function test_the_rewards_screen(): void
    {
        Livewire::actingAs($this->patient)->test(Rewards::class)->assertSeeHtml('data-empty="coupons"');

        $this->coupon('WELCOME10', ['expiry_dt' => now()->addDays(5)], ['patient_id' => (string) $this->patient->id]);
        $this->coupon('SECRET99', [], ['patient_id' => (string) $this->newPatient()->id]);

        Livewire::actingAs($this->patient)->test(Rewards::class)
            ->assertSee('WELCOME10')->assertSee('10% off')->assertSee('Use by 30 Nov 2026')->assertDontSee('SECRET99');
    }

    public function test_community_is_a_placeholder_page(): void
    {
        $this->actingAs($this->patient)->get(route('patient.community'))->assertOk()->assertSee('on its way');
    }

    public function test_every_tab_loads_for_a_patient_with_the_tab_bar_and_none_for_staff(): void
    {
        foreach (['patient.orders', 'patient.health', 'patient.follow-up', 'patient.notifications', 'patient.rewards', 'patient.community'] as $route) {
            $this->actingAs($this->patient)->get(route($route))->assertOk()
                ->assertSee('data-patient-tabs', false)->assertSee('My Orders')->assertSee('Health Stats')->assertSee('Follow-Up')
                ->assertSee('Notifications')->assertSee('Rewards')->assertSee('Community');

            foreach ([UserRole::Doctor, UserRole::Admin, UserRole::HealthCoach, UserRole::PharmacyStaff] as $role) {
                $this->actingAs(User::factory()->create(['role' => $role]))->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('patient.orders'))->assertRedirect(route('login'));
    }

    public function test_a_patient_who_has_not_finished_onboarding_is_sent_there_first(): void
    {
        $new = User::factory()->create(['role' => UserRole::Patient]);

        $this->actingAs($new)->get(route('patient.orders'))->assertRedirect(route('patient.onboarding'));
    }

    public function test_the_sidebar_links_a_patient_to_their_tabs(): void
    {
        $this->actingAs($this->patient)->get(route('patient.orders'))->assertSee(route('patient.orders'), false)->assertSee('My Orders and Health');
    }
}
