<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Livewire\Component;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparActionService;

/**
 * SPAR Close-the-Loop Wave C3 (FR-C3) — Renewals workflow.
 *
 * Staff see prescription journeys that are due for renewal, filtered by window
 * (this week / this month / all), scoped to the actor. They can send a
 * "shall we prepare your next script?" nudge to one patient, or to the whole
 * filtered list in bulk. Every send goes through SparActionService::nudge(),
 * which is consent-gated (NFR-1) and moves the journey to awaiting_patient; the
 * patient then responds via the FR-B3 response widget on their tracker.
 *
 * Package-pure: scoping via SparIdentityProvider, no host class references.
 */
class SparRenewals extends Component
{
    public string $window = 'week'; // week | month | all

    public ?string $actionNotice = null;

    public function setWindow(string $window): void
    {
        $this->window = in_array($window, ['week', 'month', 'all'], true) ? $window : 'week';
    }

    /**
     * Renewal-due journeys within the selected window, scoped to the actor.
     * "Due" = status renewal_due OR a renewal_due_date within the window.
     */
    public function getRenewalsProperty()
    {
        return $this->baseQuery()
            ->with($this->patientWith(['pharmacy']))
            ->orderByRaw('renewal_due_date is null, renewal_due_date asc')
            ->limit(100)
            ->get();
    }

    public function getCountsProperty(): array
    {
        return [
            'week' => $this->windowQuery('week')->count(),
            'month' => $this->windowQuery('month')->count(),
            'all' => $this->windowQuery('all')->count(),
        ];
    }

    /* ---- actions ---------------------------------------------------------- */

    /** Nudge one renewal-due patient. */
    public function remindOne(int $journeyId): void
    {
        $journey = $this->baseQuery()->find($journeyId);

        if (! $journey) {
            $this->actionNotice = 'That renewal is no longer in your list.';
            return;
        }

        $sent = app(SparActionService::class)->nudge($journey, $this->renewalPayload());

        $this->actionNotice = $sent
            ? 'Reminder sent — awaiting the patient.'
            : 'Not sent (patient has not consented or is unreachable).';
    }

    /** Bulk-nudge every renewal-due patient in the current filtered window. */
    public function remindAll(): void
    {
        $service = app(SparActionService::class);
        $payload = $this->renewalPayload();

        $sent = 0;
        $skipped = 0;

        foreach ($this->baseQuery()->with('patient')->get() as $journey) {
            if ($service->nudge($journey, $payload)) {
                $sent++;
            } else {
                $skipped++;
            }
        }

        $this->actionNotice = "Bulk reminders: {$sent} sent"
            . ($skipped > 0 ? ", {$skipped} skipped (no consent / unreachable)." : '.');
    }

    /* ---- internals -------------------------------------------------------- */

    private function baseQuery()
    {
        return $this->windowQuery($this->window);
    }

    /**
     * Renewal-due journeys for a given window, scoped to the actor. Uses the
     * shared visibleToCurrentActor scope on the journey model.
     */
    private function windowQuery(string $window)
    {
        $query = SparPrescriptionJourney::query()
            ->visibleToCurrentActor()
            ->where('status', 'renewal_due');

        if ($window === 'week') {
            $query->where(function ($q) {
                $q->whereNull('renewal_due_date')
                  ->orWhere('renewal_due_date', '<=', now()->addWeek());
            });
        } elseif ($window === 'month') {
            $query->where(function ($q) {
                $q->whereNull('renewal_due_date')
                  ->orWhere('renewal_due_date', '<=', now()->addMonth());
            });
        }

        return $query;
    }

    /** @return array{subject:string,body:string,link:null} */
    private function renewalPayload(): array
    {
        return [
            'subject' => 'Your prescription is due for renewal',
            'body' => 'Your chronic script is due for renewal. Shall we prepare your next repeat? '
                . 'Reply on your SPAR meds page to let us know.',
            'link' => null,
        ];
    }

    /**
     * Eager-load `patient.user` only when the model defines it (integrated host).
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
        return view('spar::livewire.admin.spar-renewals')
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
