<?php

namespace App\Livewire\Doctor;

use App\Models\Appointment;
use App\Models\FollowUpRequest;
use App\Services\Doctor\FollowUpService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** The doctor's follow-up list (Craig's guide 4.1 "Follow-Up Appointments"): open, due soon, done. */
class FollowUps extends Component
{
    public string $filter = 'open';        // open | done
    public ?int $cancelling = null;
    public string $reason = '';

    public function complete(int $id, FollowUpService $service): void
    {
        $this->resetErrorBag();
        $service->complete(Auth::user(), $this->find($id));
    }

    public function askCancel(int $id): void
    {
        $this->cancelling = $id;
        $this->reason = '';
    }

    public function cancel(FollowUpService $service): void
    {
        $this->resetErrorBag();
        $service->cancel(Auth::user(), $this->find((int) $this->cancelling), $this->reason);
        $this->reset(['cancelling', 'reason']);
    }

    private function find(int $id): FollowUpRequest
    {
        return $this->visible()->findOrFail($id);
    }

    /** Follow-ups for patients this doctor treats, plus ones they asked for. */
    private function visible()
    {
        $me = Auth::user();

        return FollowUpRequest::query()->where(fn ($q) => $q->where('requested_by', $me->id)
            ->orWhereIn('patient_id', Appointment::where('doctor_id', $me->id)->select('patient_id')));
    }

    public function render()
    {
        $q = $this->visible()->with(['patient', 'order', 'doctor']);
        $this->filter === 'open' ? $q->open()->orderBy('due_at') : $q->whereNotIn('status', FollowUpRequest::OPEN)->orderByDesc('id');

        return view('livewire.doctor.follow-ups', [
            'followUps' => $q->limit(200)->get(),
            'tz' => config('doctor.time.timezone'),
        ])->layout('layouts.app');
    }
}
