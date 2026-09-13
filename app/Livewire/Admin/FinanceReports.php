<?php

namespace App\Livewire\Admin;

use App\Enums\ReconStatus;
use App\Models\FinanceReconEntry;
use App\Services\Finance\FinanceService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Finance reports (Task 7, specs/contro-rebuild/08 §2.6) — revenue summary + daily series, the
 * PayFast↔pharmacy reconciliation worklist (match/dispute/write-off), and a CSV export of the
 * revenue ledger. Route admin.finance.
 *
 * All actions are bookkeeping — nothing here moves money.
 */
class FinanceReports extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';

    /** Recon action modal state. */
    public ?int $actionReconId = null;
    public string $actionType = '';   // '', 'dispute', 'write_off'
    public string $actionNote = '';

    public function mount(): void
    {
        $this->dateFrom = CarbonImmutable::now()->startOfMonth()->toDateString();
        $this->dateTo = CarbonImmutable::now()->toDateString();
    }

    private function window(): array
    {
        $from = CarbonImmutable::parse($this->dateFrom ?: 'today')->startOfDay();
        $to = CarbonImmutable::parse($this->dateTo ?: 'today')->endOfDay();
        if ($to->lessThan($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }

    #[Computed]
    public function revenueSummary(): array
    {
        [$from, $to] = $this->window();

        return app(FinanceService::class)->revenueSummary($from, $to);
    }

    #[Computed]
    public function revenueSeries(): array
    {
        [$from, $to] = $this->window();

        return app(FinanceService::class)->revenueSeries($from, $to);
    }

    #[Computed]
    public function reconWorklist()
    {
        return app(FinanceService::class)->reconWorklist();
    }

    // ---- recon actions --------------------------------------------------------------------------

    public function matchRecon(int $reconId): void
    {
        $recon = FinanceReconEntry::find($reconId);
        if (! $recon) {
            session()->flash('error', 'Reconciliation entry no longer exists.');
            return;
        }

        $status = app(FinanceService::class)->match($recon, auth()->id());
        unset($this->reconWorklist);

        session()->flash('message', $status === ReconStatus::Matched
            ? 'Entry matched (within tolerance).'
            : 'Entry graded partial — delta exceeds tolerance; review needed.');
    }

    public function openAction(int $reconId, string $type): void
    {
        $this->actionReconId = $reconId;
        $this->actionType = $type;
        $this->actionNote = '';
    }

    public function cancelAction(): void
    {
        $this->reset(['actionReconId', 'actionType', 'actionNote']);
    }

    public function confirmAction(): void
    {
        $recon = FinanceReconEntry::find($this->actionReconId);
        if (! $recon) {
            session()->flash('error', 'Reconciliation entry no longer exists.');
            $this->cancelAction();
            return;
        }

        $this->validate([
            'actionNote' => 'required|string|max:1000',
        ], [], ['actionNote' => 'note']);

        try {
            if ($this->actionType === 'dispute') {
                app(FinanceService::class)->dispute($recon, $this->actionNote);
                session()->flash('message', 'Entry marked disputed.');
            } elseif ($this->actionType === 'write_off') {
                app(FinanceService::class)->writeOff($recon, $this->actionNote, auth()->id());
                session()->flash('message', 'Entry written off.');
            }
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->reconWorklist);
        $this->cancelAction();
    }

    // ---- CSV export -----------------------------------------------------------------------------

    /**
     * Stream the revenue ledger for the current window as CSV. Uses a streamed response so a large
     * ledger never has to be buffered in memory.
     */
    public function exportRevenueCsv(): StreamedResponse
    {
        [$from, $to] = $this->window();
        $rows = app(FinanceService::class)->revenueExportRows($from, $to);
        $filename = 'revenue_' . $from->toDateString() . '_' . $to->toDateString() . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            $headers = ['effective_date', 'kind', 'revenue_category', 'service_line', 'amount_cents', 'amount_rand', 'currency', 'order_id', 'payment_id', 'subscription_id', 'notes'];
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($h) => $row[$h] ?? '', $headers));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        return view('livewire.admin.finance-reports');
    }
}
