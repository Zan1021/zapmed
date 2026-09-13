<?php

namespace App\Livewire\Admin;

use App\Services\Analytics\AnalyticsService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * CRM Analytics dashboard (Task 7, specs/contro-rebuild/08 §2.6) — funnel counts, step conversion,
 * blended CAC, and headline KPIs for a date window. Read-only; the nightly analytics:snapshot command
 * persists the same figures for trend history.
 *
 * Distinct from the pre-existing App\Livewire\Admin\Analytics (marketing/traffic page); this is the
 * CRM acquisition funnel view. Route admin.crm-analytics.
 */
class AnalyticsDashboard extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount(): void
    {
        $lookback = (int) config('analytics.default_lookback_days', 30);
        $this->dateFrom = CarbonImmutable::now()->subDays($lookback)->toDateString();
        $this->dateTo = CarbonImmutable::now()->toDateString();
    }

    private function window(): array
    {
        $from = CarbonImmutable::parse($this->dateFrom ?: 'today')->startOfDay();
        $to = CarbonImmutable::parse($this->dateTo ?: 'today')->endOfDay();

        // Guard against an inverted range (to before from) so aggregates never silently return zero.
        if ($to->lessThan($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }

    #[Computed]
    public function summary(): array
    {
        [$from, $to] = $this->window();

        return app(AnalyticsService::class)->kpiSummary($from, $to);
    }

    #[Computed]
    public function conversionRates(): array
    {
        [$from, $to] = $this->window();

        return app(AnalyticsService::class)->conversionRates($from, $to);
    }

    public function render()
    {
        return view('livewire.admin.analytics-dashboard');
    }
}
