<?php

namespace App\Livewire\Admin;

use App\Models\Payment;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Payments extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function getRevenueStatsProperty(): array
    {
        return [
            'total' => Payment::where('status', 'completed')->sum('amount'),
            'this_month' => Payment::where('status', 'completed')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
            'last_month' => Payment::where('status', 'completed')
                ->whereMonth('paid_at', now()->subMonth()->month)
                ->whereYear('paid_at', now()->subMonth()->year)
                ->sum('amount'),
            'today' => Payment::where('status', 'completed')
                ->whereDate('paid_at', today())
                ->sum('amount'),
            'pending_count' => Payment::where('status', 'pending')->count(),
            'pending_amount' => Payment::where('status', 'pending')->sum('amount'),
        ];
    }

    public function render()
    {
        $payments = Payment::query()
            ->with(['patient', 'appointment'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('reference', 'like', "%{$this->search}%")
                      ->orWhere('provider_reference', 'like', "%{$this->search}%")
                      ->orWhereHas('patient', function ($pq) {
                          $pq->where('first_name', 'like', "%{$this->search}%")
                             ->orWhere('last_name', 'like', "%{$this->search}%");
                      });
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->dateFrom, function ($query) {
                $query->where('created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->where('created_at', '<=', $this->dateTo . ' 23:59:59');
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);

        return view('livewire.admin.payments', [
            'payments' => $payments,
        ])->layout('layouts.app');
    }
}
