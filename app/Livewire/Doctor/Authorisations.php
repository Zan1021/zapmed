<?php

namespace App\Livewire\Doctor;

use App\Models\Appointment;
use App\Models\OrderAuthorisation;
use App\Services\Doctor\AuthorisationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Authorisations (Craig's guide 4.1): patients nearing their six-month renewal. Renew, or ask for a follow-up first. */
class Authorisations extends Component
{
    public string $filter = 'due';          // due | active | past
    public ?int $acting = null;
    public string $mode = '';               // renew | followup
    public string $note = '';
    public ?int $days = null;

    public function open(int $id, string $mode): void
    {
        $this->resetErrorBag();
        $this->acting = $id;
        $this->mode = $mode;
        $this->note = '';
        $this->days = null;
    }

    public function close(): void
    {
        $this->reset(['acting', 'mode', 'note', 'days']);
    }

    public function renew(AuthorisationService $service): void
    {
        $this->resetErrorBag();
        $service->renew(Auth::user(), $this->find(), $this->note);
        $this->close();
    }

    public function followUp(AuthorisationService $service): void
    {
        $this->resetErrorBag();
        $service->requestFollowUp(Auth::user(), $this->find(), (int) $this->days, $this->note);
        $this->close();
    }

    private function find(): OrderAuthorisation
    {
        return $this->visible()->findOrFail((int) $this->acting);
    }

    /** Authorisations for patients this doctor treats. */
    private function visible()
    {
        return OrderAuthorisation::query()
            ->whereIn('patient_id', app(\App\Services\Coach\CoachAccess::class)->doctorPatientIds(Auth::user()));
    }

    public function render(AuthorisationService $service)
    {
        $q = $this->visible()->with(['patient', 'order', 'followUp']);

        if ($this->filter === 'due') {
            $q->whereIn('status', ['active', 'expired'])
                ->whereDate('expires_on', '<=', $service->today()->addDays((int) config('doctor.authorisations.review_days_before'))->format('Y-m-d'))
                ->orderBy('expires_on');
        } elseif ($this->filter === 'active') {
            $q->where('status', 'active')->orderBy('expires_on');
        } else {
            $q->whereIn('status', ['renewed', 'expired'])->orderByDesc('id');
        }

        return view('livewire.doctor.authorisations', [
            'rows' => $q->limit(200)->get(),
            'today' => $service->today()->format('Y-m-d'),
            'days_options' => app(\App\Services\Doctor\FollowUpService::class)->days(),
        ])->layout('layouts.app');
    }
}
