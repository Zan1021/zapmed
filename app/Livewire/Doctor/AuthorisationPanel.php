<?php

namespace App\Livewire\Doctor;

use App\Models\Order;
use App\Models\OrderAuthorisation;
use App\Services\Doctor\AuthorisationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Inside the consultation screen: authorise an order's repeats for six months (the first authorisation). */
class AuthorisationPanel extends Component
{
    public int $patientId;
    public ?int $orderId = null;

    public function authorise(AuthorisationService $service): void
    {
        $this->resetErrorBag();
        if (! $this->orderId) {
            $this->addError('order', 'Choose an order first.');

            return;
        }

        $order = Order::where('patient_id', $this->patientId)->findOrFail((int) $this->orderId);
        $service->grant(Auth::user(), $order);

        $this->reset('orderId');
    }

    public function render(AuthorisationService $service)
    {
        $current = OrderAuthorisation::active()->where('patient_id', $this->patientId)->with('order')->orderBy('expires_on')->get();

        return view('livewire.doctor.authorisation-panel', [
            'current' => $current,
            'orders' => Order::where('patient_id', $this->patientId)->whereNotIn('status', ['Cancelled'])->orderByDesc('id')->limit(10)->get(['id', 'reference', 'status']),
            'months' => config('doctor.authorisations.months'),
        ]);
    }
}
