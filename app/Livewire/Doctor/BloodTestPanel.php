<?php

namespace App\Livewire\Doctor;

use App\Models\BloodTestRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\Doctor\BloodTestService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/** "Request blood tests" box inside the consultation screen. */
class BloodTestPanel extends Component
{
    use WithFileUploads;

    public int $patientId;
    /** @var string[] */
    public array $tests = [];
    public string $freeText = '';
    public string $labForm = '';
    public ?int $orderId = null;
    public $labFormFile = null;

    public function request(BloodTestService $service): void
    {
        $this->resetErrorBag();

        $patient = User::findOrFail($this->patientId);
        $order = $this->orderId ? Order::where('patient_id', $patient->id)->findOrFail($this->orderId) : null;

        $service->request(Auth::user(), $patient, $this->tests, $this->freeText, $this->labForm ?: null, $this->labFormFile, $order);

        $this->reset(['tests', 'freeText', 'labForm', 'orderId', 'labFormFile']);
    }

    public function render()
    {
        return view('livewire.doctor.blood-test-panel', [
            'catalogue' => config('doctor.blood_tests.catalogue'),
            'forms' => config('doctor.blood_tests.lab_forms'),
            'orders' => Order::where('patient_id', $this->patientId)->orderByDesc('id')->limit(10)->get(['id', 'reference', 'status']),
            'open' => BloodTestRequest::open()->where('patient_id', $this->patientId)->count(),
        ]);
    }
}
