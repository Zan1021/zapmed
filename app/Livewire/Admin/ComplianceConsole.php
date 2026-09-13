<?php

namespace App\Livewire\Admin;

use App\Enums\ConsentPurpose;
use App\Enums\ConsentState;
use App\Enums\DsarStatus;
use App\Models\ComplianceConsent;
use App\Models\ComplianceDsar;
use App\Models\RetentionScheduleItem;
use App\Models\User;
use App\Services\Compliance\ComplianceService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Compliance console (Task 9, specs/contro-rebuild/08 §2.8) — the POPIA ops surface.
 *
 * Panes: DSAR queue (acknowledge → start → complete/reject, overdue vs 30-day SLA), retention schedule
 * (legal hold, run due items), and a consent viewer/editor (per-patient purpose grant/withdraw). All
 * actions go through ComplianceService, which guards status machines + writes the append-only audit trail.
 */
class ComplianceConsole extends Component
{
    use WithPagination;

    public string $tab = 'dsars'; // dsars | retention | consent

    public string $dsarFilter = 'open'; // open, overdue, all

    public ?int $rejectId = null;
    public string $rejectReason = '';

    public ?int $holdId = null;
    public string $holdReason = '';

    /** Consent tab: patient lookup. */
    public string $consentQuery = '';
    public ?int $consentPatientId = null;

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

    // ---- consent viewer / editor ----------------------------------------------------------------

    public function findConsentPatient(): void
    {
        $patient = User::query()
            ->where('role', \App\Enums\UserRole::Patient->value)
            ->where(function ($q) {
                $like = '%' . trim($this->consentQuery) . '%';
                $q->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like);
            })
            ->first();

        if (! $patient) {
            session()->flash('error', 'No patient matched that search.');
            $this->consentPatientId = null;
            return;
        }

        $this->consentPatientId = $patient->id;
    }

    public function grantConsent(string $purpose): void
    {
        $this->setConsent($purpose, ConsentState::Granted);
    }

    public function withdrawConsent(string $purpose): void
    {
        $this->setConsent($purpose, ConsentState::Withdrawn);
    }

    private function setConsent(string $purpose, ConsentState $state): void
    {
        $patient = $this->consentPatientId ? User::find($this->consentPatientId) : null;
        $purposeEnum = ConsentPurpose::tryFrom($purpose);
        if (! $patient || ! $purposeEnum) {
            session()->flash('error', 'Invalid patient or purpose.');
            return;
        }

        app(ComplianceService::class)->setConsent($patient, $purposeEnum, $state, 'ops update via console', actorId: auth()->id());
        session()->flash('message', "Consent {$state->value} for {$purposeEnum->label()}.");
    }

    /** @return array<string,\App\Models\ComplianceConsent|null> purpose value => current consent row */
    #[Computed]
    public function consentMatrix(): array
    {
        if ($this->consentPatientId === null) {
            return [];
        }

        $existing = ComplianceConsent::where('principal_id', $this->consentPatientId)->get()->keyBy(fn ($c) => $c->purpose->value);

        $matrix = [];
        foreach (ConsentPurpose::cases() as $purpose) {
            $matrix[$purpose->value] = $existing->get($purpose->value);
        }

        return $matrix;
    }

    #[Computed]
    public function consentPatient(): ?User
    {
        return $this->consentPatientId ? User::find($this->consentPatientId) : null;
    }

    public function render()
    {
        return view('livewire.admin.compliance-console');
    }
}
