<?php

namespace App\Services\Finance;

use App\Enums\ReconStatus;
use App\Enums\RevenueKind;
use App\Models\FinanceReconEntry;
use App\Models\FinanceRevenueEntry;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Finance read/write service — parity with Mark's finance_reports module (RevenueRepo / ReconRepo /
 * ReportRepo).
 *
 * ⚠️ Bookkeeping only. Recording revenue/recon entries NEVER moves money — it records the result of
 * charges that happened in the live checkout / PayFast webhook flow. Safe to run during import.
 *
 * Refunds/chargebacks/discounts are stored as NEGATIVE amounts; callers pass positive magnitudes and
 * this service applies the sign, so a caller can't accidentally book a positive refund.
 */
class FinanceService
{
    /**
     * Record a revenue ledger entry. For refund/chargeback/discount kinds, the amount is forced
     * negative regardless of the sign passed in.
     */
    public function recordRevenue(
        RevenueKind $kind,
        int $amountCents,
        \DateTimeInterface $effectiveDate,
        string $revenueCategory = 'other',
        ?string $serviceLine = null,
        ?int $paymentId = null,
        ?int $orderId = null,
        ?int $subscriptionId = null,
        ?int $principalId = null,
        ?int $createdBy = null,
        ?string $notes = null,
    ): FinanceRevenueEntry {
        $magnitude = abs($amountCents);
        $signed = $kind->isNegative() ? -$magnitude : $magnitude;

        return FinanceRevenueEntry::create([
            'kind' => $kind->value,
            'amount_cents' => $signed,
            'effective_date' => Carbon::parse($effectiveDate)->toDateString(),
            'revenue_category' => $revenueCategory,
            'service_line' => $serviceLine,
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'subscription_id' => $subscriptionId,
            'principal_id' => $principalId,
            'created_by' => $createdBy,
            'notes' => $notes,
        ]);
    }

    /**
     * Book cash_collected from a captured payment (idempotent per payment). Convenience over
     * recordRevenue for the common "PayFast 2xx" path.
     */
    public function recordCashFromPayment(Payment $payment, ?string $serviceLine = null, ?int $createdBy = null): FinanceRevenueEntry
    {
        return FinanceRevenueEntry::firstOrCreate(
            [
                'kind' => RevenueKind::CashCollected->value,
                'payment_id' => $payment->id,
            ],
            [
                'amount_cents' => abs((int) $payment->amount),
                'effective_date' => Carbon::parse($payment->paid_at ?? now())->toDateString(),
                'revenue_category' => $payment->payment_type ?? 'other',
                'service_line' => $serviceLine,
                'order_id' => $payment->order_id,
                'principal_id' => $payment->patient_id,
                'created_by' => $createdBy,
            ],
        );
    }

    // ---- revenue reads --------------------------------------------------------------------------

    /**
     * Sum revenue for a window, grouped by kind.
     *
     * @return array<string,array{amount_cents:int,entry_count:int}>
     */
    public function revenueSummary(\DateTimeInterface $since, \DateTimeInterface $until, ?string $serviceLine = null): array
    {
        $rows = FinanceRevenueEntry::query()
            ->between($since, $until)
            ->when($serviceLine, fn ($q) => $q->where('service_line', $serviceLine))
            ->selectRaw('kind, COALESCE(SUM(amount_cents),0) as total, COUNT(*) as cnt')
            ->groupBy('kind')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->kind->value] = [
                'amount_cents' => (int) $row->total,
                'entry_count' => (int) $row->cnt,
            ];
        }

        return $out;
    }

    /**
     * Daily cash-collected vs recognised series. Only days with entries are returned.
     *
     * @return array<int,array{date:string,cash_cents:int,recognised_cents:int}>
     */
    public function revenueSeries(\DateTimeInterface $since, \DateTimeInterface $until, ?string $serviceLine = null): array
    {
        return FinanceRevenueEntry::query()
            ->between($since, $until)
            ->when($serviceLine, fn ($q) => $q->where('service_line', $serviceLine))
            ->selectRaw('effective_date')
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = ? THEN amount_cents ELSE 0 END),0) as cash", [RevenueKind::CashCollected->value])
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = ? THEN amount_cents ELSE 0 END),0) as recognised", [RevenueKind::Recognised->value])
            ->groupBy('effective_date')
            ->orderBy('effective_date')
            ->get()
            ->map(fn ($r) => [
                'date' => Carbon::parse($r->effective_date)->toDateString(),
                'cash_cents' => (int) $r->cash,
                'recognised_cents' => (int) $r->recognised,
            ])
            ->all();
    }

    // ---- reconciliation -------------------------------------------------------------------------

    /**
     * Create/upsert a reconciliation row for an order + pharmacy invoice (idempotent on the
     * (order, invoice) pair — parity with uq_recon_order_invoice).
     */
    public function upsertRecon(
        Order $order,
        ?int $pharmacyAmountCents = null,
        ?int $paymentAmountCents = null,
        ?string $pharmacyInvoiceRef = null,
        ?int $paymentId = null,
        ?string $notes = null,
    ): FinanceReconEntry {
        return FinanceReconEntry::updateOrCreate(
            [
                'order_id' => $order->id,
                'pharmacy_invoice_ref' => $pharmacyInvoiceRef,
            ],
            [
                'payment_id' => $paymentId,
                'pharmacy_amount_cents' => $pharmacyAmountCents,
                'payment_amount_cents' => $paymentAmountCents,
                'notes' => $notes,
            ],
        );
    }

    /**
     * Grade a recon entry as matched — but only if within the configured tolerance; otherwise
     * it becomes 'partial'. Mirrors Mark's tolerance auto-match. Returns the resulting status.
     */
    public function match(FinanceReconEntry $recon, ?int $actorId = null): ReconStatus
    {
        $tolerance = (int) config('analytics.finance.recon_auto_match_tolerance_cents', 100);
        $status = abs($recon->delta_cents) <= $tolerance ? ReconStatus::Matched : ReconStatus::Partial;

        $recon->fill([
            'status' => $status->value,
            'matched_at' => $status === ReconStatus::Matched ? now() : null,
            'matched_by' => $status === ReconStatus::Matched ? $actorId : null,
        ])->save();

        return $status;
    }

    public function dispute(FinanceReconEntry $recon, string $notes): FinanceReconEntry
    {
        $recon->fill(['status' => ReconStatus::Disputed->value, 'notes' => $notes])->save();

        return $recon;
    }

    public function writeOff(FinanceReconEntry $recon, string $reason, ?int $actorId = null): FinanceReconEntry
    {
        if ($recon->status === ReconStatus::Matched) {
            throw new RuntimeException('Cannot write off an already-matched reconciliation entry.');
        }

        $recon->fill([
            'status' => ReconStatus::WrittenOff->value,
            'written_off_at' => now(),
            'written_off_by' => $actorId,
            'written_off_reason' => $reason,
        ])->save();

        return $recon;
    }

    /**
     * The pharmacy reconciliation worklist: unmatched/partial/disputed rows, worst delta first.
     *
     * @return Collection<int,FinanceReconEntry>
     */
    public function reconWorklist(): Collection
    {
        return FinanceReconEntry::query()
            ->whereIn('status', [ReconStatus::Unmatched->value, ReconStatus::Partial->value, ReconStatus::Disputed->value])
            ->with(['order', 'payment'])
            ->get()
            ->sortByDesc(fn (FinanceReconEntry $r) => abs($r->delta_cents))
            ->values();
    }

    /**
     * Flat rows for CSV export of the revenue ledger in a window.
     *
     * @return array<int,array<string,mixed>>
     */
    public function revenueExportRows(\DateTimeInterface $since, \DateTimeInterface $until): array
    {
        return FinanceRevenueEntry::query()
            ->between($since, $until)
            ->orderBy('effective_date')
            ->get()
            ->map(fn (FinanceRevenueEntry $e) => [
                'effective_date' => Carbon::parse($e->effective_date)->toDateString(),
                'kind' => $e->kind->value,
                'revenue_category' => $e->revenue_category,
                'service_line' => $e->service_line,
                'amount_cents' => $e->amount_cents,
                'amount_rand' => number_format($e->amount_cents / 100, 2, '.', ''),
                'currency' => $e->currency,
                'order_id' => $e->order_id,
                'payment_id' => $e->payment_id,
                'subscription_id' => $e->subscription_id,
                'notes' => $e->notes,
            ])
            ->all();
    }
}
