<?php

namespace App\Livewire\Doctor;

use App\Models\Appointment;
use App\Models\BloodTestRequest;
use App\Services\Doctor\BloodTestService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Blood results and history (Craig's guide 4.1): requested tests, uploaded results, review. */
class BloodTests extends Component
{
    use WithFileUploads;

    public string $filter = 'open';          // open | done
    public ?int $acting = null;              // the request being reviewed / cancelled / uploaded to
    public string $mode = '';                // review | cancel | upload
    public string $text = '';
    public $upload = null;

    public function open(int $id, string $mode): void
    {
        $this->resetErrorBag();
        $this->acting = $id;
        $this->mode = $mode;
        $this->text = '';
        $this->upload = null;
    }

    public function close(): void
    {
        $this->reset(['acting', 'mode', 'text', 'upload']);
    }

    public function review(BloodTestService $service): void
    {
        $this->resetErrorBag();
        $service->review(Auth::user(), $this->find(), $this->text);
        $this->close();
    }

    public function cancel(BloodTestService $service): void
    {
        $this->resetErrorBag();
        $service->cancel(Auth::user(), $this->find(), $this->text);
        $this->close();
    }

    public function uploadResult(BloodTestService $service): void
    {
        $this->resetErrorBag();
        $this->validate(['upload' => ['required', 'file']]);
        $service->uploadResult(Auth::user(), $this->find(), $this->upload);
        $this->close();
    }

    private function find(): BloodTestRequest
    {
        return $this->visible()->findOrFail((int) $this->acting);
    }

    /** Requests for patients this doctor treats, plus ones they asked for. */
    private function visible()
    {
        $me = Auth::user();

        return BloodTestRequest::query()->where(fn ($q) => $q->where('requested_by', $me->id)
            ->orWhereIn('patient_id', Appointment::where('doctor_id', $me->id)->select('patient_id')));
    }

    public function render()
    {
        $q = $this->visible()->with(['patient', 'order', 'results']);
        $this->filter === 'open' ? $q->open()->orderBy('id') : $q->whereNotIn('status', BloodTestRequest::OPEN)->orderByDesc('id');

        return view('livewire.doctor.blood-tests', ['requests' => $q->limit(200)->get()])->layout('layouts.app');
    }
}
