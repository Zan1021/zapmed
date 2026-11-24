<?php

namespace App\Livewire\Admin;

use App\Models\IntegrationMessage;
use App\Services\Operations\IntegrationLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/** The integration log (Craig's guide 6.4): what was sent to the pharmacy, what came back, and a safe resend. */
class IntegrationLogPage extends Component
{
    use WithPagination;

    public string $status = '';
    public string $search = '';          // prescription reference, order reference or the patient's email
    public ?int $confirming = null;      // an "unknown" row waiting for the person to confirm
    public bool $confirmed = false;
    public ?string $message = null;

    public function updating(): void
    {
        $this->resetPage();
    }

    public function askResend(int $id): void
    {
        $this->resetErrorBag();
        $this->message = null;
        $this->confirming = $id;
        $this->confirmed = false;
    }

    public function cancel(): void
    {
        $this->reset(['confirming', 'confirmed']);
    }

    public function resend(int $id, IntegrationLog $log): void
    {
        $this->resetErrorBag();

        $result = $log->resend(Auth::user(), IntegrationMessage::findOrFail($id), $this->confirmed);

        $this->message = $result['success'] ? 'The pharmacy accepted it.' : 'The pharmacy did not accept it: '.$result['message'];
        $this->reset(['confirming', 'confirmed']);
    }

    public function render(IntegrationLog $log)
    {
        $q = IntegrationMessage::with(['prescription', 'order', 'patient'])->orderByDesc('id');
        if (in_array($this->status, ['success', 'failed', 'unknown'], true)) {
            $q->where('status', $this->status);
        }
        if (trim($this->search) !== '') {
            $s = trim($this->search);
            $q->where(fn ($w) => $w->whereHas('prescription', fn ($p) => $p->where('reference', 'like', "%{$s}%"))
                ->orWhereHas('order', fn ($o) => $o->where('reference', 'like', "%{$s}%"))
                ->orWhereHas('patient', fn ($u) => $u->where('email', 'like', "%{$s}%")));
        }

        $page = $q->paginate(25);
        $latest = $page->getCollection()->mapWithKeys(fn (IntegrationMessage $m) => [$m->id => $log->isLatest($m) && $m->prescription && ! $log->alreadySent($m->prescription)]);

        return view('livewire.admin.integration-log', [
            'rows' => $page,
            'canResend' => $latest,
            'max' => (int) config('operations.integrations.max_attempts'),
        ])->layout('layouts.app');
    }
}
