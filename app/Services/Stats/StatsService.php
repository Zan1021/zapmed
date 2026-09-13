<?php

namespace App\Services\Stats;

use App\Services\Analytics\AnalyticsService as FunnelAnalytics;
use App\Services\AnalyticsService as BusinessAnalytics;
use App\Services\Finance\FinanceService;
use Carbon\CarbonImmutable;

/**
 * Task 7 — StatsService: the single facade the exhaustive admin Stats page (T7.7) calls.
 *
 * This does NOT re-implement metrics. It delegates to the already-tested domain services
 * (BusinessAnalytics = revenue/patients/consults/prescriptions/partners; FunnelAnalytics = CRM
 * acquisition funnel/CAC/KPIs; and — added per task — Finance, Subscriptions, Orders, CRM funnel).
 * It exists to give the Stats page ONE cohesive, sectioned read surface with canonical period handling,
 * without merging or breaking the two intentionally-distinct existing Analytics pages.
 *
 * Sections are filled incrementally by T7.2..T7.6; each ships with tests.
 */
class StatsService
{
    public function __construct(
        private readonly BusinessAnalytics $business,
        private readonly FunnelAnalytics $funnel,
        private readonly FinanceService $finance,
    ) {
    }

    /**
     * Resolve a named period into a [from, to] CarbonImmutable window (canonical for the whole page).
     *
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    public function window(string $period = 'month'): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'week' => [$now->startOfWeek(), $now->endOfWeek()],
            'month' => [$now->startOfMonth(), $now->endOfMonth()],
            'year' => [$now->startOfYear(), $now->endOfYear()],
            'all' => [CarbonImmutable::createFromTimestamp(0), $now->endOfDay()],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
    }

    // ── Business overview (delegates to the existing, tested business analytics) ─────────────────

    /** @return array<string,mixed> */
    public function overview(string $period = 'month'): array
    {
        return [
            'revenue' => $this->business->getRevenueSummary($period),
            'revenue_by_type' => $this->business->getRevenueByType($period),
            'profit' => $this->business->getProfitSummary($period),
            'patients' => $this->business->getPatientStats(),
            'consultations' => $this->business->getConsultationStats($period),
            'prescriptions' => $this->business->getPrescriptionStats($period),
        ];
    }

    // ── Acquisition funnel (delegates to the parity funnel analytics) ────────────────────────────

    /** @return array<string,mixed> */
    public function acquisition(string $period = 'month'): array
    {
        [$from, $to] = $this->window($period);

        return [
            'kpi_summary' => $this->funnel->kpiSummary($from, $to),
            'funnel_counts' => $this->funnel->funnelCounts($from, $to),
            'conversion_rates' => $this->funnel->conversionRates($from, $to),
            'cac_cents' => $this->funnel->cac($from, $to),
        ];
    }

    // All sections (overview/acquisition/subscriptions/orders/finance/crmFunnel) implemented.

    // ── Subscriptions (T7.3) — active/churn via Craig's rule, MRR, lifecycle + cycle success ─────

    /**
     * Subscription health metrics. "Active" uses Craig's rule: last payment within
     * active_inactivity_days; a sub is treated as churned once it passes inactivity + grace days
     * (or is explicitly cancelled). MRR = sum of active subscriptions' plan price (monthly-normalised).
     *
     * @return array<string,mixed>
     */
    public function subscriptions(string $period = 'month'): array
    {
        [$from, $to] = $this->window($period);

        $inactivity = (int) config('analytics.subscriptions.active_inactivity_days', 35);
        $grace = (int) config('analytics.subscriptions.active_grace_days', 5);
        $activeSince = CarbonImmutable::now()->subDays($inactivity);
        $churnCutoff = CarbonImmutable::now()->subDays($inactivity + $grace);

        // Active = status active AND last payment within the inactivity window.
        $active = \App\Models\Subscription::query()
            ->where('status', 'active')
            ->where('last_payment_at', '>=', $activeSince)
            ->count();

        // Churned = explicitly cancelled, OR active-status but silent past inactivity+grace (lapsed).
        $cancelled = \App\Models\Subscription::query()->where('status', 'cancelled')->count();
        $lapsed = \App\Models\Subscription::query()
            ->where('status', 'active')
            ->where(function ($q) use ($churnCutoff) {
                $q->whereNull('last_payment_at')->orWhere('last_payment_at', '<', $churnCutoff);
            })
            ->count();
        $churned = $cancelled + $lapsed;

        $totalEverActive = $active + $churned;
        $churnRate = $totalEverActive > 0 ? round($churned / $totalEverActive, 4) : 0.0;

        // MRR: sum of active subs' plan price, normalised to a monthly figure by billing_cycle.
        $mrrCents = (int) \App\Models\Subscription::query()
            ->where('status', 'active')
            ->where('last_payment_at', '>=', $activeSince)
            ->with('plan')
            ->get()
            ->sum(function ($sub) {
                $price = (int) ($sub->plan->price ?? 0);
                return match ($sub->plan->billing_cycle ?? 'monthly') {
                    'yearly', 'annual' => intdiv($price, 12),
                    'quarterly' => intdiv($price, 3),
                    'weekly' => $price * 4,
                    default => $price, // monthly
                };
            });

        // Period lifecycle events.
        $newInPeriod = \App\Models\Subscription::query()->whereBetween('starts_at', [$from, $to])->count();
        $cancelledInPeriod = \App\Models\Subscription::query()->whereBetween('cancelled_at', [$from, $to])->count();

        // Cycle success/fail (3-strike lifecycle) within the period.
        $cyclesFulfilled = \App\Models\SubscriptionCycle::query()
            ->where('status', \App\Enums\CycleStatus::Fulfilled->value)
            ->whereBetween('updated_at', [$from, $to])
            ->count();
        $cyclesFailed = \App\Models\SubscriptionCycle::query()
            ->where('status', \App\Enums\CycleStatus::PaymentFailed->value)
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        return [
            'active' => $active,
            'churned' => $churned,
            'cancelled_total' => $cancelled,
            'lapsed' => $lapsed,
            'churn_rate' => $churnRate,
            'mrr_cents' => $mrrCents,
            'new_in_period' => $newInPeriod,
            'cancelled_in_period' => $cancelledInPeriod,
            'cycles_fulfilled' => $cyclesFulfilled,
            'cycles_failed' => $cyclesFailed,
            'rule' => ['inactivity_days' => $inactivity, 'grace_days' => $grace],
        ];
    }

    // ── Orders (T7.4) — counts by all 25 statuses, by board lane, fulfilment funnel, aging ───────

    /**
     * Order aggregate metrics. Counts every one of the 25 statuses (zero-filled so a status with no
     * orders still shows), rolls them into the 7 board lanes, a fulfilment funnel (key milestones),
     * and PendingPayment aging (how many are stuck awaiting payment beyond the outstanding threshold).
     *
     * @return array<string,mixed>
     */
    public function orders(string $period = 'month'): array
    {
        $statuses = (array) config('orders.statuses', []);
        $lanes = (array) config('orders.board_lanes', []);

        $rawCounts = \App\Models\Order::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        // Zero-fill all 25 statuses so none silently vanish.
        $byStatus = [];
        foreach ($statuses as $s) {
            $byStatus[$s] = (int) ($rawCounts[$s] ?? 0);
        }

        // Roll up into board lanes.
        $byLane = [];
        foreach ($lanes as $key => $lane) {
            $byLane[$key] = [
                'label' => $lane['label'] ?? $key,
                'count' => array_sum(array_map(fn ($s) => $byStatus[$s] ?? 0, $lane['statuses'] ?? [])),
            ];
        }

        $total = array_sum($byStatus);

        // Fulfilment funnel — cumulative "reached at least this milestone" counts.
        $reached = fn (array $ss) => array_sum(array_map(fn ($s) => $byStatus[$s] ?? 0, $ss));
        $fulfilmentFunnel = [
            'payment_received' => $reached(['PaymentReceived', 'PendingBooking', 'PendingConsulation', 'InReview', 'Processing', 'PharmacyProcessing', 'PreparingMedication', 'Despatched', 'Delivered', 'Completed']),
            'in_pharmacy' => $reached(['Processing', 'PharmacyProcessing', 'PreparingMedication', 'Despatched', 'Delivered', 'Completed']),
            'despatched' => $reached(['Despatched', 'Delivered', 'Completed']),
            'delivered' => $reached(['Delivered', 'Completed']),
        ];

        // PendingPayment aging.
        $outstandingHours = (int) config('analytics.finance.outstanding_payments_aged_hours', 24);
        $agedCutoff = CarbonImmutable::now()->subHours($outstandingHours);
        $pendingPaymentTotal = $byStatus['PendingPayment'] ?? 0;
        $pendingPaymentAged = \App\Models\Order::query()
            ->where('status', 'PendingPayment')
            ->where('ordered_at', '<', $agedCutoff)
            ->count();

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'by_lane' => $byLane,
            'fulfilment_funnel' => $fulfilmentFunnel,
            'pending_payment' => [
                'total' => $pendingPaymentTotal,
                'aged_over_threshold' => $pendingPaymentAged,
                'threshold_hours' => $outstandingHours,
            ],
        ];
    }

    // ── Finance (T7.5) — revenue by kind + by service line, reconciliation, refunds, outstanding ─

    /**
     * Finance metrics. Delegates revenue-by-kind to FinanceService; adds a by-service-line breakdown,
     * reconciliation status counts, total refunds/chargebacks/discounts (negative kinds), and
     * outstanding (uncaptured) payments aged past the configured threshold.
     *
     * @return array<string,mixed>
     */
    public function finance(string $period = 'month'): array
    {
        [$from, $to] = $this->window($period);

        // Revenue by kind (delegated to the tested FinanceService).
        $byKind = $this->finance->revenueSummary($from, $to);

        // Revenue by service line (net amount + entry count).
        $byServiceLine = \App\Models\FinanceRevenueEntry::query()
            ->whereBetween('effective_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("COALESCE(service_line, 'unspecified') as line, COALESCE(SUM(amount_cents),0) as total, COUNT(*) as cnt")
            ->groupBy('line')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->line => ['amount_cents' => (int) $r->total, 'entry_count' => (int) $r->cnt]])
            ->toArray();

        // Net revenue (sum of all signed entries — refunds already negative).
        $netCents = \App\Models\FinanceRevenueEntry::query()
            ->whereBetween('effective_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount_cents');

        // Refunds/chargebacks/discounts total (stored negative → report as positive magnitude).
        $refundsCents = abs((int) \App\Models\FinanceRevenueEntry::query()
            ->whereBetween('effective_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('kind', [\App\Enums\RevenueKind::Refund->value, \App\Enums\RevenueKind::Chargeback->value, \App\Enums\RevenueKind::Discount->value])
            ->sum('amount_cents'));

        // Reconciliation status counts (all-time worklist health).
        $reconByStatus = \App\Models\FinanceReconEntry::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        // Outstanding: payments not yet completed, aged past the threshold.
        $outstandingHours = (int) config('analytics.finance.outstanding_payments_aged_hours', 24);
        $agedCutoff = CarbonImmutable::now()->subHours($outstandingHours);
        $outstandingAged = \App\Models\Payment::query()
            ->where('status', 'pending')
            ->where('created_at', '<', $agedCutoff)
            ->count();
        $outstandingCents = (int) \App\Models\Payment::query()
            ->where('status', 'pending')
            ->sum('amount');

        return [
            'by_kind' => $byKind,
            'by_service_line' => $byServiceLine,
            'net_cents' => (int) $netCents,
            'refunds_cents' => $refundsCents,
            'recon_by_status' => $reconByStatus,
            'outstanding' => [
                'pending_amount_cents' => $outstandingCents,
                'aged_over_threshold' => $outstandingAged,
                'threshold_hours' => $outstandingHours,
            ],
        ];
    }

    // ── CRM funnel (T7.6) — 14-stage lead counts, risk-band spread, open flags, dropped/cold ─────

    /**
     * CRM lead-funnel health. Counts leads across all 14 FunnelStages (zero-filled), the risk-band
     * distribution (low/medium/high/critical via each lead's latest risk score), open (uncleared)
     * flags by kind, and dropped/cold leads (terminal DroppedOff stage + leads with no activity for
     * longer than the cold threshold).
     *
     * @return array<string,mixed>
     */
    public function crmFunnel(int $coldDays = 14): array
    {
        // Leads per stage (all 14, zero-filled).
        $rawStages = \App\Models\CrmLead::query()
            ->selectRaw('current_stage, COUNT(*) as c')
            ->groupBy('current_stage')
            ->pluck('c', 'current_stage');

        $byStage = [];
        foreach (\App\Enums\FunnelStage::cases() as $stage) {
            $byStage[$stage->value] = (int) ($rawStages[$stage->value] ?? 0);
        }

        $totalLeads = array_sum($byStage);

        // Risk-band distribution (each lead's latest risk score band; leads with no score = 'unscored').
        $byRiskBand = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0, 'unscored' => 0];
        \App\Models\CrmLead::query()->with('riskScore')->get()->each(function ($lead) use (&$byRiskBand) {
            $band = $lead->riskScore?->band?->value ?? 'unscored';
            $byRiskBand[$band] = ($byRiskBand[$band] ?? 0) + 1;
        });

        // Open (uncleared) flags by kind.
        $openFlagsByKind = \App\Models\CrmFlag::query()
            ->whereNull('cleared_at')
            ->selectRaw('kind, COUNT(*) as c')
            ->groupBy('kind')
            ->pluck('c', 'kind')
            ->toArray();

        // Dropped / cold.
        $dropped = $byStage[\App\Enums\FunnelStage::DroppedOff->value] ?? 0;
        $coldCutoff = CarbonImmutable::now()->subDays($coldDays);
        $cold = \App\Models\CrmLead::query()
            ->whereNotIn('current_stage', [\App\Enums\FunnelStage::DroppedOff->value, \App\Enums\FunnelStage::Churned->value, \App\Enums\FunnelStage::Subscribed->value])
            ->where('last_activity_at', '<', $coldCutoff)
            ->count();

        return [
            'total_leads' => $totalLeads,
            'by_stage' => $byStage,
            'by_risk_band' => $byRiskBand,
            'open_flags_by_kind' => $openFlagsByKind,
            'dropped_off' => $dropped,
            'cold' => $cold,
            'cold_threshold_days' => $coldDays,
        ];
    }
}
