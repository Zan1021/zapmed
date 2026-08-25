<?php

namespace App\Livewire\Admin;

use App\Models\SparDispenseRecord;
use App\Models\SparOrder;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Carbon\Carbon;
use Livewire\Component;

class SparReporting extends Component
{
    public string $period = '30'; // days
    public ?int $pharmacyFilter = null;

    public function getDateRangeProperty(): array
    {
        return [
            'start' => now()->subDays((int) $this->period),
            'end' => now(),
        ];
    }

    public function getAdherenceStatsProperty(): array
    {
        $query = SparDispenseRecord::where('due_date', '>=', $this->dateRange['start'])
            ->where('due_date', '<=', $this->dateRange['end']);

        if ($this->pharmacyFilter) {
            $query->whereHas('journey', fn ($q) => $q->where('spar_pharmacy_id', $this->pharmacyFilter));
        }

        $total = (clone $query)->whereIn('status', ['collected', 'delivered', 'missed', 'reminded'])->count();
        $onTime = (clone $query)->whereIn('status', ['collected', 'delivered'])->count();
        $overdue = (clone $query)->whereIn('status', ['reminded', 'upcoming'])->where('due_date', '<', now())->count();

        return [
            'adherence_rate' => $total > 0 ? round(($onTime / $total) * 100, 1) : 0,
            'total_due' => $total,
            'on_time' => $onTime,
            'overdue' => $overdue,
        ];
    }

    public function getRenewalStatsProperty(): array
    {
        $query = SparPrescriptionJourney::where('updated_at', '>=', $this->dateRange['start']);

        if ($this->pharmacyFilter) {
            $query->where('spar_pharmacy_id', $this->pharmacyFilter);
        }

        $renewalDue = (clone $query)->where('status', 'renewal_due')->count();
        $renewed = (clone $query)->where('status', 'renewed')->count();
        $primaryDoctor = (clone $query)->where('renewal_route', 'primary_doctor')->count();
        $zapmed = (clone $query)->where('renewal_route', 'zapmed')->count();

        $totalRenewalAttempts = $renewed + $renewalDue;
        $renewalRate = $totalRenewalAttempts > 0 ? round(($renewed / $totalRenewalAttempts) * 100, 1) : 0;

        return [
            'renewal_rate' => $renewalRate,
            'renewal_due' => $renewalDue,
            'renewed' => $renewed,
            'via_primary_doctor' => $primaryDoctor,
            'via_zapmed' => $zapmed,
            'zapmed_conversion' => ($primaryDoctor + $zapmed) > 0 ? round(($zapmed / ($primaryDoctor + $zapmed)) * 100, 1) : 0,
        ];
    }

    public function getPatientStatsProperty(): array
    {
        $query = SparPatient::query();

        if ($this->pharmacyFilter) {
            $query->where('spar_pharmacy_id', $this->pharmacyFilter);
        }

        $total = (clone $query)->count();
        $active = (clone $query)->where('is_active', true)->count();
        $consented = (clone $query)->where('consent_status', 'opted_in')->count();
        $optedOut = (clone $query)->where('consent_status', 'opted_out')->count();
        $newThisPeriod = (clone $query)->where('created_at', '>=', $this->dateRange['start'])->count();

        return [
            'total' => $total,
            'active' => $active,
            'consented' => $consented,
            'opted_out' => $optedOut,
            'new_this_period' => $newThisPeriod,
            'opt_in_rate' => $total > 0 ? round(($consented / $total) * 100, 1) : 0,
        ];
    }

    public function getOrderStatsProperty(): array
    {
        $query = SparOrder::where('created_at', '>=', $this->dateRange['start']);

        if ($this->pharmacyFilter) {
            $query->where('spar_pharmacy_id', $this->pharmacyFilter);
        }

        $total = (clone $query)->count();
        $collections = (clone $query)->where('type', 'collection')->count();
        $deliveries = (clone $query)->where('type', 'delivery')->count();
        $completed = (clone $query)->where('status', 'completed')->count();

        // Average prep time (requested to ready)
        $avgPrepMinutes = SparOrder::where('created_at', '>=', $this->dateRange['start'])
            ->whereNotNull('ready_at')
            ->when($this->pharmacyFilter, fn ($q) => $q->where('spar_pharmacy_id', $this->pharmacyFilter))
            ->selectRaw('AVG(JULIANDAY(ready_at) - JULIANDAY(created_at)) * 24 * 60 as avg_mins')
            ->value('avg_mins');

        return [
            'total' => $total,
            'collections' => $collections,
            'deliveries' => $deliveries,
            'completed' => $completed,
            'collection_pct' => $total > 0 ? round(($collections / $total) * 100, 0) : 0,
            'avg_prep_minutes' => $avgPrepMinutes ? round($avgPrepMinutes) : null,
        ];
    }

    public function getPharmacyRankingProperty()
    {
        return SparPharmacy::active()
            ->withCount([
                'patients as active_patients' => fn ($q) => $q->where('is_active', true),
                'journeys as active_journeys' => fn ($q) => $q->where('status', 'active'),
                'orders as completed_orders' => fn ($q) => $q->where('status', 'completed')
                    ->where('created_at', '>=', $this->dateRange['start']),
            ])
            ->orderByDesc('active_patients')
            ->get();
    }

    public function getMonthlyTrendProperty(): array
    {
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $query = SparDispenseRecord::whereBetween('due_date', [$start, $end]);
            if ($this->pharmacyFilter) {
                $query->whereHas('journey', fn ($q) => $q->where('spar_pharmacy_id', $this->pharmacyFilter));
            }

            $due = (clone $query)->count();
            $completed = (clone $query)->whereIn('status', ['collected', 'delivered'])->count();

            $months->push([
                'label' => $month->format('M Y'),
                'due' => $due,
                'completed' => $completed,
                'rate' => $due > 0 ? round(($completed / $due) * 100, 1) : 0,
            ]);
        }

        return $months->toArray();
    }

    public function getPharmaciesProperty()
    {
        return SparPharmacy::active()->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.admin.spar-reporting')
            ->layout('layouts.app');
    }
}
