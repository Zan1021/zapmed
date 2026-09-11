<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Services\SparStatsService;

/**
 * Unified SPAR Insights (merged Stats + Reporting, Phase 8.3).
 *
 * One page, role-scoped and period-filterable. Every metric is resolved through
 * SparStatsService, which is fail-closed on the actor's pharmacy scope — a
 * pharmacy-staff actor can only ever see their own store's numbers, a
 * group-admin only their group, super-admin all. The optional pharmacy filter
 * is intersected with that scope in the service, so it can only ever NARROW,
 * never widen, what the actor may see.
 *
 * Aggregates only — no per-patient PHI is surfaced or exported.
 */
class SparStats extends Component
{
    /** Look-back window in days. */
    public string $period = '30';

    /** Optional single-pharmacy filter (id). Bounded by actor scope. */
    public ?int $pharmacyFilter = null;

    /** Allowed period options (guards against arbitrary query values). */
    private const PERIODS = ['7', '30', '90', '180', '365'];

    public function mount(): void
    {
        // Any authenticated SPAR actor may view Insights for their own scope.
        abort_unless(app(SparIdentityProvider::class)->currentRole() !== null, 403);
    }

    /** Normalised, validated period in days. */
    private function periodDays(): int
    {
        return in_array($this->period, self::PERIODS, true) ? (int) $this->period : 30;
    }

    public function getInsightsProperty(): array
    {
        return app(SparStatsService::class)->insights($this->periodDays(), $this->pharmacyFilter);
    }

    public function getPharmaciesProperty()
    {
        return app(SparStatsService::class)->selectablePharmacies();
    }

    /**
     * Stream the current (scoped, filtered) insights as a CSV download.
     * Reuses the exact scoped payload the page shows — no separate query path,
     * so the export can never leak more than the view.
     */
    public function downloadCsv(): StreamedResponse
    {
        $service = app(SparStatsService::class);
        $insights = $service->insights($this->periodDays(), $this->pharmacyFilter);
        $rows = $service->toCsvRows($insights);

        $filename = 'spar-insights-'.now()->format('Y-m-d').'-'.$this->periodDays().'d.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function render()
    {
        return view('spar::livewire.admin.spar-stats', [
            'data' => $this->insights,
        ])->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
