<?php

namespace App\Livewire\Doctor;

use App\Models\FollowUpRequest;
use App\Models\Order;
use App\Services\Doctor\FollowUpService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** "Request a follow-up" box inside the consultation screen: pick an order and how many days. */
class FollowUpPanel extends Component
{
    public int $patientId;
    public ?int $consultationId = null;
    public ?int $orderId = null;
    public ?int $days = null;
    public string $note = '';

    public function request(FollowUpService $service): void
    {
        $this->resetErrorBag();

        $order = Order::where('patient_id', $this->patientId)->findOrFail((int) $this->orderId);
        $service->request(Auth::user(), $order, (int) $this->days, $this->note, $this->consultationId);

        $this->reset(['orderId', 'days', 'note']);
    }

    public function render(FollowUpService $service)
    {
        return view('livewire.doctor.follow-up-panel', [
            'orders' => Order::where('patient_id', $this->patientId)->whereNotIn('status', ['Cancelled', 'Paused'])->orderByDesc('id')->limit(10)->get(['id', 'reference', 'status']),
            'open' => FollowUpRequest::open()->where('patient_id', $this->patientId)->with('order')->get(),
            'days_options' => $service->days(),
            'tz' => config('doctor.time.timezone'),
        ]);
    }
}
