<?php

namespace Zapmed\SparCore\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;

/**
 * Scope-filtered platform statistics (spec FR-18, Phase 8.1).
 *
 * Every metric is filtered to the pharmacies the CURRENT actor may see, via the
 * bound SparIdentityProvider:
 *   - super-admin  → all pharmacies (pharmacyIds() = null = no restriction)
 *   - group-admin  → pharmacies in their group
 *   - pharmacy      → their single store
 * Fail-closed: an actor with no resolvable scope gets an empty id set.
 *
 * Aggregates only — no per-patient PHI leaves this service (spec: PHI-minimised
 * stats surface).
 */
class SparStatsService
{
    /**
     * The set of pharmacy ids in scope, or null for "all" (super-admin).
     * An empty array means "nothing visible" (fail-closed).
     *
     * @return array<int>|null
     */
    public function scopedPharmacyIds(): ?array
    {
        $identity = app(SparIdentityProvider::class);

        if ($identity->isSuperAdmin()) {
            return null; // no restriction
        }

        $pharmacyId = $identity->currentPharmacyId();
        if ($pharmacyId !== null) {
            return [$pharmacyId];
        }

        $groupId = $identity->currentGroupId();
        if ($groupId !== null) {
            return SparPharmacy::where('group_id', $groupId)->pluck('id')->all();
        }

        return []; // fail-closed
    }

    /** Apply the pharmacy scope to a query on a table with spar_pharmacy_id. */
    private function scope($query, string $column = 'spar_pharmacy_id')
    {
        $ids = $this->scopedPharmacyIds();
        if ($ids === null) {
            return $query;              // all
        }
        if ($ids === []) {
            return $query->whereRaw('1 = 0'); // nothing
        }

        return $query->whereIn($column, $ids);
    }

    /** The full stats payload for the current actor's scope + role view. */
    public function forCurrentActor(): array
    {
        $identity = app(SparIdentityProvider::class);
        $role = $identity->currentRole();
        $showPlatform = $identity->isSuperAdmin();
        $showGroupLevel = $showPlatform || $role === 'group_admin';

        return [
            'role' => $role,
            'counts' => $this->counts($showPlatform, $showGroupLevel),
            'onboarding_funnel' => $this->onboardingFunnel(),
            'consent' => $this->consentRates(),
            'adherence' => $this->adherence(),
            'renewals' => $this->renewals(),
            'orders' => $this->orderQueue(),
            'messaging' => $this->messaging(),
            'leaderboard' => $showGroupLevel ? $this->leaderboard() : [],
        ];
    }

    private function counts(bool $showPlatform, bool $showGroupLevel): array
    {
        $ids = $this->scopedPharmacyIds();

        $pharmacyCount = $ids === null
            ? SparPharmacy::count()
            : count($ids);

        return [
            'groups' => $showPlatform ? SparPharmacyGroup::count() : ($showGroupLevel ? 1 : 0),
            'pharmacies' => $pharmacyCount,
            'patients' => $this->scope(SparPatient::query())->count(),
            'active_patients' => $this->scope(SparPatient::query())->where('is_active', true)->count(),
            'active_journeys' => $this->scope(SparPrescriptionJourney::query())->where('status', 'active')->count(),
        ];
    }

    private function onboardingFunnel(): array
    {
        $base = fn () => $this->scope(SparPatient::query());

        $awaiting = (clone $base())->where('onboarding_status', 'awaiting_contact')->count();
        $pending = (clone $base())->where('onboarding_status', 'pending_consent')->count();
        $active = (clone $base())->where('onboarding_status', 'active')->count();
        $optedOut = (clone $base())->where('onboarding_status', 'opted_out')->count();
        $total = $awaiting + $pending + $active + $optedOut;

        return [
            'awaiting_contact' => $awaiting,
            'pending_consent' => $pending,
            'active' => $active,
            'opted_out' => $optedOut,
            'total' => $total,
            'activation_rate' => $total > 0 ? round($active / $total * 100, 1) : 0.0,
        ];
    }

    private function consentRates(): array
    {
        $base = fn () => $this->scope(SparPatient::query());
        $optedIn = (clone $base())->where('consent_status', 'opted_in')->count();
        $pending = (clone $base())->where('consent_status', 'pending')->count();
        $optedOut = (clone $base())->where('consent_status', 'opted_out')->count();
        $total = $optedIn + $pending + $optedOut;

        return [
            'opted_in' => $optedIn,
            'pending' => $pending,
            'opted_out' => $optedOut,
            'consent_rate' => $total > 0 ? round($optedIn / $total * 100, 1) : 0.0,
        ];
    }

    private function adherence(): array
    {
        // Dispense records for in-scope pharmacies (via their journeys).
        $ids = $this->scopedPharmacyIds();
        $q = SparDispenseRecord::query();
        if ($ids !== null) {
            $q = $ids === []
                ? $q->whereRaw('1 = 0')
                : $q->whereHas('journey', fn ($j) => $j->whereIn('spar_pharmacy_id', $ids));
        }

        $collected = (clone $q)->where('status', 'collected')->count();
        $upcoming = (clone $q)->where('status', 'upcoming')->count();
        $overdue = (clone $q)->where('status', 'upcoming')->where('due_date', '<', now())->count();

        return [
            'collected' => $collected,
            'upcoming' => $upcoming,
            'overdue' => $overdue,
        ];
    }

    private function renewals(): array
    {
        $base = fn () => $this->scope(SparPrescriptionJourney::query());

        return [
            'due' => (clone $base())->where('status', 'renewal_due')->count(),
            'renewed' => (clone $base())->where('status', 'renewed')->count(),
            'lapsed' => (clone $base())->where('status', 'renewal_due')
                ->whereNotNull('renewal_due_date')
                ->where('renewal_due_date', '<', now()->subDays(30))
                ->count(),
        ];
    }

    private function orderQueue(): array
    {
        $base = fn () => $this->scope(SparOrder::query());

        return [
            'requested' => (clone $base())->where('status', 'requested')->count(),
            'preparing' => (clone $base())->where('status', 'preparing')->count(),
            'ready' => (clone $base())->where('status', 'ready')->count(),
            'completed' => (clone $base())->where('status', 'completed')->count(),
        ];
    }

    private function messaging(): array
    {
        // Reminders sent = dispense records that have been reminded.
        $ids = $this->scopedPharmacyIds();
        $q = SparDispenseRecord::query()->whereNotNull('reminded_at');
        if ($ids !== null) {
            $q = $ids === []
                ? $q->whereRaw('1 = 0')
                : $q->whereHas('journey', fn ($j) => $j->whereIn('spar_pharmacy_id', $ids));
        }

        return [
            'reminders_sent' => $q->count(),
            // Channel-level delivery breakdown is recorded in spar_audit; a
            // dedicated messages table is a later enhancement. Placeholder keys
            // keep the dashboard contract stable.
            'by_channel' => [
                'whatsapp' => null,
                'sms' => null,
                'email' => null,
                'inapp' => null,
            ],
        ];
    }

    /**
     * Pharmacy leaderboard by consent rate (super-admin / group-admin only).
     *
     * @return array<int, array{pharmacy:string, patients:int, consent_rate:float}>
     */
    private function leaderboard(): array
    {
        $ids = $this->scopedPharmacyIds();
        $pharmacies = $ids === null
            ? SparPharmacy::all()
            : ($ids === [] ? collect() : SparPharmacy::whereIn('id', $ids)->get());

        return $pharmacies->map(function (SparPharmacy $p) {
            $total = SparPatient::where('spar_pharmacy_id', $p->id)->count();
            $consented = SparPatient::where('spar_pharmacy_id', $p->id)->where('consent_status', 'opted_in')->count();

            return [
                'pharmacy' => $p->name,
                'patients' => $total,
                'consent_rate' => $total > 0 ? round($consented / $total * 100, 1) : 0.0,
            ];
        })->sortByDesc('consent_rate')->values()->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Merged "Insights" surface (Stats + Reporting unified, Phase 8.3)
    |--------------------------------------------------------------------------
    | Period- and pharmacy-filter aware, but ALWAYS bounded by the actor's own
    | scope (a chosen pharmacyFilter is intersected with scopedPharmacyIds so a
    | group-admin can never filter to a store outside their group — closes the
    | unscoped-Reporting leak). All aggregates only; no per-patient PHI.
    */

    /**
     * Effective pharmacy-id set for an insights query, intersecting the actor's
     * scope with an optional user-selected pharmacy filter. Returns null only
     * when the actor is unrestricted (super-admin) AND no filter is applied.
     *
     * @return array<int>|null
     */
    public function effectivePharmacyIds(?int $pharmacyFilter = null): ?array
    {
        $scoped = $this->scopedPharmacyIds(); // null = all, [] = none, [ids]

        if ($pharmacyFilter === null) {
            return $scoped;
        }

        // A filter was chosen. Honour it only if the actor may see that pharmacy.
        if ($scoped === null) {
            return [$pharmacyFilter];              // super-admin: any single store
        }
        if ($scoped === []) {
            return [];                              // no scope: nothing
        }

        return in_array($pharmacyFilter, $scoped, true) ? [$pharmacyFilter] : [];
    }

    /** Apply an id set to a query on spar_pharmacy_id. Null = no restriction. */
    private function applyIds($query, ?array $ids, string $column = 'spar_pharmacy_id')
    {
        if ($ids === null) {
            return $query;
        }
        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $ids);
    }

    /** Apply an id set to a query joined to journeys (dispense records). */
    private function applyIdsViaJourney($query, ?array $ids)
    {
        if ($ids === null) {
            return $query;
        }
        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('journey', fn ($j) => $j->whereIn('spar_pharmacy_id', $ids));
    }

    /** Pharmacies the actor may pick in the filter dropdown (own scope only). */
    public function selectablePharmacies()
    {
        return SparPharmacy::query()->visibleToCurrentActor()->orderBy('name')->get();
    }

    /**
     * The full merged Insights payload for the current actor, a period (days)
     * and an optional pharmacy filter.
     */
    public function insights(int $periodDays, ?int $pharmacyFilter = null): array
    {
        $ids = $this->effectivePharmacyIds($pharmacyFilter);
        $start = now()->subDays($periodDays);
        $end = now();

        return [
            'role' => app(SparIdentityProvider::class)->currentRole(),
            'period_days' => $periodDays,
            'range' => ['start' => $start, 'end' => $end],
            'counts' => $this->insightCounts($ids),
            'adherence' => $this->adherenceRate($ids, $start, $end),
            'renewals' => $this->renewalBreakdown($ids, $start),
            'patients' => $this->patientBreakdown($ids, $start),
            'orders' => $this->orderBreakdown($ids, $start),
            'onboarding_funnel' => $this->onboardingFunnelFor($ids),
            'consent' => $this->consentFor($ids),
            'messaging' => $this->messagingFor($ids),
            'monthly_trend' => $this->monthlyTrend($ids),
            'ranking' => $this->pharmacyRanking($ids, $start),
        ];
    }

    private function insightCounts(?array $ids): array
    {
        $pharmacyCount = $ids === null ? SparPharmacy::count() : count($ids);

        return [
            'pharmacies' => $pharmacyCount,
            'patients' => $this->applyIds(SparPatient::query(), $ids)->count(),
            'active_patients' => $this->applyIds(SparPatient::query(), $ids)->where('is_active', true)->count(),
            'active_journeys' => $this->applyIds(SparPrescriptionJourney::query(), $ids)->where('status', 'active')->count(),
        ];
    }

    private function adherenceRate(?array $ids, Carbon $start, Carbon $end): array
    {
        $q = fn () => $this->applyIdsViaJourney(
            SparDispenseRecord::whereBetween('due_date', [$start, $end]),
            $ids
        );

        $total = (clone $q())->whereIn('status', ['collected', 'delivered', 'missed', 'reminded'])->count();
        $onTime = (clone $q())->whereIn('status', ['collected', 'delivered'])->count();
        $overdue = (clone $q())->where('status', 'upcoming')->where('due_date', '<', now())->count();

        return [
            'rate' => $total > 0 ? round(($onTime / $total) * 100, 1) : 0.0,
            'total_due' => $total,
            'on_time' => $onTime,
            'overdue' => $overdue,
        ];
    }

    private function renewalBreakdown(?array $ids, Carbon $start): array
    {
        $base = fn () => $this->applyIds(
            SparPrescriptionJourney::where('updated_at', '>=', $start),
            $ids
        );

        $due = (clone $base())->where('status', 'renewal_due')->count();
        $renewed = (clone $base())->where('status', 'renewed')->count();
        $viaGp = (clone $base())->where('renewal_route', 'primary_doctor')->count();
        $viaZapmed = (clone $base())->where('renewal_route', 'zapmed')->count();

        $attempts = $renewed + $due;
        $routeTotal = $viaGp + $viaZapmed;

        return [
            'rate' => $attempts > 0 ? round(($renewed / $attempts) * 100, 1) : 0.0,
            'due' => $due,
            'renewed' => $renewed,
            'via_primary_doctor' => $viaGp,
            'via_zapmed' => $viaZapmed,
            'zapmed_conversion' => $routeTotal > 0 ? round(($viaZapmed / $routeTotal) * 100, 1) : 0.0,
        ];
    }

    private function patientBreakdown(?array $ids, Carbon $start): array
    {
        $base = fn () => $this->applyIds(SparPatient::query(), $ids);

        $total = (clone $base())->count();
        $active = (clone $base())->where('is_active', true)->count();
        $consented = (clone $base())->where('consent_status', 'opted_in')->count();
        $optedOut = (clone $base())->where('consent_status', 'opted_out')->count();
        $newThisPeriod = (clone $base())->where('created_at', '>=', $start)->count();

        return [
            'total' => $total,
            'active' => $active,
            'consented' => $consented,
            'opted_out' => $optedOut,
            'new_this_period' => $newThisPeriod,
            'opt_in_rate' => $total > 0 ? round(($consented / $total) * 100, 1) : 0.0,
        ];
    }

    private function orderBreakdown(?array $ids, Carbon $start): array
    {
        $base = fn () => $this->applyIds(SparOrder::where('created_at', '>=', $start), $ids);

        $total = (clone $base())->count();
        $collections = (clone $base())->where('type', 'collection')->count();
        $deliveries = (clone $base())->where('type', 'delivery')->count();
        $completed = (clone $base())->where('status', 'completed')->count();

        return [
            'total' => $total,
            'collections' => $collections,
            'deliveries' => $deliveries,
            'completed' => $completed,
            'collection_pct' => $total > 0 ? (int) round(($collections / $total) * 100) : 0,
            'avg_prep_minutes' => $this->avgPrepMinutes($ids, $start),
        ];
    }

    /**
     * Average order preparation time (requested → ready) in minutes.
     * Driver-safe: SQLite uses JULIANDAY; Postgres uses EXTRACT(EPOCH …).
     * Any other driver falls back to a portable PHP-side average.
     */
    private function avgPrepMinutes(?array $ids, Carbon $start): ?int
    {
        $base = fn () => $this->applyIds(
            SparOrder::where('created_at', '>=', $start)->whereNotNull('ready_at'),
            $ids
        );

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $avg = (clone $base())
                ->selectRaw('AVG((JULIANDAY(ready_at) - JULIANDAY(created_at)) * 24 * 60) as avg_mins')
                ->value('avg_mins');

            return $avg !== null ? (int) round($avg) : null;
        }

        if ($driver === 'pgsql') {
            $avg = (clone $base())
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (ready_at - created_at)) / 60) as avg_mins')
                ->value('avg_mins');

            return $avg !== null ? (int) round((float) $avg) : null;
        }

        if ($driver === 'mysql') {
            $avg = (clone $base())
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, ready_at)) / 60 as avg_mins')
                ->value('avg_mins');

            return $avg !== null ? (int) round((float) $avg) : null;
        }

        // Portable fallback: average in PHP.
        $rows = (clone $base())->get(['created_at', 'ready_at']);
        if ($rows->isEmpty()) {
            return null;
        }
        $mins = $rows->map(fn ($o) => $o->created_at->diffInMinutes($o->ready_at));

        return (int) round($mins->avg());
    }

    private function onboardingFunnelFor(?array $ids): array
    {
        $base = fn () => $this->applyIds(SparPatient::query(), $ids);

        $awaiting = (clone $base())->where('onboarding_status', 'awaiting_contact')->count();
        $pending = (clone $base())->where('onboarding_status', 'pending_consent')->count();
        $active = (clone $base())->where('onboarding_status', 'active')->count();
        $optedOut = (clone $base())->where('onboarding_status', 'opted_out')->count();
        $total = $awaiting + $pending + $active + $optedOut;

        return [
            'awaiting_contact' => $awaiting,
            'pending_consent' => $pending,
            'active' => $active,
            'opted_out' => $optedOut,
            'total' => $total,
            'activation_rate' => $total > 0 ? round($active / $total * 100, 1) : 0.0,
        ];
    }

    private function consentFor(?array $ids): array
    {
        $base = fn () => $this->applyIds(SparPatient::query(), $ids);
        $optedIn = (clone $base())->where('consent_status', 'opted_in')->count();
        $pending = (clone $base())->where('consent_status', 'pending')->count();
        $optedOut = (clone $base())->where('consent_status', 'opted_out')->count();
        $total = $optedIn + $pending + $optedOut;

        return [
            'opted_in' => $optedIn,
            'pending' => $pending,
            'opted_out' => $optedOut,
            'consent_rate' => $total > 0 ? round($optedIn / $total * 100, 1) : 0.0,
        ];
    }

    private function messagingFor(?array $ids): array
    {
        $q = $this->applyIdsViaJourney(
            SparDispenseRecord::whereNotNull('reminded_at'),
            $ids
        );

        return ['reminders_sent' => $q->count()];
    }

    /** Six-month completed-vs-due trend, scoped. */
    private function monthlyTrend(?array $ids): array
    {
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $q = fn () => $this->applyIdsViaJourney(
                SparDispenseRecord::whereBetween('due_date', [$start, $end]),
                $ids
            );

            $due = (clone $q())->count();
            $completed = (clone $q())->whereIn('status', ['collected', 'delivered'])->count();

            $months->push([
                'label' => $month->format('M Y'),
                'due' => $due,
                'completed' => $completed,
                'rate' => $due > 0 ? round(($completed / $due) * 100, 1) : 0.0,
            ]);
        }

        return $months->toArray();
    }

    /** Pharmacy performance ranking, scoped (never leaks out-of-scope stores). */
    public function pharmacyRanking(?array $ids, Carbon $start)
    {
        $query = SparPharmacy::query()->visibleToCurrentActor();

        // If a single-store filter is active, narrow to it.
        if ($ids !== null && count($ids) === 1) {
            $query->whereKey($ids[0]);
        } elseif ($ids === []) {
            $query->whereRaw('1 = 0');
        }

        return $query->withCount([
            'patients as active_patients' => fn ($q) => $q->where('is_active', true),
            'journeys as active_journeys' => fn ($q) => $q->where('status', 'active'),
            'orders as completed_orders' => fn ($q) => $q->where('status', 'completed')->where('created_at', '>=', $start),
        ])->orderByDesc('active_patients')->get();
    }

    /**
     * Flatten the insights payload into labelled rows for CSV export.
     *
     * @return array<int, array{0:string,1:string,2:int|float|string}>
     */
    public function toCsvRows(array $insights): array
    {
        $r = [];
        $r[] = ['Section', 'Metric', 'Value'];
        $r[] = ['Meta', 'Period (days)', $insights['period_days']];
        $r[] = ['Meta', 'Generated', now()->toDateTimeString()];

        foreach (['pharmacies', 'patients', 'active_patients', 'active_journeys'] as $k) {
            $r[] = ['Counts', ucwords(str_replace('_', ' ', $k)), $insights['counts'][$k]];
        }
        $r[] = ['Adherence', 'Rate %', $insights['adherence']['rate']];
        $r[] = ['Adherence', 'On time', $insights['adherence']['on_time']];
        $r[] = ['Adherence', 'Total due', $insights['adherence']['total_due']];
        $r[] = ['Adherence', 'Overdue', $insights['adherence']['overdue']];
        $r[] = ['Renewals', 'Rate %', $insights['renewals']['rate']];
        $r[] = ['Renewals', 'Renewed', $insights['renewals']['renewed']];
        $r[] = ['Renewals', 'Due', $insights['renewals']['due']];
        $r[] = ['Renewals', 'Via ZapMed', $insights['renewals']['via_zapmed']];
        $r[] = ['Renewals', 'Via primary doctor', $insights['renewals']['via_primary_doctor']];
        $r[] = ['Renewals', 'ZapMed conversion %', $insights['renewals']['zapmed_conversion']];
        $r[] = ['Patients', 'Total', $insights['patients']['total']];
        $r[] = ['Patients', 'Active', $insights['patients']['active']];
        $r[] = ['Patients', 'Consented', $insights['patients']['consented']];
        $r[] = ['Patients', 'Opted out', $insights['patients']['opted_out']];
        $r[] = ['Patients', 'New this period', $insights['patients']['new_this_period']];
        $r[] = ['Patients', 'Opt-in rate %', $insights['patients']['opt_in_rate']];
        $r[] = ['Orders', 'Total', $insights['orders']['total']];
        $r[] = ['Orders', 'Collections', $insights['orders']['collections']];
        $r[] = ['Orders', 'Deliveries', $insights['orders']['deliveries']];
        $r[] = ['Orders', 'Completed', $insights['orders']['completed']];
        $r[] = ['Orders', 'Avg prep (min)', $insights['orders']['avg_prep_minutes'] ?? 'n/a'];
        $r[] = ['Consent', 'Opted in', $insights['consent']['opted_in']];
        $r[] = ['Consent', 'Pending', $insights['consent']['pending']];
        $r[] = ['Consent', 'Opted out', $insights['consent']['opted_out']];
        $r[] = ['Consent', 'Consent rate %', $insights['consent']['consent_rate']];
        $r[] = ['Messaging', 'Reminders sent', $insights['messaging']['reminders_sent']];

        foreach ($insights['monthly_trend'] as $m) {
            $r[] = ['Monthly trend', $m['label'].' completed/due', $m['completed'].'/'.$m['due'].' ('.$m['rate'].'%)'];
        }
        foreach ($insights['ranking'] as $p) {
            $r[] = ['Pharmacy ranking', $p->name, 'patients='.$p->active_patients.', journeys='.$p->active_journeys.', completed='.$p->completed_orders];
        }

        return $r;
    }
}
