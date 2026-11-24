<?php

namespace App\Livewire\Patient;

use App\Models\Appointment;
use App\Models\FollowUpRequest;
use App\Services\Patient\PatientFollowUps;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Follow-Up tab (Craig's guide 3.1): book a follow-up or check-in with one of your doctors, 15 or 30 minutes. */
class FollowUp extends Component
{
    public string $type = 'follow_up';
    public ?int $doctorId = null;
    public int $minutes = 30;
    public string $date = '';
    public string $time = '';
    public ?int $requestId = null;

    public function mount(): void
    {
        $this->date = now(config('doctor.time.timezone'))->addDay()->format('Y-m-d');

        // Coming from a booking link Operations sent, or "Book a check-in" on My Orders, or a doctor's request: start on it.
        $linked = session()->pull('booking_link.follow_up_request_id');
        if ($linked && FollowUpRequest::where('patient_id', Auth::id())->where('status', FollowUpRequest::REQUESTED)->whereKey($linked)->exists()) {
            $this->pickRequest((int) $linked);

            return;
        }

        $first = app(PatientFollowUps::class)->requests(Auth::user())->first();
        if ($first) {
            $this->pickRequest($first->id);
        }
    }

    public function pickRequest(int $id): void
    {
        $request = FollowUpRequest::where('patient_id', Auth::id())->where('status', FollowUpRequest::REQUESTED)->findOrFail($id);
        $this->requestId = $request->id;
        $this->doctorId = $request->requested_by;
        $this->time = '';
    }

    public function clearRequest(): void
    {
        $this->requestId = null;
        $this->time = '';
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['doctorId', 'minutes', 'date'], true)) {
            $this->time = '';
        }
    }

    public function book(PatientFollowUps $service)
    {
        $this->resetErrorBag();

        $result = $service->book(Auth::user(), (int) $this->doctorId, $this->date, $this->time, $this->minutes, $this->type, $this->requestId);

        if ($result['payment']) {
            return $this->redirect(route('payment.checkout', ['reference' => $result['payment']->reference]));
        }

        session()->flash('followup-booked', 'Your appointment is booked. We will send you the details.');
        $this->reset(['time', 'requestId']);
    }

    public function render(PatientFollowUps $service)
    {
        $patient = Auth::user();
        $doctors = $service->doctors($patient);
        $selected = $doctors->firstWhere('id', $this->doctorId);
        $request = $this->requestId ? FollowUpRequest::where('patient_id', $patient->id)->find($this->requestId) : null;

        $slots = [];
        try {
            $slots = $this->doctorId ? $service->slots($patient, $this->doctorId, $this->date, $this->minutes) : [];
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('date', $e->errors()['date'][0] ?? 'Choose a valid date.');
        }

        return view('livewire.patient.follow-up', [
            'requests' => $service->requests($patient),
            'doctors' => $doctors,
            'slots' => $slots,
            'fee' => $selected ? $service->feeCents($selected, $this->minutes, $request) : null,
            'types' => config('patient.follow_up.types'),
            'durations' => config('patient.follow_up.durations'),
            'upcoming' => Appointment::with('doctor')->where('patient_id', $patient->id)
                ->whereIn('type', array_keys(config('patient.follow_up.types')))->whereIn('status', ['confirmed', 'pending_payment'])
                ->whereDate('appointment_date', '>=', now()->toDateString())->orderBy('appointment_date')->limit(10)->get(),
        ])->layout('layouts.app');
    }
}
