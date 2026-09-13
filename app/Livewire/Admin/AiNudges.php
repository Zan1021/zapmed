<?php

namespace App\Livewire\Admin;

use App\Enums\NudgeStatus;
use App\Models\CrmLead;
use App\Models\CrmNudge;
use App\Services\Crm\CrmAiService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * AI Nudges console (Task 8, specs/contro-rebuild/08 §2.7).
 *
 * Ops review queue for AI-/rule-drafted outreach: generate a draft for a lead, then approve → (send)
 * or dismiss. Nothing is sent automatically — approval is an explicit human step, and the "send" here
 * only records intent (the notifications layer dispatches in live flow).
 *
 * When no OpenAI key is configured, drafting still works via the deterministic templated fallback, so
 * the console is fully usable with zero AI dependency (generated_by shows 'rules' vs 'ai').
 */
class AiNudges extends Component
{
    use WithPagination;

    public string $filter = 'pending'; // pending, draft, approved, sent, dismissed, all

    /** Lead id to draft a nudge for (from the quick-draft box). */
    public string $draftLeadId = '';
    public string $draftKind = 'reengagement';

    public ?int $dismissId = null;
    public string $dismissReason = '';

    private function ai(): CrmAiService
    {
        return app(CrmAiService::class);
    }

    #[Computed]
    public function aiConfigured(): bool
    {
        return $this->ai()->isConfigured();
    }

    public function draft(): void
    {
        $this->validate([
            'draftLeadId' => 'required|integer',
            'draftKind' => 'required|in:reengagement,cross_sell,winback,checkin',
        ]);

        $lead = CrmLead::find((int) $this->draftLeadId);
        if (! $lead) {
            session()->flash('error', 'Lead not found.');
            return;
        }

        $nudge = $this->ai()->draftNudge($lead, $this->draftKind);
        $this->reset(['draftLeadId', 'draftKind']);
        $this->resetPage();

        session()->flash('message', "Draft nudge created ({$nudge->generated_by}). Review and approve before sending.");
    }

    public function approve(int $id): void
    {
        $nudge = CrmNudge::find($id);
        if (! $nudge) {
            session()->flash('error', 'Nudge no longer exists.');
            return;
        }

        try {
            $this->ai()->approveNudge($nudge, auth()->id());
            session()->flash('message', 'Nudge approved. It can now be sent.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function send(int $id): void
    {
        $nudge = CrmNudge::find($id);
        if (! $nudge) {
            session()->flash('error', 'Nudge no longer exists.');
            return;
        }

        try {
            $this->ai()->markNudgeSent($nudge);
            session()->flash('message', 'Nudge marked as sent (dispatch handled by notifications in live flow).');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openDismiss(int $id): void
    {
        $this->dismissId = $id;
        $this->dismissReason = '';
    }

    public function cancelDismiss(): void
    {
        $this->reset(['dismissId', 'dismissReason']);
    }

    public function confirmDismiss(): void
    {
        $nudge = CrmNudge::find($this->dismissId);
        if (! $nudge) {
            session()->flash('error', 'Nudge no longer exists.');
            $this->cancelDismiss();
            return;
        }

        try {
            $this->ai()->dismissNudge($nudge, $this->dismissReason ?: null, auth()->id());
            session()->flash('message', 'Nudge dismissed.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->cancelDismiss();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function nudges()
    {
        return CrmNudge::query()
            ->with(['patient:id,first_name,last_name', 'lead:id,current_stage'])
            ->when($this->filter === 'pending', fn ($q) => $q->pending())
            ->when(in_array($this->filter, NudgeStatus::values(), true), fn ($q) => $q->where('status', $this->filter))
            ->latest()
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.admin.ai-nudges');
    }
}
