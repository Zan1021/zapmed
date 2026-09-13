<?php

namespace App\Livewire\Admin;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Services\Alerts\AlertScanner;
use App\Services\Crm\CrmAiService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Alerts morning-list (Task 4, specs/contro-rebuild/08 §2.3).
 *
 * Ops triage board: the open/acknowledged alert list (the "morning huddle" view), severity/status
 * filters, and per-alert actions — acknowledge, resolve, snooze, comment, assign to self. A manual
 * "Run scan now" button triggers the same detectors the scheduler runs.
 *
 * Task 8 wire-in: an on-demand AI "suggest next action" per alert (CrmAiService::suggestAlertAction) —
 * degrades to a rule-based suggestion with no OpenAI key.
 */
class AlertsBoard extends Component
{
    use WithPagination;

    /** @var array<int,string> */
    public array $severities = ['critical', 'warning', 'informational'];

    /** @var array<int,string> */
    public array $statuses = ['open', 'acknowledged'];

    public ?int $selectedAlertId = null;
    public string $commentBody = '';
    public int $snoozeHours = 24;

    /** AI suggested-action state for the open alert. */
    public ?string $aiAction = null;
    public ?string $aiActionBy = null;   // 'ai' | 'rules'

    public function updatedSeverities(): void
    {
        $this->resetPage();
    }

    public function updatedStatuses(): void
    {
        $this->resetPage();
    }

    public function runScan(AlertScanner $scanner): void
    {
        $result = $scanner->scan();
        session()->flash('message', "Scan complete — {$result['raised']} active, {$result['auto_closed']} auto-closed.");
    }

    public function select(int $id): void
    {
        $this->selectedAlertId = $id;
        $this->reset(['commentBody', 'aiAction', 'aiActionBy']);
    }

    public function closeDrawer(): void
    {
        $this->reset(['selectedAlertId', 'commentBody', 'aiAction', 'aiActionBy']);
    }

    /** Ask the AI (or rule-based fallback) for a suggested next action on the open alert. */
    public function suggestAction(): void
    {
        $alert = Alert::find($this->selectedAlertId);
        if (! $alert) {
            return;
        }

        $result = app(CrmAiService::class)->suggestAlertAction($alert);
        $this->aiAction = $result['action'];
        $this->aiActionBy = $result['generated_by'];
    }

    public function acknowledge(int $id): void
    {
        Alert::find($id)?->acknowledge(auth()->id());
        session()->flash('message', 'Alert acknowledged.');
    }

    public function resolve(int $id): void
    {
        Alert::find($id)?->resolve(auth()->id());
        session()->flash('message', 'Alert resolved.');
        if ($this->selectedAlertId === $id) {
            $this->closeDrawer();
        }
    }

    public function snooze(int $id): void
    {
        $hours = max(1, min(720, $this->snoozeHours));
        Alert::find($id)?->snooze(now()->addHours($hours));
        session()->flash('message', "Alert snoozed for {$hours}h.");
    }

    public function assignToMe(int $id): void
    {
        Alert::find($id)?->update(['assigned_to' => auth()->id()]);
        session()->flash('message', 'Assigned to you.');
    }

    public function addComment(): void
    {
        $alert = Alert::find($this->selectedAlertId);
        if (! $alert) {
            return;
        }

        $this->validate(['commentBody' => 'required|string|max:2000']);
        $alert->comments()->create(['body' => $this->commentBody, 'created_by' => auth()->id()]);
        session()->flash('message', 'Comment added.');
        $this->reset(['commentBody']);
    }

    #[Computed]
    public function alerts()
    {
        return Alert::query()
            ->with('assignee:id,first_name,last_name')
            ->when($this->severities, fn ($q) => $q->whereIn('severity', $this->severities))
            ->when($this->statuses, fn ($q) => $q->whereIn('status', $this->statuses))
            // Critical first, then most recent.
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->latest('raised_at')
            ->paginate(20);
    }

    #[Computed]
    public function selectedAlert(): ?Alert
    {
        return $this->selectedAlertId
            ? Alert::with('comments.author:id,first_name,last_name', 'assignee:id,first_name,last_name')->find($this->selectedAlertId)
            : null;
    }

    /** @return array<string,int> open counts by severity, for the header strip. */
    #[Computed]
    public function openCounts(): array
    {
        $base = Alert::query()->whereIn('status', AlertStatus::activeValues());

        return [
            'critical' => (clone $base)->where('severity', 'critical')->count(),
            'warning' => (clone $base)->where('severity', 'warning')->count(),
            'informational' => (clone $base)->where('severity', 'informational')->count(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.alerts-board', [
            'allSeverities' => AlertSeverity::cases(),
            'allStatuses' => AlertStatus::cases(),
        ]);
    }
}
