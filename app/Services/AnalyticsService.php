<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Commission;
use App\Models\Consultation;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Referral;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    // ─── SECTION 1: REVENUE ──────────────────────────────────────────────────

    public function getRevenueSummary(string $period = 'month'): array
    {
        $query = Payment::where('status', 'completed');
        $query = $this->applyPeriodFilter($query, $period);

        $total = $query->sum('amount');
        $count = $query->count();

        // Previous period for comparison
        $prevQuery = Payment::where('status', 'completed');
        $prevQuery = $this->applyPreviousPeriodFilter($prevQuery, $period);
        $prevTotal = $prevQuery->sum('amount');

        $growth = $prevTotal > 0 ? round((($total - $prevTotal) / $prevTotal) * 100, 1) : 0;

        return [
            'total' => $total,
            'count' => $count,
            'growth' => $growth,
            'average_per_transaction' => $count > 0 ? (int) round($total / $count) : 0,
        ];
    }

    public function getRevenueByType(string $period = 'month'): array
    {
        $query = Payment::where('status', 'completed');
        $query = $this->applyPeriodFilter($query, $period);

        $consultations = (clone $query)->whereNotNull('appointment_id')->sum('amount');
        $medications = (clone $query)->whereNull('appointment_id')->where('description', 'like', 'Medication%')->sum('amount');
        $subscriptions = (clone $query)->where('description', 'like', '%subscription%')->sum('amount');
        $other = (clone $query)->sum('amount') - $consultations - $medications - $subscriptions;

        return [
            'consultations' => $consultations,
            'medications' => $medications,
            'subscriptions' => $subscriptions,
            'other' => max(0, $other),
        ];
    }

    public function getRevenueChart(int $months = 12): array
    {
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $revenue = Payment::where('status', 'completed')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('amount');
            $data[] = [
                'label' => $date->format('M Y'),
                'value' => $revenue,
            ];
        }
        return $data;
    }

    public function getProfitSummary(string $period = 'month'): array
    {
        $query = Payout::query();
        $query = $this->applyPeriodFilter($query, $period);

        return [
            'total_revenue' => $query->sum('total_amount'),
            'doctor_payouts' => $query->sum('doctor_amount'),
            'pharmacy_payouts' => $query->sum('pharmacy_amount'),
            'partner_payouts' => $query->sum('partner_amount'),
            'delivery_costs' => $query->sum('delivery_fee'),
            'platform_profit' => $query->sum('platform_amount'),
        ];
    }

    // ─── SECTION 2: PATIENTS & GROWTH ────────────────────────────────────────

    public function getPatientStats(): array
    {
        $total = User::where('role', 'patient')->count();
        $thisMonth = User::where('role', 'patient')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();
        $lastMonth = User::where('role', 'patient')->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year)->count();
        $growth = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : 0;

        return [
            'total' => $total,
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'growth' => $growth,
        ];
    }

    public function getSignupChart(int $months = 12): array
    {
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = User::where('role', 'patient')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            $data[] = ['label' => $date->format('M'), 'value' => $count];
        }
        return $data;
    }

    public function getConversionFunnel(): array
    {
        $registered = User::where('role', 'patient')->count();
        $booked = User::where('role', 'patient')->whereHas('appointments')->count();
        $paid = Payment::where('status', 'completed')->distinct('patient_id')->count('patient_id');
        $completed = Consultation::where('status', 'completed')->distinct('patient_id')->count('patient_id');

        return [
            ['stage' => 'Registered', 'count' => $registered, 'percent' => 100],
            ['stage' => 'Booked', 'count' => $booked, 'percent' => $registered > 0 ? round(($booked / $registered) * 100) : 0],
            ['stage' => 'Paid', 'count' => $paid, 'percent' => $registered > 0 ? round(($paid / $registered) * 100) : 0],
            ['stage' => 'Completed Consultation', 'count' => $completed, 'percent' => $registered > 0 ? round(($completed / $registered) * 100) : 0],
        ];
    }

    public function getPatientDemographics(): array
    {
        $byProvince = User::where('role', 'patient')
            ->whereNotNull('province')
            ->selectRaw('province, count(*) as total')
            ->groupBy('province')
            ->orderByDesc('total')
            ->limit(9)
            ->pluck('total', 'province')
            ->toArray();

        $byGender = User::where('role', 'patient')
            ->whereNotNull('gender')
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->toArray();

        return ['by_province' => $byProvince, 'by_gender' => $byGender];
    }

    // ─── SECTION 3: CONSULTATIONS & DOCTORS ──────────────────────────────────

    public function getConsultationStats(string $period = 'month'): array
    {
        $query = Consultation::query();
        $query = $this->applyPeriodFilter($query, $period);

        $completed = (clone $query)->where('status', 'completed')->count();
        $total = (clone $query)->count();
        $avgDuration = (clone $query)->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->whereNotNull('started_at')
            ->get()
            ->avg(fn ($c) => $c->started_at->diffInMinutes($c->completed_at));

        $noShows = Appointment::query();
        $noShows = $this->applyPeriodFilter($noShows, $period);
        $noShowCount = $noShows->where('status', 'no_show')->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'avg_duration' => round($avgDuration ?? 0),
            'no_shows' => $noShowCount,
            'no_show_rate' => $total > 0 ? round(($noShowCount / $total) * 100, 1) : 0,
        ];
    }

    public function getDoctorPerformance(): array
    {
        return User::where('role', 'doctor')
            ->withCount(['appointments as total_consultations' => fn ($q) => $q->where('status', 'completed')])
            ->withCount(['appointments as this_month_consultations' => fn ($q) => $q->where('status', 'completed')->whereMonth('appointment_date', now()->month)])
            ->get()
            ->map(fn ($doc) => [
                'name' => 'Dr. ' . $doc->first_name . ' ' . $doc->last_name,
                'total' => $doc->total_consultations,
                'this_month' => $doc->this_month_consultations,
            ])
            ->sortByDesc('total')
            ->values()
            ->toArray();
    }

    public function getTreatmentPopularity(): array
    {
        return Appointment::where('status', 'completed')
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type')
            ->toArray();
    }

    // ─── SECTION 4: PRESCRIPTIONS & PHARMACY ─────────────────────────────────

    public function getPrescriptionStats(string $period = 'month'): array
    {
        $query = Prescription::query();
        $query = $this->applyPeriodFilter($query, $period);

        $total = $query->count();
        $chronic = (clone $query)->where('is_chronic', true)->count();
        $avgValue = (clone $query)->avg('total_amount');

        return [
            'total' => $total,
            'chronic' => $chronic,
            'one_off' => $total - $chronic,
            'chronic_ratio' => $total > 0 ? round(($chronic / $total) * 100) : 0,
            'avg_value' => (int) round($avgValue ?? 0),
        ];
    }

    public function getTopMedications(int $limit = 10): array
    {
        return PrescriptionItem::selectRaw('medication_name, count(*) as times_prescribed, sum(quantity) as total_units')
            ->groupBy('medication_name')
            ->orderByDesc('times_prescribed')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // ─── SECTION 5: PARTNERS ─────────────────────────────────────────────────

    public function getPartnerStats(): array
    {
        return Partner::active()
            ->withCount('referrals')
            ->withCount(['referrals as conversions' => fn ($q) => $q->where('status', 'converted')])
            ->withSum('commissions', 'commission_amount')
            ->orderByDesc('commissions_sum_commission_amount')
            ->get()
            ->map(fn ($p) => [
                'name' => $p->name,
                'slug' => $p->slug,
                'referrals' => $p->referrals_count,
                'conversions' => $p->conversions,
                'conversion_rate' => $p->referrals_count > 0 ? round(($p->conversions / $p->referrals_count) * 100, 1) : 0,
                'earned' => $p->commissions_sum_commission_amount ?? 0,
            ])
            ->toArray();
    }

    // ─── SECTION 6: PREDICTIONS ──────────────────────────────────────────────

    public function getPredictions(): array
    {
        // Simple linear projection based on last 3 months
        $revenueHistory = [];
        $patientHistory = [];
        for ($i = 3; $i >= 1; $i--) {
            $date = now()->subMonths($i);
            $revenueHistory[] = Payment::where('status', 'completed')->whereYear('created_at', $date->year)->whereMonth('created_at', $date->month)->sum('amount');
            $patientHistory[] = User::where('role', 'patient')->whereYear('created_at', $date->year)->whereMonth('created_at', $date->month)->count();
        }

        $avgRevenueGrowth = count($revenueHistory) >= 2 ? ($revenueHistory[2] - $revenueHistory[0]) / 2 : 0;
        $avgPatientGrowth = count($patientHistory) >= 2 ? ($patientHistory[2] - $patientHistory[0]) / 2 : 0;

        $lastRevenue = end($revenueHistory) ?: 0;
        $lastPatients = end($patientHistory) ?: 0;

        return [
            'next_month_revenue' => max(0, (int) ($lastRevenue + $avgRevenueGrowth)),
            'next_month_patients' => max(0, (int) ($lastPatients + $avgPatientGrowth)),
            'revenue_trend' => $avgRevenueGrowth > 0 ? 'growing' : ($avgRevenueGrowth < 0 ? 'declining' : 'stable'),
            'top_growing_treatment' => $this->getTopGrowingTreatment(),
            'marketing_suggestion' => $this->getMarketingSuggestion(),
        ];
    }

    private function getTopGrowingTreatment(): string
    {
        // Compare this month vs last month bookings per type
        $thisMonth = Appointment::whereMonth('created_at', now()->month)->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');
        $lastMonth = Appointment::whereMonth('created_at', now()->subMonth()->month)->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');

        $growth = [];
        foreach ($thisMonth as $type => $count) {
            $prev = $lastMonth->get($type, 0);
            $growth[$type] = $prev > 0 ? (($count - $prev) / $prev) * 100 : 100;
        }

        arsort($growth);
        return array_key_first($growth) ?? 'general';
    }

    private function getMarketingSuggestion(): string
    {
        $topTreatment = $this->getTopGrowingTreatment();
        $suggestions = [
            'weight-loss' => 'Weight loss is trending — increase ad spend on GLP-1/Ozempic keywords.',
            'erectile-dysfunction-treatment' => 'ED treatment demand growing — target men 30-55 on social media.',
            'acne-treatment' => 'Skincare demand up — partner with beauty influencers.',
            'general' => 'GP consultations steady — highlight convenience and sick notes in marketing.',
        ];
        return $suggestions[$topTreatment] ?? 'Focus on retargeting existing visitors who didn\'t convert.';
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    private function applyPeriodFilter($query, string $period)
    {
        return match ($period) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->where('created_at', '>=', now()->startOfWeek()),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'year' => $query->whereYear('created_at', now()->year),
            'all' => $query,
            default => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
        };
    }

    private function applyPreviousPeriodFilter($query, string $period)
    {
        return match ($period) {
            'today' => $query->whereDate('created_at', today()->subDay()),
            'week' => $query->whereBetween('created_at', [now()->subWeeks(2)->startOfWeek(), now()->subWeek()->startOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year),
            'year' => $query->whereYear('created_at', now()->subYear()->year),
            default => $query->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year),
        };
    }
}
