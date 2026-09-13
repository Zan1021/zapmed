<?php

namespace App\Services\Analytics;

use App\Enums\FunnelEventKind;
use App\Models\AnalyticsAdSpend;
use App\Models\AnalyticsAttribution;
use App\Models\AnalyticsFunnelEvent;
use App\Models\AnalyticsKpiSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Analytics read/write service — parity with Mark's analytics module (SummaryRepo / SpendRepo /
 * attribution capture).
 *
 * Writes: recordEvent (append-only funnel log), captureAttribution (first-touch preserved,
 * last-touch updated), recordSpend. Reads: funnelCounts, conversionRates, cac, kpiSummary.
 * snapshot() persists a daily KPI row for fast dashboards.
 */
class AnalyticsService
{
    /**
     * Append a funnel event. Anonymous (pre-signup) events pass principalId=null + a visitorId.
     *
     * @param  array<string,mixed>  $properties
     */
    public function recordEvent(
        FunnelEventKind $kind,
        ?int $principalId = null,
        ?string $visitorId = null,
        ?string $serviceLine = null,
        ?string $customLabel = null,
        array $properties = [],
        ?\DateTimeInterface $occurredAt = null,
    ): AnalyticsFunnelEvent {
        return AnalyticsFunnelEvent::create([
            'principal_id' => $principalId,
            'visitor_id' => $visitorId,
            'kind' => $kind->value,
            'custom_label' => $kind === FunnelEventKind::Custom ? $customLabel : null,
            'service_line' => $serviceLine,
            'properties' => $properties ?: null,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * Capture marketing attribution for a patient. first_* is written once and NEVER overwritten;
     * last_* always reflects the most recent touch. Idempotent-friendly (upsert on principal_id).
     *
     * @param  array<string,string|null>  $touch  keys: source, medium, campaign, referrer, landing_page, visitor_id
     */
    public function captureAttribution(int $principalId, array $touch): AnalyticsAttribution
    {
        $row = AnalyticsAttribution::find($principalId);

        if (! $row) {
            return AnalyticsAttribution::create([
                'principal_id' => $principalId,
                'visitor_id' => $touch['visitor_id'] ?? null,
                'first_source' => $touch['source'] ?? null,
                'first_medium' => $touch['medium'] ?? null,
                'first_campaign' => $touch['campaign'] ?? null,
                'first_referrer' => $touch['referrer'] ?? null,
                'last_source' => $touch['source'] ?? null,
                'last_medium' => $touch['medium'] ?? null,
                'last_campaign' => $touch['campaign'] ?? null,
                'landing_page' => $touch['landing_page'] ?? null,
                'captured_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Existing patient: only update last-touch fields; leave first-touch intact.
        $row->fill([
            'last_source' => $touch['source'] ?? $row->last_source,
            'last_medium' => $touch['medium'] ?? $row->last_medium,
            'last_campaign' => $touch['campaign'] ?? $row->last_campaign,
            'updated_at' => now(),
        ])->save();

        return $row;
    }

    /** Record an ad-spend line for CAC. */
    public function recordSpend(
        \DateTimeInterface $periodStart,
        \DateTimeInterface $periodEnd,
        string $channel,
        int $amountCents,
        ?string $campaign = null,
        ?int $createdBy = null,
        array $metadata = [],
    ): AnalyticsAdSpend {
        return AnalyticsAdSpend::create([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'channel' => $channel,
            'campaign' => $campaign,
            'amount_cents' => $amountCents,
            'created_by' => $createdBy,
            'metadata' => $metadata ?: null,
        ]);
    }

    // ---- reads ----------------------------------------------------------------------------------

    /**
     * Count of each funnel step within a window (the acquisition funnel, in order).
     *
     * @return array<string,int>  kind value => count
     */
    public function funnelCounts(\DateTimeInterface $since, \DateTimeInterface $until): array
    {
        $counts = AnalyticsFunnelEvent::query()
            ->between($since, $until)
            ->selectRaw('kind, COUNT(*) as c')
            ->groupBy('kind')
            ->pluck('c', 'kind');

        $out = [];
        foreach (FunnelEventKind::funnelOrder() as $kind) {
            $out[$kind->value] = (int) ($counts[$kind->value] ?? 0);
        }

        return $out;
    }

    /**
     * Step-to-step conversion rates (each step ÷ the prior step), as fractions 0..1.
     *
     * @return array<string,float>  "from→to" => rate
     */
    public function conversionRates(\DateTimeInterface $since, \DateTimeInterface $until): array
    {
        $counts = $this->funnelCounts($since, $until);
        $steps = FunnelEventKind::funnelOrder();

        $rates = [];
        for ($i = 1; $i < count($steps); $i++) {
            $prev = $counts[$steps[$i - 1]->value] ?? 0;
            $curr = $counts[$steps[$i]->value] ?? 0;
            $key = $steps[$i - 1]->value . '→' . $steps[$i]->value;
            $rates[$key] = $prev > 0 ? round($curr / $prev, 4) : 0.0;
        }

        return $rates;
    }

    /**
     * Blended CAC for a window = total ad spend ÷ new patients acquired (sign_up_complete events).
     * Returns cents-per-acquisition, or null when there were no acquisitions (avoid /0).
     */
    public function cac(\DateTimeInterface $since, \DateTimeInterface $until): ?int
    {
        $spend = (int) AnalyticsAdSpend::query()
            ->where('period_start', '>=', Carbon::parse($since)->toDateString())
            ->where('period_end', '<=', Carbon::parse($until)->toDateString())
            ->sum('amount_cents');

        $newPatients = AnalyticsFunnelEvent::query()
            ->ofKind(FunnelEventKind::SignUpComplete)
            ->between($since, $until)
            ->count();

        return $newPatients > 0 ? (int) round($spend / $newPatients) : null;
    }

    /**
     * Headline KPIs for a window: new patients, conversion (signup→first payment), activation
     * (signup→consult complete), CAC. Mirrors Mark's /v1/analytics/summary.
     *
     * @return array<string,mixed>
     */
    public function kpiSummary(?\DateTimeInterface $since = null, ?\DateTimeInterface $until = null): array
    {
        $lookback = (int) config('analytics.default_lookback_days', 30);
        $since ??= CarbonImmutable::now()->subDays($lookback);
        $until ??= CarbonImmutable::now();

        $counts = $this->funnelCounts($since, $until);
        $signups = $counts[FunnelEventKind::SignUpComplete->value] ?? 0;

        $rate = fn (int $num) => $signups > 0 ? round($num / $signups, 4) : 0.0;

        return [
            'since' => Carbon::parse($since)->toDateTimeString(),
            'until' => Carbon::parse($until)->toDateTimeString(),
            'new_patients' => $signups,
            'conversion_to_first_payment' => $rate($counts[FunnelEventKind::FirstPayment->value] ?? 0),
            'activation_to_consult_complete' => $rate($counts[FunnelEventKind::ConsultComplete->value] ?? 0),
            'subscribed' => $counts[FunnelEventKind::Subscribed->value] ?? 0,
            'cac_cents' => $this->cac($since, $until),
            'funnel' => $counts,
        ];
    }

    /**
     * Compute + persist a daily KPI snapshot for a date (dimension 'all'/'all' unless sliced).
     * Idempotent: re-running for the same date/slice overwrites the row.
     */
    public function snapshot(?\DateTimeInterface $date = null, string $dimension = 'all', string $dimensionValue = 'all'): AnalyticsKpiSnapshot
    {
        $day = CarbonImmutable::parse($date ?? now())->startOfDay();
        $metrics = $this->kpiSummary($day, $day->endOfDay());

        // Composite-key model (snapshot_date, dimension, dimension_value) with a date cast — a plain
        // updateOrCreate can't reliably re-find the row across the cast boundary, so match explicitly.
        $existing = AnalyticsKpiSnapshot::query()
            ->whereDate('snapshot_date', $day->toDateString())
            ->where('dimension', $dimension)
            ->where('dimension_value', $dimensionValue)
            ->first();

        if ($existing) {
            $existing->fill(['metrics' => $metrics, 'computed_at' => now()])->save();

            return $existing;
        }

        return AnalyticsKpiSnapshot::create([
            'snapshot_date' => $day->toDateString(),
            'dimension' => $dimension,
            'dimension_value' => $dimensionValue,
            'metrics' => $metrics,
            'computed_at' => now(),
        ]);
    }

    /**
     * Recent snapshots for the trend view.
     *
     * @return Collection<int,AnalyticsKpiSnapshot>
     */
    public function recentSnapshots(int $days = 30, string $dimension = 'all', string $dimensionValue = 'all'): Collection
    {
        return AnalyticsKpiSnapshot::query()
            ->where('dimension', $dimension)
            ->where('dimension_value', $dimensionValue)
            ->where('snapshot_date', '>=', CarbonImmutable::now()->subDays($days)->toDateString())
            ->orderBy('snapshot_date')
            ->get();
    }
}
