<?php

namespace App\Livewire\Admin;

use App\Services\Operations\OperationsReports;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/** The Operations reports (Craig's guide 6.3): pick a report and a period, read it, download the CSV. */
class OperationsReportsPage extends Component
{
    public string $report = 'upcoming_repeats';
    public string $month = '';
    public string $from = '';
    public string $to = '';
    public string $useMonth = '1';       // 1 = a whole month, 0 = custom dates
    public string $productId = '';
    public string $state = 'all';
    public string $status = '';

    public function mount(): void
    {
        $this->month = now(config('doctor.time.timezone'))->format('Y-m');
    }

    /** The filters the screen and the CSV link both use. */
    public function filters(): array
    {
        return array_filter([
            'month' => $this->useMonth === '1' ? $this->month : null,
            'from' => $this->useMonth === '1' ? null : ($this->from ?: null),
            'to' => $this->useMonth === '1' ? null : ($this->to ?: null),
            'product_id' => $this->productId ?: null,
            'state' => $this->state !== 'all' ? $this->state : null,
            'status' => $this->status ?: null,
        ], fn ($v) => $v !== null);
    }

    public function render(OperationsReports $reports)
    {
        $this->resetErrorBag();

        try {
            $result = $reports->run(Auth::user(), $this->report, $this->filters());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $result = null;
        }

        return view('livewire.admin.operations-reports', [
            'result' => $result,
            'reportNames' => OperationsReports::REPORTS,
            'products' => $this->report === 'user_medication' ? $reports->products() : collect(),
            'csvUrl' => route('admin.reports.export', ['report' => $this->report] + $this->filters()),
        ])->layout('layouts.app');
    }
}
