<?php

namespace App\Livewire\Admin;

use App\Services\Stats\StatsService;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Task 7 — the exhaustive admin Stats dashboard. ONE canonical, sectioned read surface pulling every
 * metric group from StatsService (overview / acquisition / subscriptions / orders / finance / CRM
 * funnel). Read-only. Cross-links to the two focused Analytics pages (business + CRM acquisition),
 * which remain the deep-dive views. Period-filterable.
 */
class Stats extends Component
{
    public string $period = 'month';

    /** @var array<int,string> */
    public array $periods = ['today', 'week', 'month', 'year', 'all'];

    public function setPeriod(string $period): void
    {
        if (in_array($period, $this->periods, true)) {
            $this->period = $period;
        }
    }

    #[Computed]
    public function overview(): array
    {
        return app(StatsService::class)->overview($this->period);
    }

    #[Computed]
    public function acquisition(): array
    {
        return app(StatsService::class)->acquisition($this->period);
    }

    #[Computed]
    public function subscriptions(): array
    {
        return app(StatsService::class)->subscriptions($this->period);
    }

    #[Computed]
    public function orders(): array
    {
        return app(StatsService::class)->orders($this->period);
    }

    #[Computed]
    public function finance(): array
    {
        return app(StatsService::class)->finance($this->period);
    }

    #[Computed]
    public function crm(): array
    {
        return app(StatsService::class)->crmFunnel();
    }

    public function render()
    {
        return view('livewire.admin.stats')->layout('layouts.app');
    }
}
