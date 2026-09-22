<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Services\SparWinBackService;

/**
 * SPAR Close-the-Loop Wave C5 (FR-C5) — Lost-customer win-back queue.
 *
 * Lists patients whose latest response signal was an opt-out (stop/ignore),
 * scoped to the actor, and lets staff send a personalised win-back offer. The
 * send is consent-gated by the dispatcher (a fully opted-out patient is not
 * messaged). Package-pure.
 */
class SparWinBack extends Component
{
    /** Per-patient incentive text keyed by patient id. */
    public array $incentive = [];

    public ?string $actionNotice = null;

    public function getLostCustomersProperty()
    {
        return app(SparWinBackService::class)->lostCustomers();
    }

    public function sendWinBack(int $patientId): void
    {
        // Re-resolve within the lost set so a crafted id can't target anyone else.
        $service = app(SparWinBackService::class);
        if (! in_array($patientId, $service->lostPatientIds(), true)) {
            $this->actionNotice = 'That patient is not in your win-back queue.';
            return;
        }

        $patient = SparPatient::query()->visibleToCurrentActor()->find($patientId);
        if (! $patient) {
            $this->actionNotice = 'That patient is not available.';
            return;
        }

        $sent = $service->sendWinBack($patient, (string) ($this->incentive[$patientId] ?? ''));

        $this->audit($patient, $sent);

        $this->actionNotice = $sent
            ? "Win-back offer sent to {$patient->display_name}."
            : "Not sent — {$patient->display_name} has fully opted out of comms.";

        unset($this->incentive[$patientId]);
    }

    private function audit(SparPatient $patient, bool $sent): void
    {
        $channel = config('logging.channels.spar_audit') ? 'spar_audit' : 'stack';
        Log::channel($channel)->info('spar_winback', [
            'action' => 'winback_offer',
            'spar_patient_id' => $patient->id,
            'sent' => $sent,
            'author_id' => Auth::user()?->getAuthIdentifier(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function render()
    {
        return view('spar::livewire.admin.spar-win-back')
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
