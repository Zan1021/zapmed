<?php

namespace Zapmed\SparCore\Services;

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
}
