<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Payment;
use App\Models\User;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T7.1 — CHARACTERIZATION tests for the business App\Services\AnalyticsService (the rich revenue/
 * patients/consults service behind /admin/analytics). This service had NO test coverage; the Stats
 * module (T7.2+) will lean on it, so we pin its CURRENT behaviour first to guarantee no silent number
 * changes during rationalisation.
 *
 * These assert the service's OBSERVED contract as-is (not a redesign). If a later change alters a number,
 * this test must be consciously updated with a reason.
 */
class BusinessAnalyticsCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(AnalyticsService::class);
    }

    private function patient(array $attr = []): User
    {
        return User::factory()->create(array_merge(['role' => UserRole::Patient], $attr));
    }

    public function test_revenue_summary_totals_completed_payments_this_month(): void
    {
        $p = $this->patient();
        Payment::create(['patient_id' => $p->id, 'provider' => 'payfast', 'amount' => 45000, 'currency' => 'ZAR', 'status' => 'completed', 'description' => 'Consultation']);
        Payment::create(['patient_id' => $p->id, 'provider' => 'payfast', 'amount' => 15000, 'currency' => 'ZAR', 'status' => 'completed', 'description' => 'Medication - RX-1']);
        // A pending payment must be excluded.
        Payment::create(['patient_id' => $p->id, 'provider' => 'payfast', 'amount' => 99900, 'currency' => 'ZAR', 'status' => 'pending', 'description' => 'ignored']);

        $summary = $this->svc->getRevenueSummary('month');

        $this->assertSame(60000, $summary['total']);
        $this->assertSame(2, $summary['count']);
        $this->assertSame(30000, $summary['average_per_transaction']);
    }

    public function test_revenue_by_type_splits_consultation_medication_subscription(): void
    {
        $p = $this->patient();
        $appt = Appointment::create([
            'patient_id' => $p->id, 'doctor_id' => $this->patient()->id, 'type' => 'weight-loss',
            'status' => 'completed', 'appointment_date' => now(), 'start_time' => '09:00', 'end_time' => '09:30', 'fee_amount' => 45000,
        ]);
        Payment::create(['patient_id' => $p->id, 'appointment_id' => $appt->id, 'provider' => 'payfast', 'amount' => 45000, 'currency' => 'ZAR', 'status' => 'completed', 'description' => 'Consultation']);
        Payment::create(['patient_id' => $p->id, 'provider' => 'payfast', 'amount' => 15000, 'currency' => 'ZAR', 'status' => 'completed', 'description' => 'Medication - RX-1']);

        $byType = $this->svc->getRevenueByType('month');

        $this->assertSame(45000, $byType['consultations']);
        $this->assertSame(15000, $byType['medications']);
        $this->assertArrayHasKey('subscriptions', $byType);
        $this->assertArrayHasKey('other', $byType);
    }

    public function test_patient_stats_counts_patients_and_growth_shape(): void
    {
        $this->patient();
        $this->patient();

        $stats = $this->svc->getPatientStats();

        $this->assertSame(2, $stats['total']);
        $this->assertArrayHasKey('this_month', $stats);
        $this->assertArrayHasKey('growth', $stats);
    }

    public function test_conversion_funnel_registered_booked_paid_completed_shape(): void
    {
        $funnel = $this->svc->getConversionFunnel();

        $stages = array_column($funnel, 'stage');
        $this->assertSame(['Registered', 'Booked', 'Paid', 'Completed Consultation'], $stages);
        // Registered is always the 100% baseline.
        $this->assertSame(100, $funnel[0]['percent']);
    }

    public function test_consultation_stats_and_no_show_rate_shape(): void
    {
        $stats = $this->svc->getConsultationStats('month');

        foreach (['total', 'completed', 'avg_duration', 'no_shows', 'no_show_rate'] as $key) {
            $this->assertArrayHasKey($key, $stats);
        }
    }

    public function test_prescription_stats_shape(): void
    {
        $stats = $this->svc->getPrescriptionStats('month');

        foreach (['total', 'chronic', 'one_off', 'chronic_ratio', 'avg_value'] as $key) {
            $this->assertArrayHasKey($key, $stats);
        }
    }

    public function test_period_filter_month_excludes_last_month_payment(): void
    {
        $p = $this->patient();
        $thisMonth = Payment::create(['patient_id' => $p->id, 'provider' => 'payfast', 'amount' => 10000, 'currency' => 'ZAR', 'status' => 'completed', 'description' => 'this']);
        $lastMonth = Payment::create(['patient_id' => $p->id, 'provider' => 'payfast', 'amount' => 88800, 'currency' => 'ZAR', 'status' => 'completed', 'description' => 'last']);
        // Backdate the second payment into last month.
        $lastMonth->forceFill(['created_at' => Carbon::now()->subMonthNoOverflow()->startOfMonth()->addDays(2)])->save();

        $summary = $this->svc->getRevenueSummary('month');

        $this->assertSame(10000, $summary['total'], 'month period must exclude last-month payments');
    }
}
