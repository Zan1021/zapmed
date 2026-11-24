<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\OperationsReportsPage;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Medication;
use App\Models\Order;
use App\Models\PatientNote;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Commerce\LiveOrderBridge;
use App\Services\Doctor\AuthorisationService;
use App\Services\Doctor\FollowUpService;
use App\Services\Doctor\PatientNoteService;
use App\Services\Operations\OperationsReports;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4.1 Operations reports (Craig's guide 6.3). Read-only, admin only, no clinical text, strict dates, CSV that cannot
 * run a formula in a spreadsheet.
 */
class OperationsReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;
    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));   // Wednesday
        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
        $this->doctor = User::factory()->create(['role' => UserRole::Doctor]);
    }

    private function svc(): OperationsReports
    {
        return app(OperationsReports::class);
    }

    private function patient(array $attrs = []): User
    {
        $p = User::factory()->create(['role' => UserRole::Patient] + $attrs);
        Appointment::create(['patient_id' => $p->id, 'doctor_id' => $this->doctor->id, 'type' => 'general', 'status' => 'completed', 'appointment_date' => '2026-11-01', 'start_time' => '09:00', 'end_time' => '09:30', 'fee_amount' => 1]);

        return $p;
    }

    /** @return array{0:Order,1:Subscription} */
    private function repeatOrder(User $patient, string $status = 'Delivered', string $category = 'Weight Loss', int $pack = 28): array
    {
        $plan = SubscriptionPlan::create(['name' => 'Repeat plan', 'slug' => 'rp-'.uniqid(), 'price' => 29900, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $sub = Subscription::create(['user_id' => $patient->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'total_paid' => 0, 'payment_count' => 0, 'repeat_cycle_days' => $pack]);
        $order = app(LiveOrderBridge::class)->onSubscriptionStarted($sub);
        $order->update(['status' => $status, 'service_category' => $category]);

        return [$order->fresh(), $sub];
    }

    private function col(array $report, string $name): array
    {
        $i = array_search($name, $report['columns'], true);

        return array_column($report['rows'], $i);
    }

    public function test_only_admin_can_run_a_report(): void
    {
        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach, UserRole::PharmacyStaff] as $role) {
            try {
                $this->svc()->run(User::factory()->create(['role' => $role]), 'customer_tracker');
                $this->fail('Should be forbidden');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertSame('Customer tracker', $this->svc()->run($this->ops, 'customer_tracker')['title']);
    }

    public function test_an_unknown_report_and_bad_dates_are_refused(): void
    {
        $cases = [
            ['nonsense', [], 'report'],
            ['customer_tracker', ['month' => '2026-13'], 'month'],
            ['customer_tracker', ['month' => 'November'], 'month'],
            ['customer_tracker', ['from' => '2026-02-31'], 'from'],
            ['customer_tracker', ['from' => 'x'], 'from'],
            ['customer_tracker', ['from' => '2026-11-10', 'to' => '2026-11-01'], 'to'],
            ['user_medication', ['state' => 'maybe'], 'state'],
        ];
        foreach ($cases as [$key, $filters, $field]) {
            try {
                $this->svc()->run($this->ops, $key, $filters);
                $this->fail("Should be refused: $field");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }
        }
    }

    public function test_upcoming_repeats_lists_the_month_with_a_monday_or_wednesday_batch_day(): void
    {
        $p1 = $this->patient(['preferred_contact' => 'whatsapp']);
        $p2 = $this->patient();
        [$o1, $s1] = $this->repeatOrder($p1);
        [$o2, $s2] = $this->repeatOrder($p2, 'Delivered', 'Hair Loss');
        $lifecycle = app(SubscriptionLifecycle::class);
        $lifecycle->scheduleNextCycle($s1, CarbonImmutable::parse('2026-11-27 09:30', 'Africa/Johannesburg'));   // Friday -> Monday 30 Nov
        $lifecycle->scheduleNextCycle($s2, CarbonImmutable::parse('2026-12-02 09:30', 'Africa/Johannesburg'));   // outside November

        $r = $this->svc()->run($this->ops, 'upcoming_repeats', ['month' => '2026-11']);

        $this->assertSame([$o1->reference], $this->col($r, 'Order'));
        $this->assertSame(['WhatsApp'], $this->col($r, 'Contact by'));
        $this->assertSame(['2026-11-27'], $this->col($r, 'Repeat date'));
        $this->assertSame(['2026-11-30'], $this->col($r, 'Batch day'), 'Friday goes to Monday.');
        $this->assertSame(['Weight Loss'], $this->col($r, 'Category'));
        $this->assertSame(1, $r['summary']['Repeats this period']);

        $dec = $this->svc()->run($this->ops, 'upcoming_repeats', ['month' => '2026-12']);
        $this->assertSame([$o2->reference], $this->col($dec, 'Order'));
        $this->assertSame(['2026-12-02'], $this->col($dec, 'Batch day'), 'A Wednesday stays put.');
    }

    public function test_batch_days_are_the_next_monday_or_wednesday_on_or_after(): void
    {
        $map = ['2026-11-23' => '2026-11-23', '2026-11-24' => '2026-11-25', '2026-11-25' => '2026-11-25', '2026-11-26' => '2026-11-30', '2026-11-28' => '2026-11-30', '2026-11-29' => '2026-11-30'];
        foreach ($map as $in => $out) {
            $this->assertSame($out, $this->svc()->batchDay(CarbonImmutable::parse($in, 'Africa/Johannesburg'))->format('Y-m-d'), $in);
        }
        config(['operations.reports.batch_weekdays' => [5]]);
        $this->assertSame('2026-11-27', $this->svc()->batchDay(CarbonImmutable::parse('2026-11-25'))->format('Y-m-d'));
    }

    public function test_cancelled_and_non_scheduled_cycles_are_not_upcoming(): void
    {
        [, $s] = $this->repeatOrder($this->patient());
        $lifecycle = app(SubscriptionLifecycle::class);
        $lifecycle->scheduleNextCycle($s, CarbonImmutable::parse('2026-11-27 09:30', 'Africa/Johannesburg'));
        $lifecycle->pause($s->fresh(), null, 'Holiday', $this->ops->id, 'operations');   // cancels the uncharged cycle

        $this->assertSame([], $this->svc()->run($this->ops, 'upcoming_repeats', ['month' => '2026-11'])['rows']);
    }

    public function test_customer_tracker_counts_by_status_and_flags_what_needs_action(): void
    {
        $a = $this->patient();
        [$ok] = $this->repeatOrder($a, 'Delivered');
        [$paused] = $this->repeatOrder($this->patient(), 'Paused');
        [$failed] = $this->repeatOrder($a, 'RepeatPaymentFailed');
        Order::whereIn('id', [$ok->id, $paused->id, $failed->id])->update(['ordered_at' => '2026-11-10']);
        Order::create(['patient_id' => $a->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => '2026-09-01']);   // another month

        $r = $this->svc()->run($this->ops, 'customer_tracker', ['month' => '2026-11']);

        $this->assertCount(3, $r['rows']);
        $this->assertSame(2, $r['summary']['Customers (distinct)']);
        $this->assertSame(2, $r['summary']['Records needing action']);
        $flags = array_combine($this->col($r, 'Order'), $this->col($r, 'Needs action'));
        $this->assertSame(['Yes', 'Yes', ''], [$flags[$paused->reference], $flags[$failed->reference], $flags[$ok->reference]]);

        $only = $this->svc()->run($this->ops, 'customer_tracker', ['month' => '2026-11', 'status' => 'Paused']);
        $this->assertSame([$paused->reference], $this->col($only, 'Order'));
    }

    public function test_authorisation_report_shows_who_is_coming_due_and_who_has_a_follow_up(): void
    {
        $p1 = $this->patient();
        $p2 = $this->patient();
        [$o1] = $this->repeatOrder($p1);
        [$o2] = $this->repeatOrder($p2);
        $a1 = app(AuthorisationService::class)->grant($this->doctor, $o1, '2026-06-15');   // ends 15 Dec
        app(AuthorisationService::class)->grant($this->doctor, $o2, '2026-08-01');          // ends 1 Feb 2027
        app(FollowUpService::class)->request($this->doctor, $o1, 7);

        $r = $this->svc()->run($this->ops, 'authorisations', ['from' => '2026-12-01', 'to' => '2026-12-31']);

        $this->assertSame([$o1->reference], $this->col($r, 'Order'));
        $this->assertSame(['2026-12-15'], $this->col($r, 'Ends'));
        $this->assertSame(['Follow-up open'], $this->col($r, 'Follow-up'));
        $this->assertSame(1, $r['summary']['Authorisations ending in this period']);
        $this->assertSame($a1->doctor->name, $this->col($r, 'Authorised by')[0]);
    }

    public function test_user_medication_report_filters_by_product_and_state_and_shows_no_notes(): void
    {
        $med1 = Medication::create(['name' => 'Product One', 'form' => 'tablet', 'strength' => '5mg', 'is_active' => true]);
        $med2 = Medication::create(['name' => 'Product Two', 'form' => 'tablet', 'strength' => '10mg', 'is_active' => true]);
        $active = $this->patient(['email' => 'active@example.test']);
        $old = $this->patient(['email' => 'old@example.test']);

        foreach ([[$active, $med1, 'signed', '2027-05-01'], [$old, $med1, 'signed', '2026-06-01'], [$active, $med2, 'dispensed', null]] as $i => [$patient, $med, $status, $valid]) {
            $appt = Appointment::where('patient_id', $patient->id)->first();
            $c = Consultation::create(['appointment_id' => $appt->id, 'patient_id' => $patient->id, 'doctor_id' => $this->doctor->id, 'status' => 'completed']);
            $rx = Prescription::create(['reference' => 'RX-OP'.$i, 'consultation_id' => $c->id, 'patient_id' => $patient->id, 'doctor_id' => $this->doctor->id, 'status' => $status, 'valid_until' => $valid, 'notes' => 'CLINICAL DETAIL']);
            PrescriptionItem::create(['prescription_id' => $rx->id, 'medication_id' => $med->id, 'medication_name' => $med->name, 'strength' => '5mg', 'form' => 'tablet', 'dosage' => '1', 'frequency' => 'daily', 'quantity' => 1]);
        }
        app(PatientNoteService::class)->create($this->doctor, $active, PatientNote::CLINICAL, 'CONFIDENTIAL NOTE');

        $all = $this->svc()->run($this->ops, 'user_medication', ['month' => '2026-11']);
        $this->assertCount(3, $all['rows']);

        $one = $this->svc()->run($this->ops, 'user_medication', ['month' => '2026-11', 'product_id' => $med1->id]);
        $this->assertEqualsCanonicalizing(['active@example.test', 'old@example.test'], $this->col($one, 'Email'));

        $activeOnly = $this->svc()->run($this->ops, 'user_medication', ['month' => '2026-11', 'product_id' => $med1->id, 'state' => 'active']);
        $this->assertSame(['active@example.test'], $this->col($activeOnly, 'Email'));
        $expired = $this->svc()->run($this->ops, 'user_medication', ['month' => '2026-11', 'state' => 'expired']);
        $this->assertSame(['old@example.test'], $this->col($expired, 'Email'));

        $text = json_encode($all);
        $this->assertStringNotContainsString('CLINICAL DETAIL', $text);
        $this->assertStringNotContainsString('CONFIDENTIAL NOTE', $text);
    }

    public function test_add_on_client_report_covers_only_the_coach_add_on(): void
    {
        $coachPlan = SubscriptionPlan::create(['name' => 'Health coach', 'slug' => config('coach.plan_slug'), 'price' => 45000, 'billing_cycle' => 'monthly', 'cycle_frequency' => 1]);
        $a = $this->patient();
        $b = $this->patient();
        Subscription::create(['user_id' => $a->id, 'subscription_plan_id' => $coachPlan->id, 'status' => 'active', 'total_paid' => 0, 'payment_count' => 0]);
        Subscription::create(['user_id' => $b->id, 'subscription_plan_id' => $coachPlan->id, 'status' => 'cancelled', 'total_paid' => 0, 'payment_count' => 0]);
        $this->repeatOrder($this->patient());   // an ordinary repeat plan: not an add-on

        $r = $this->svc()->run($this->ops, 'addon_clients', ['month' => '2026-11']);

        $this->assertCount(2, $r['rows']);
        $this->assertSame(['R450.00', 'R450.00'], $this->col($r, 'Charge'));
        $this->assertSame(1, $r['summary']['Active now']);
        $this->assertSame('R450.00', $r['summary']['Monthly value of the active ones']);
    }

    public function test_pause_and_cancel_analysis_splits_by_who_started_it_with_percentages(): void
    {
        $lifecycle = app(SubscriptionLifecycle::class);
        [, $s1] = $this->repeatOrder($this->patient());
        [, $s2] = $this->repeatOrder($this->patient());
        [, $s3] = $this->repeatOrder($this->patient());

        $lifecycle->pause($s1, null, 'Going overseas', $s1->user_id, 'patient');
        $lifecycle->pause($s2, null, 'Stock problem', $this->ops->id, 'operations');
        $lifecycle->cancel($s3, 'Too expensive', 'patient', $s3->user_id);

        $r = $this->svc()->run($this->ops, 'pause_cancel', ['month' => '2026-11']);

        $this->assertCount(3, $r['rows']);
        $this->assertSame(2, $r['summary']['Pauses in total']);
        $this->assertSame('1 (50%)', $r['summary']['Pauses by Patient']);
        $this->assertSame('1 (50%)', $r['summary']['Pauses by Operations']);
        $this->assertSame(1, $r['summary']['Cancels in total']);
        $this->assertSame('1 (100%)', $r['summary']['Cancels by Patient']);
        $this->assertSame('1 (33%)', $r['summary']['Reason: too expensive']);
        $this->assertContains('Operations', $this->col($r, 'Started by'));
    }

    public function test_a_reason_counts_together_whatever_the_capital_letters(): void
    {
        $lifecycle = app(SubscriptionLifecycle::class);
        foreach (['Too Expensive', 'too expensive ', 'TOO EXPENSIVE'] as $reason) {
            [, $s] = $this->repeatOrder($this->patient());
            $lifecycle->cancel($s, $reason, 'patient', $s->user_id);
        }

        $this->assertSame('3 (100%)', $this->svc()->run($this->ops, 'pause_cancel', ['month' => '2026-11'])['summary']['Reason: too expensive']);
    }

    public function test_csv_has_a_header_and_cannot_run_a_formula(): void
    {
        $evil = $this->patient(['first_name' => '=HYPERLINK("http://evil")', 'last_name' => 'X']);
        [$o, $s] = $this->repeatOrder($evil);
        app(SubscriptionLifecycle::class)->scheduleNextCycle($s, CarbonImmutable::parse('2026-11-27 09:30', 'Africa/Johannesburg'));

        $csv = $this->svc()->csv($this->ops, 'upcoming_repeats', ['month' => '2026-11']);

        $this->assertStringStartsWith('Order,Patient,Email,"Contact by",Category', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString($o->reference, $csv);
    }

    public function test_the_export_route_and_page_are_admin_only(): void
    {
        $this->actingAs($this->ops)->get(route('admin.reports'))->assertOk()->assertSee('Operations reports');
        $this->actingAs($this->ops)->get(route('admin.reports.export', ['report' => 'customer_tracker', 'month' => '2026-11']))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertSee('Needs action');

        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get(route('admin.reports'))->assertForbidden();
            $this->actingAs($u)->get(route('admin.reports.export', ['report' => 'customer_tracker']))->assertForbidden();
        }
    }

    public function test_the_screen_switches_reports_shows_summary_and_reports_bad_input(): void
    {
        $this->repeatOrder($this->patient(), 'Paused');
        Order::query()->update(['ordered_at' => '2026-11-10']);

        Livewire::actingAs($this->ops)->test(OperationsReportsPage::class)
            ->set('report', 'customer_tracker')->assertSeeHtml('data-report="customer_tracker"')->assertSee('Records needing action')
            ->set('report', 'pause_cancel')->assertSeeHtml('data-report="pause_cancel"')
            ->set('useMonth', '0')->set('from', '2026-02-31')->assertHasErrors('from')
            ->set('from', '2026-11-01')->set('to', '2026-11-30')->assertHasNoErrors()
            ->set('report', 'user_medication')->assertSee('All products');
    }

    public function test_the_sidebar_links_operations_to_the_reports(): void
    {
        $this->actingAs($this->ops)->get(route('admin.reports'))->assertSee(route('admin.reports'), false)->assertSee('Operations reports');
    }
}
