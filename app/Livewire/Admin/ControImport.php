<?php

namespace App\Livewire\Admin;

use App\Models\ImportQuarantine;
use App\Models\UpstreamSyncRun;
use App\Services\Contro\ControClient;
use App\Services\Contro\ControParityReport;
use App\Services\Contro\ControPullService;
use App\Services\Contro\ControReconciler;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Import Ops console (Task 1, specs/contro-rebuild/08-crm-build-spec §3).
 *
 * Admin surface over the Contro import CLI: run/monitor pulls, view the parity report, and work the
 * quarantine queue. Read-only against Contro; the pull button is disabled until creds are configured
 * (pending from Craig). Reconcile/parity/quarantine operate on whatever is already staged.
 */
class ControImport extends Component
{
    use WithPagination;

    public string $quarantineFilter = 'open'; // open|all

    /** True once Contro API creds/base URL are configured (live pull possible). */
    #[Computed]
    public function isConfigured(): bool
    {
        return filled(config('contro.api.base_url'));
    }

    #[Computed]
    public function parity(): array
    {
        return (new ControParityReport())->build();
    }

    #[Computed]
    public function parityClean(): bool
    {
        return (new ControParityReport())->isClean();
    }

    public function pullAll(): void
    {
        if (! $this->isConfigured()) {
            session()->flash('error', 'Contro API is not configured yet (base URL / credentials pending). Cannot pull.');
            return;
        }

        try {
            $runs = (new ControPullService(ControClient::fromConfig()))->pullAll('manual', false);
            $total = array_sum(array_map(fn ($r) => $r->rows_upserted, $runs));
            session()->flash('message', "Pull complete — {$total} row(s) landed into staging.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Pull failed: ' . $e->getMessage());
        }
    }

    public function backfill(): void
    {
        if (! $this->isConfigured()) {
            session()->flash('error', 'Contro API is not configured yet. Cannot backfill.');
            return;
        }

        try {
            $runs = (new ControPullService(ControClient::fromConfig()))->pullAll('manual', true);
            $total = array_sum(array_map(fn ($r) => $r->rows_upserted, $runs));
            session()->flash('message', "Backfill complete — {$total} row(s) landed into staging.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Backfill failed: ' . $e->getMessage());
        }
    }

    public function reconcile(): void
    {
        try {
            $counts = (new ControReconciler())->reconcileAll();
            $total = array_sum($counts);
            session()->flash('message', "Reconcile complete — {$total} row(s) mapped to canonical tables.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Reconcile failed: ' . $e->getMessage());
        }
    }

    public function resolveQuarantine(int $id): void
    {
        $q = ImportQuarantine::findOrFail($id);
        $q->update(['status' => 'resolved', 'resolved_at' => now()]);
        session()->flash('message', "Quarantine #{$id} marked resolved.");
    }

    public function ignoreQuarantine(int $id): void
    {
        $q = ImportQuarantine::findOrFail($id);
        $q->update(['status' => 'ignored', 'resolved_at' => now()]);
        session()->flash('message', "Quarantine #{$id} ignored.");
    }

    public function render()
    {
        $runs = UpstreamSyncRun::latest()->limit(10)->get();

        $quarantine = ImportQuarantine::query()
            ->when($this->quarantineFilter === 'open', fn ($q) => $q->where('status', 'open'))
            ->latest()
            ->paginate(15);

        return view('livewire.admin.contro-import', compact('runs', 'quarantine'));
    }
}
