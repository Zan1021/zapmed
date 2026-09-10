<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Livewire\Component;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Services\SparStatsService;

/**
 * Statistics dashboard (spec FR-18, Phase 8.2). ONE component, THREE scope
 * views auto-selected by the viewer's role via SparStatsService:
 *   - super-admin → platform-wide
 *   - group-admin → their group
 *   - pharmacy     → their store
 * Any authenticated staff actor may view their own scope's numbers; only
 * super/group see the leaderboard. Aggregates only — no per-patient PHI.
 */
class SparStats extends Component
{
    public function mount(): void
    {
        // Any authenticated SPAR actor can see stats for their own scope.
        abort_unless(app(SparIdentityProvider::class)->currentRole() !== null, 403);
    }

    public function render()
    {
        $stats = app(SparStatsService::class)->forCurrentActor();

        return view('spar::livewire.admin.spar-stats', ['stats' => $stats])
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
