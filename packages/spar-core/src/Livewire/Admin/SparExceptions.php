<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Zapmed\SparCore\Contracts\SparActionable;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparActionService;
use Zapmed\SparCore\Services\SparReminderService;
use Livewire\Component;

class SparExceptions extends Component
{
    public string $filter = 'all';

    /** Compose-message modal state. */
    public bool $showMessageModal = false;
    public ?string $messageSubjectType = null;
    public ?int $messageSubjectId = null;
    public string $messageBody = '';

    /** Transient flash for the last action outcome (shown in the view). */
    public ?string $actionNotice = null;

    /**
     * The subject classes the Exceptions screen is allowed to act on. The UI
     * passes a class-string, so we NEVER instantiate an arbitrary class — only
     * these three actionable models are resolvable (guards against a crafted
     * wire:click passing e.g. App\Models\User).
     *
     * @return array<int, class-string>
     */
    private function allowedSubjects(): array
    {
        return [
            SparDispenseRecord::class,
            SparPrescriptionJourney::class,
            SparOrder::class,
        ];
    }

    public function getOverdueDispensesProperty()
    {
        return SparDispenseRecord::overdue()
            ->with($this->patientWith(['journey.pharmacy']))
            ->orderBy('due_date')
            ->limit(50)
            ->get();
    }

    public function getMissingRenewalsProperty()
    {
        return SparPrescriptionJourney::where('status', 'renewal_due')
            ->where('renewal_due_date', '<', now()->subDays(7))
            ->with($this->patientWith(['pharmacy']))
            ->orderBy('renewal_due_date')
            ->limit(50)
            ->get();
    }

    public function getUnresponsivePatientsProperty()
    {
        $service = new SparReminderService();
        return $service->getUnresponsivePatients(10);
    }

    /* --------------------------------------------------------------------- */
    /* FR-B6 — staff close-the-loop actions on exception rows                */
    /* --------------------------------------------------------------------- */

    /**
     * Send a consent-gated reminder nudge for the given actionable subject.
     */
    public function nudgeItem(string $subjectType, int $subjectId): void
    {
        $item = $this->resolveSubject($subjectType, $subjectId);

        if (! $item) {
            $this->actionNotice = 'That item is no longer available.';
            return;
        }

        $sent = app(SparActionService::class)->nudge($item, [
            'subject' => 'A reminder from your SPAR pharmacy',
            'body' => $this->defaultNudgeBody($item),
            'link' => null,
        ]);

        $this->actionNotice = $sent
            ? 'Nudge sent — the loop is now awaiting the patient.'
            : 'Nudge not sent (no consent or no reachable channel).';
    }

    /**
     * Defer the item for $days days (staff-side; no message goes out).
     */
    public function snoozeItem(string $subjectType, int $subjectId, int $days = 7): void
    {
        $item = $this->resolveSubject($subjectType, $subjectId);

        if (! $item) {
            $this->actionNotice = 'That item is no longer available.';
            return;
        }

        $snoozed = app(SparActionService::class)->snooze($item, $days);

        $this->actionNotice = $snoozed
            ? "Snoozed for {$days} day(s)."
            : 'Could not snooze (item already resolved).';
    }

    /**
     * Open the compose modal for a personal message to this subject's patient.
     */
    public function messageItem(string $subjectType, int $subjectId): void
    {
        // Validate the target up front so the modal never opens on a bad subject.
        if (! $this->resolveSubject($subjectType, $subjectId)) {
            $this->actionNotice = 'That item is no longer available.';
            return;
        }

        $this->messageSubjectType = $subjectType;
        $this->messageSubjectId = $subjectId;
        $this->messageBody = '';
        $this->showMessageModal = true;
    }

    /**
     * Send the composed personal message via the coach thread.
     */
    public function sendMessage(): void
    {
        $this->validate([
            'messageBody' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $item = $this->resolveSubject((string) $this->messageSubjectType, (int) $this->messageSubjectId);

        if (! $item) {
            $this->closeMessageModal();
            $this->actionNotice = 'That item is no longer available.';
            return;
        }

        $pharmacyId = $this->pharmacyIdFor($item);

        if (! $pharmacyId) {
            $this->closeMessageModal();
            $this->actionNotice = 'Could not determine the pharmacy for this item.';
            return;
        }

        $posted = app(SparActionService::class)->personalMessage(
            $item,
            $this->messageBody,
            $this->currentAuthor(),
            $pharmacyId,
        );

        $this->closeMessageModal();

        $this->actionNotice = $posted
            ? 'Message sent to the patient.'
            : 'Message not sent (patient has not consented).';
    }

    public function closeMessageModal(): void
    {
        $this->showMessageModal = false;
        $this->messageSubjectType = null;
        $this->messageSubjectId = null;
        $this->messageBody = '';
    }

    /* --------------------------------------------------------------------- */
    /* Internals                                                             */
    /* --------------------------------------------------------------------- */

    /**
     * Resolve a subject instance from a class-string + id, restricted to the
     * whitelisted actionable models. Returns null if the type is not allowed
     * or the row no longer exists.
     *
     * The class-string arrives from the browser, so we NEVER instantiate an
     * arbitrary class. We accept a whitelisted base OR any host subclass of it
     * (hosts subclass the package models, e.g. App\Models\SparDispenseRecord
     * extends the package base) — matched via is_a(), not exact string compare.
     */
    private function resolveSubject(string $subjectType, int $subjectId): ?SparActionable
    {
        if (! $this->isAllowedSubject($subjectType)) {
            return null;
        }

        /** @var SparActionable|null $item */
        $item = $subjectType::query()->find($subjectId);

        return $item instanceof SparActionable ? $item : null;
    }

    /**
     * Whether the given class-string is (or subclasses) one of the whitelisted
     * actionable models. Guards against a crafted wire:click passing an
     * unrelated class (e.g. App\Models\User).
     */
    private function isAllowedSubject(string $subjectType): bool
    {
        if (! class_exists($subjectType)) {
            return false;
        }

        foreach ($this->allowedSubjects() as $allowed) {
            if (is_a($subjectType, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Best-effort pharmacy id for the subject (dispense → journey.pharmacy;
     * journey → pharmacy; order → its pharmacy).
     */
    private function pharmacyIdFor(SparActionable $item): ?int
    {
        if ($item instanceof SparDispenseRecord) {
            return $item->journey?->spar_pharmacy_id;
        }

        if ($item instanceof SparPrescriptionJourney) {
            return $item->spar_pharmacy_id;
        }

        if ($item instanceof SparOrder) {
            return $item->spar_pharmacy_id;
        }

        return null;
    }

    private function defaultNudgeBody(SparActionable $item): string
    {
        if ($item instanceof SparPrescriptionJourney) {
            return 'Your prescription is due for renewal. Please arrange a new script, or reply for help.';
        }

        return 'Your medication is due for collection. Reply to arrange collection or delivery.';
    }

    /**
     * The acting staff member as a plain author array (no host User coupling).
     *
     * @return array{id:int|null,name:string|null,role:string|null}
     */
    private function currentAuthor(): array
    {
        $user = Auth::user();

        return [
            'id' => $user?->getAuthIdentifier(),
            'name' => $user->name ?? null,
            'role' => $user->role ?? null,
        ];
    }

    /**
     * Eager-load list including `patient.user` only when the model defines it.
     *
     * @param  array<int, string>  $extra
     * @return array<int, string>
     */
    private function patientWith(array $extra = []): array
    {
        $patientLoad = method_exists(SparPatient::class, 'user') ? 'patient.user' : 'patient';

        return array_merge([$patientLoad], $extra);
    }

    public function render()
    {
        return view('spar::livewire.admin.spar-exceptions')
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
