<?php

namespace App\Services\Contro;

use App\Models\CatalogCoupon;
use App\Models\CatalogItem;
use App\Models\ImportQuarantine;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PatientProfile;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\UpstreamIngestedRow;
use Illuminate\Support\Facades\DB;

/**
 * Reconcile verification / parity report (blueprint §4 step 2).
 *
 * For each Contro entity set, compares:
 *   - staged     : rows landed in upstream_ingested_rows (what we pulled from Contro)
 *   - reconciled : canonical rows carrying that entity's Contro crosswalk (what we mapped)
 *   - quarantined: rows flagged for review (open)
 *   - unaccounted: staged - reconciled - quarantined  (should be 0 once a run is complete)
 *
 * Read-only. Used to sign off a backfill/delta run with Craig/Dave before cutover.
 */
class ControParityReport
{
    /**
     * Map each Contro entity_set to the canonical model that carries its crosswalk (for reconciled counts).
     * Entities whose canonical target has no direct 1:1 crosswalk row are marked null and reported as
     * staged/quarantine only (documented — nothing silently ignored).
     */
    private const CANONICAL = [
        'patients' => PatientProfile::class,
        'products' => CatalogItem::class,
        'coupons' => CatalogCoupon::class,
        'orders' => Order::class,
        'order_status_history' => OrderStatusHistory::class,
        'payments' => Payment::class,
        'prescriptions' => Prescription::class,
    ];

    /** @return array<string,array<string,int>> per-entity counts keyed by entity_set. */
    public function build(): array
    {
        $report = [];

        foreach (array_keys(config('contro.entities')) as $entitySet) {
            $staged = UpstreamIngestedRow::forEntity($entitySet)->count();
            $quarantined = ImportQuarantine::where('entity_set', $entitySet)->where('status', 'open')->count();

            $reconciled = 0;
            $model = self::CANONICAL[$entitySet] ?? null;
            if ($model !== null) {
                $reconciled = $model::query()
                    ->where('upstream_source', 'contro')
                    ->whereNotNull('upstream_id')
                    ->count();
            }

            $report[$entitySet] = [
                'staged' => $staged,
                'reconciled' => $reconciled,
                'quarantined' => $quarantined,
                'unaccounted' => max(0, $staged - $reconciled - $quarantined),
            ];
        }

        return $report;
    }

    /** True if every entity is fully accounted for (nothing staged-but-unhandled). */
    public function isClean(): bool
    {
        foreach ($this->build() as $counts) {
            if ($counts['unaccounted'] !== 0) {
                return false;
            }
        }

        return true;
    }

    /** Open quarantine grouped by (entity_set, reason) with counts. @return array<int,object> */
    public function quarantineSummary(): array
    {
        return ImportQuarantine::where('status', 'open')
            ->select('entity_set', 'reason', DB::raw('count(*) as total'))
            ->groupBy('entity_set', 'reason')
            ->orderBy('entity_set')
            ->get()
            ->all();
    }
}
