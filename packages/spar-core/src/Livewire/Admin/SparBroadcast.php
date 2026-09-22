<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Services\MessagingDispatcher;

/**
 * SPAR Close-the-Loop Wave C4 (FR-C4) — Pharmacy internal broadcast.
 *
 * A pharmacist composes a short message (a special, a closing-time change) and
 * fans it out to THEIR consented patients only. Every send goes through the
 * consent-gated MessagingDispatcher (NFR-1) and the recipient set is scoped to
 * the actor (SparPatient::visibleToCurrentActor + consented), so a broadcast can
 * never reach a non-consented patient nor a patient outside the actor's scope.
 *
 * Demo-grade (NFR-5): free-text body, no scheduling, no segmentation beyond
 * "my consented patients". Audited to spar_audit.
 *
 * Package-pure: no host User/Prescription references; author duck-typed.
 */
class SparBroadcast extends Component
{
    public string $subject = '';
    public string $body = '';

    public ?string $result = null;

    /** How many consented patients are currently in the actor's reach. */
    public function getAudienceCountProperty(): int
    {
        return SparPatient::query()->visibleToCurrentActor()->consented()->count();
    }

    public function send(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'min:2', 'max:120'],
            'body' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $dispatcher = app(MessagingDispatcher::class);

        $recipients = SparPatient::query()
            ->visibleToCurrentActor()
            ->consented()
            ->get();

        $sent = 0;
        foreach ($recipients as $patient) {
            // Dispatcher re-checks consent; this is belt-and-braces (NFR-1).
            if ($dispatcher->send($patient, [
                'subject' => $this->subject,
                'body' => $this->body,
                'link' => null,
            ])) {
                $sent++;
            }
        }

        $this->audit($sent, $recipients->count());

        $this->result = "Broadcast sent to {$sent} of {$recipients->count()} consented patients.";
        $this->reset(['subject', 'body']);
    }

    private function audit(int $sent, int $audience): void
    {
        $user = Auth::user();
        $channel = config('logging.channels.spar_audit') ? 'spar_audit' : 'stack';

        Log::channel($channel)->info('spar_broadcast_sent', [
            'action' => 'broadcast_sent',
            'sent' => $sent,
            'audience' => $audience,
            'author_id' => $user?->getAuthIdentifier(),
            'author_name' => $user->name ?? null,
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function render()
    {
        return view('spar::livewire.admin.spar-broadcast')
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
