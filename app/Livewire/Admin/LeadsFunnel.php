<?php

namespace App\Livewire\Admin;

use App\Enums\FunnelStage;
use App\Models\CrmLead;
use App\Models\User;
use App\Services\Crm\LeadFunnel;
use App\Services\Crm\RiskScorer;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Leads / Funnel board (Task 3, specs/contro-rebuild/08 §2.1).
 *
 * A Kanban over crm_leads grouped by the 14-stage funnel, with per-stage counts, a service-line /
 * assignee filter, and a detail drawer to move a lead (via LeadFunnel::advance — immutable events),
 * raise/clear flags, add notes, and recompute the rules-based risk score.
 */
class LeadsFunnel extends Component
{
    public string $serviceLine = '';
    public string $assignee = '';
    public string $search = '';

    public ?int $selectedLeadId = null;

    // Drawer inputs.
    public string $moveTo = '';
    public string $moveNotes = '';
    public string $noteBody = '';
    public bool $notePinned = false;
    public string $flagKind = '';
    public string $flagReason = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['serviceLine', 'assignee', 'search'], true)) {
            $this->selectedLeadId = null;
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['serviceLine', 'assignee', 'search', 'selectedLeadId']);
    }

    public function selectLead(int $leadId): void
    {
        $this->selectedLeadId = $leadId;
        $this->reset(['moveTo', 'moveNotes', 'noteBody', 'notePinned', 'flagKind', 'flagReason']);
    }

    public function closeDrawer(): void
    {
        $this->reset(['selectedLeadId', 'moveTo', 'moveNotes', 'noteBody', 'notePinned', 'flagKind', 'flagReason']);
    }

    public function moveStage(LeadFunnel $funnel): void
    {
        $lead = CrmLead::find($this->selectedLeadId);
        if (! $lead) {
            $this->closeDrawer();
            return;
        }

        $this->validate(['moveTo' => 'required|string']);

        try {
            $funnel->advance($lead, $this->moveTo, [
                'actor' => 'admin:' . auth()->id(),
                'notes' => $this->moveNotes ?: null,
            ]);
            session()->flash('message', "Lead moved to {$this->moveTo}.");
            $this->reset(['moveTo', 'moveNotes']);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function addNote(LeadFunnel $funnel): void
    {
        $lead = CrmLead::find($this->selectedLeadId);
        if (! $lead) {
            return;
        }

        $this->validate(['noteBody' => 'required|string|max:2000']);
        $funnel->addNote($lead, $this->noteBody, $this->notePinned, auth()->id());
        session()->flash('message', 'Note added.');
        $this->reset(['noteBody', 'notePinned']);
    }

    public function raiseFlag(LeadFunnel $funnel): void
    {
        $lead = CrmLead::find($this->selectedLeadId);
        if (! $lead) {
            return;
        }

        $this->validate(['flagKind' => 'required|string']);
        $funnel->raiseFlag($lead, $this->flagKind, $this->flagReason ?: null, auth()->id());
        session()->flash('message', 'Flag raised.');
        $this->reset(['flagKind', 'flagReason']);
    }

    public function clearFlag(int $flagId, LeadFunnel $funnel): void
    {
        $lead = CrmLead::find($this->selectedLeadId);
        $flag = $lead?->flags()->find($flagId);
        if ($flag && $flag->isActive()) {
            $funnel->clearFlag($lead, $flag->kind, auth()->id());
            session()->flash('message', 'Flag cleared.');
        }
    }

    public function recomputeRisk(RiskScorer $scorer): void
    {
        $lead = CrmLead::find($this->selectedLeadId);
        if ($lead) {
            $scorer->score($lead);
            session()->flash('message', 'Risk score recomputed.');
        }
    }

    private function filteredQuery()
    {
        return CrmLead::query()
            ->with(['patient:id,first_name,last_name', 'assignee:id,first_name,last_name', 'riskScore'])
            ->withCount(['activeFlags'])
            ->when($this->serviceLine !== '', fn ($q) => $q->where('service_line', $this->serviceLine))
            ->when($this->assignee !== '', fn ($q) => $q->where('assigned_to', (int) $this->assignee))
            ->when($this->search !== '', function ($q) {
                $term = trim($this->search);
                $q->whereHas('patient', function ($p) use ($term) {
                    $p->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('member_number', 'like', "%{$term}%");
                });
            });
    }

    /** @return array<string,Collection> */
    #[Computed]
    public function columns(): array
    {
        $out = [];
        foreach (FunnelStage::cases() as $stage) {
            $out[$stage->value] = $this->filteredQuery()
                ->where('current_stage', $stage->value)
                ->latest('last_activity_at')
                ->limit(50)
                ->get();
        }

        return $out;
    }

    /** @return array<string,int> */
    #[Computed]
    public function columnCounts(): array
    {
        $counts = [];
        foreach (FunnelStage::cases() as $stage) {
            $counts[$stage->value] = $this->filteredQuery()->where('current_stage', $stage->value)->count();
        }

        return $counts;
    }

    #[Computed]
    public function selectedLead(): ?CrmLead
    {
        if ($this->selectedLeadId === null) {
            return null;
        }

        return CrmLead::with([
            'patient:id,first_name,last_name,email,member_number',
            'assignee:id,first_name,last_name',
            'activeFlags',
            'notes.author:id,first_name,last_name',
            'riskScore',
            'funnelEvents',
        ])->find($this->selectedLeadId);
    }

    #[Computed]
    public function assignableStaff(): Collection
    {
        return User::query()
            ->whereIn('role', [\App\Enums\UserRole::Admin->value, \App\Enums\UserRole::Doctor->value])
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);
    }

    #[Computed]
    public function serviceLines(): Collection
    {
        return CrmLead::query()->whereNotNull('service_line')->distinct()->orderBy('service_line')->pluck('service_line');
    }

    public function render()
    {
        return view('livewire.admin.leads-funnel', [
            'stages' => FunnelStage::cases(),
            'flagKinds' => \App\Enums\CrmFlagKind::cases(),
        ]);
    }
}
