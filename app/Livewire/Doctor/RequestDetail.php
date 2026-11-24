<?php

namespace App\Livewire\Doctor;

use App\Models\DoctorRequest;
use App\Services\Doctor\DoctorRequestService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/** One doctor request: details, response, files, history and the status buttons the viewer is allowed to use. */
class RequestDetail extends Component
{
    use WithFileUploads;

    public DoctorRequest $doctorRequest;
    public string $response = '';
    public $upload = null;

    public function mount(DoctorRequest $doctorRequest, DoctorRequestService $service): void
    {
        abort_unless($service->canView(Auth::user(), $doctorRequest), 403);

        $this->doctorRequest = $doctorRequest;
        $service->open(Auth::user(), $doctorRequest);
        $this->doctorRequest->refresh();
    }

    public function start(DoctorRequestService $service): void
    {
        $this->resetErrorBag();
        $service->start(Auth::user(), $this->doctorRequest);
        $this->doctorRequest->refresh();
    }

    public function submit(DoctorRequestService $service): void
    {
        $this->resetErrorBag();
        $service->submit(Auth::user(), $this->doctorRequest, $this->response);
        $this->response = '';
        $this->doctorRequest->refresh();
    }

    public function complete(DoctorRequestService $service): void
    {
        $this->resetErrorBag();
        $service->complete(Auth::user(), $this->doctorRequest);
        $this->doctorRequest->refresh();
    }

    public function attach(DoctorRequestService $service): void
    {
        $this->resetErrorBag();
        $this->validate(['upload' => ['required', 'file']]);
        $service->attach(Auth::user(), $this->doctorRequest, $this->upload);
        $this->upload = null;
    }

    public function render(DoctorRequestService $service)
    {
        $me = Auth::user();
        $r = $this->doctorRequest->load(['requester', 'people', 'refs.patient', 'refs.order', 'files.uploader', 'events.user']);

        return view('livewire.doctor.request-detail', [
            'r' => $r,
            'isAssignee' => $service->isAssignee($me, $r),
            'canComplete' => $service->canComplete($me, $r) && $r->status === DoctorRequest::SUBMITTED,
            'canAttach' => $r->isOpen(),
        ])->layout('layouts.app');
    }
}
