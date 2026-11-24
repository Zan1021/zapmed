<?php

namespace App\Livewire\Admin;

use App\Services\Operations\OperationsDashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/** The Operations dashboard (Craig's guide 6.1): orders, sign-ups, revenue, cancellations, unpaid carts and coaching oversight. */
class OperationsOverview extends Component
{
    public string $month = '';
    public string $from = '';
    public string $to = '';
    public string $useMonth = '1';

    public function mount(): void
    {
        $this->month = now(config('doctor.time.timezone'))->format('Y-m');
    }

    public function render(OperationsDashboard $dashboard)
    {
        $this->resetErrorBag();

        $filters = $this->useMonth === '1' ? ['month' => $this->month] : array_filter(['from' => $this->from, 'to' => $this->to]);

        try {
            $data = $dashboard->build(Auth::user(), $filters);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $data = null;
        }

        return view('livewire.admin.operations-overview', ['d' => $data])->layout('layouts.app');
    }
}
