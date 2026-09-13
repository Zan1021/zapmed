<?php

namespace App\Livewire\Admin;

use App\Enums\DsarStatus;
use App\Models\ComplianceDsar;
use App\Models\RetentionScheduleItem;
use App\Services\Compliance\ComplianceService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Compliance console (Task 9, specs/contro-rebuild/08 §2.8) — the POPIA ops surface.
 *
 * Two panes: the DSAR queue (acknowledge → start → complete/reject, with overdue highlighting against
 * the 30-day SLA) and the retention schedule (place/release legal hold, run due items). All actions go
 * through ComplianceService, which guards the status machines and writes the append-only audit trail.
 */
class ComplianceConsole extends Component
{
    use WithPagination;

    public string $tab = 'dsars'; // dsars | retention

    public string $dsarFilter = 'open'; // open, overdue, all

    public ?int $rejectId = null;
    public string $rejectReason = '';

    public ?int $holdId = null;
    public string $holdReason = '';

    private function svc(): ComplianceService
    {
        return app(ComplianceService::class);
    }

    // ---- DSAR actions ---------------------------------------------------------------------------

    public function acknowledge(int $id): void
    {
        $this->runDsar($id, fn (ComplianceDsar $d) => $this->svc()->acknowledgeDsar($d, auth()->id()), 'DSAR acknowledged.');
    }

    public function start(int $id): void
    {
        $this->runDsar($id, fn (ComplianceDsar $d) => $this->svc()->startDsar($d, auth()->id()), 'DSAR moved to in-progress.');
    }

    public function complete(int $id): void
    {
        $this->runDsar($id, fn (ComplianceDsar $d) => $this->svc()->completeDsar($d, auth()->id()), 'DSAR completed.');
    }

    public function openReject(int $id): void
    {
        $this->rejectId = $id;
        $this->rejectReason = '';
    }

    public function cancelReject(): void
    {
        $this->reset(['rejectId', 'rejectReason']);
    }

    public function confirmReject(): void
    {
        $this->validate(['rejectReason' => 'required|string|max:1000'], [], ['rejectReason' => 'reason']);
        $this->runDsar($this->rejectId, fn (ComplianceDsar $d) => $this->svc()->rejectDsar($d, $this->rejectReason, auth()->id()), 'DSAR rejected.');
        $this->cancelReject();
    }

    private function runDsar(?int $id, callable $action, string $ok): void
    {
        $dsar = ComplianceDsar::find($id);
        if (! $dsar) {
            session()->flash('error', 'DSAR no longer exists.');
            return;
        }
        try {
            $action($dsar);
            session()->flash('message', $ok);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    // ---- retention actions ----------------------------------------------------------------------

    public function runRetention(int $id): void
    {
        $item = RetentionScheduleItem::find($id);
        if (! $item) {
            session()->flash('error', 'Retention item no longer exists.');
            return;
        }
        try {
            $this->svc()->completeRetention($item, auth()->id());
            session()->flash('message', 'Retention item processed.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function releaseHold(int $id): void
    {
        $item = RetentionScheduleItem::find($id);
        if ($item) {
            $this->svc()->releaseLegalHold($item, auth()->id());
            session()->flash('message', 'Legal hold released.');
        }
    }

    public function openHold(int $id): void
    {
        $this->holdId = $id;
        $this->holdReason = '';
    }

    public function cancelHold(): void
    {
        $this->reset(['holdId', 'holdReason']);
    }

    public function confirmHold(): void
    {
        $this->validate(['holdReason' => 'required|string|max:1000'], [], ['holdReason' => 'reason']);
        $item = RetentionScheduleItem::find($this->holdId);
        if ($item) {
            $this->svc()->placeLegalHold($item, $this->holdReason, auth()->id());
            session()->flash('message', 'Legal hold placed.');
        }
        $this->cancelHold();
    }

    // ---- data -----------------------------------------------------------------------------------

    #[Computed]
    public function dsars()
    {
        return ComplianceDsar::query()
            ->with('principal:id,first_name,last_name')
            ->when($this->dsarFilter === 'open', fn ($q) => $q->open())
            ->when($this->dsarFilter === 'overdue', fn ($q) => $q->open()->where('due_at', '<', now()))
            ->latest('received_at')
            ->paginate(15);
    }

    #[Computed]
    public function retentionItems()
    {
        return RetentionScheduleItem::query()
            ->with('policy:id,data_class,action')
            ->orderBy('due_at')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.admin.compliance-console');
    }
}
