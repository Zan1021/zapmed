<?php

namespace App\Services\Alerts;

use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\AlertDefinition;
use App\Models\Appointment;
use App\Models\CrmLead;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Alert scanner — the Laravel equivalent of Mark's alerts ScannerRunner + Detectors.
 *
 * Runs every detector, raises alerts idempotently (Alert::raise dedupes by key), then AUTO-RESOLVES:
 * any currently-active alert belonging to an auto_resolve definition whose dedupe_key was NOT seen this
 * run is auto-closed (its condition cleared). This mirrors the reference's resolveCleared sweep.
 *
 * The reference's in-process setInterval maps to our scheduled Artisan command (alerts:scan), wired in
 * routes/console.php. Detectors read thresholds from each definition's threshold_config so ops can tune
 * them at runtime without a deploy.
 */
class AlertScanner
{
    /**
     * Run all detectors and the auto-resolve sweep.
     *
     * @return array{raised:int,auto_closed:int,seen:int}
     */
    public function scan(): array
    {
        $seenKeys = [];
        $raised = 0;

        foreach ([
            'ordersStale',
            'ordersStaleCritical',
            'pendingBooking',
            'abandonedCarts',
            'failedPayments',
            'repeatFailedPayments',
            'consultNoShow',
            'coldSignups',
        ] as $detector) {
            [$count, $keys] = $this->{$detector}();
            $raised += $count;
            $seenKeys = array_merge($seenKeys, $keys);
        }

        $autoClosed = $this->autoResolveCleared($seenKeys);

        return ['raised' => $raised, 'auto_closed' => $autoClosed, 'seen' => count($seenKeys)];
    }

    private function def(string $code): ?AlertDefinition
    {
        return AlertDefinition::where('code', $code)->where('is_active', true)->first();
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array{0:int,1:array<int,string>}
     */
    private function raiseBatch(string $code, string $severity, array $rows): array
    {
        $raised = 0;
        $keys = [];
        foreach ($rows as $r) {
            Alert::raise([
                'definition_code' => $code,
                'severity' => $severity,
                'title' => $r['title'],
                'detail' => $r['detail'] ?? null,
                'subject_type' => $r['subject_type'],
                'subject_id' => $r['subject_id'],
                'dedupe_key' => $r['dedupe_key'],
                'metadata' => $r['metadata'] ?? null,
            ]);
            $raised++;
            $keys[] = $r['dedupe_key'];
        }

        return [$raised, $keys];
    }

    /** How long an order has sat in its current status, in hours (from latest history row, else created). */
    private function ordersStaleBase(int $staleHours): \Illuminate\Support\Collection
    {
        $threshold = now()->subHours($staleHours);

        return Order::query()
            ->whereNotIn('status', ['Delivered', 'Completed', 'Cancelled', 'RefundComplete'])
            ->get()
            ->filter(function (Order $o) use ($threshold) {
                $last = $o->statusHistory()->max('occurred_at') ?? $o->created_at;
                return $last !== null && Carbon::parse($last)->lt($threshold);
            });
    }

    private function ordersStale(): array
    {
        $def = $this->def('orders.stale');
        if (! $def) {
            return [0, []];
        }
        $hours = (int) $def->threshold('stale_hours', 24);

        $rows = $this->ordersStaleBase($hours)->map(fn (Order $o) => [
            'title' => "Order {$o->reference} stale at {$o->status}",
            'detail' => "No status change in {$hours}h.",
            'subject_type' => 'order',
            'subject_id' => $o->id,
            'dedupe_key' => "orders.stale:{$o->id}",
            'metadata' => ['status' => $o->status],
        ])->values()->all();

        return $this->raiseBatch('orders.stale', 'warning', $rows);
    }

    private function ordersStaleCritical(): array
    {
        $def = $this->def('orders.stale_critical');
        if (! $def) {
            return [0, []];
        }
        $hours = (int) $def->threshold('stale_hours', 72);

        $rows = $this->ordersStaleBase($hours)->map(fn (Order $o) => [
            'title' => "Order {$o->reference} CRITICALLY stale at {$o->status}",
            'detail' => "No status change in {$hours}h.",
            'subject_type' => 'order',
            'subject_id' => $o->id,
            'dedupe_key' => "orders.stale_critical:{$o->id}",
            'metadata' => ['status' => $o->status],
        ])->values()->all();

        return $this->raiseBatch('orders.stale_critical', 'critical', $rows);
    }

    private function pendingBooking(): array
    {
        $def = $this->def('orders.pending_booking');
        if (! $def) {
            return [0, []];
        }
        $hours = (int) $def->threshold('stale_hours', 48);
        $threshold = now()->subHours($hours);

        $rows = Order::where('status', 'PendingBooking')
            ->get()
            ->filter(function (Order $o) use ($threshold) {
                $last = $o->statusHistory()->max('occurred_at') ?? $o->created_at;
                return $last !== null && Carbon::parse($last)->lt($threshold);
            })
            ->map(fn (Order $o) => [
                'title' => "Order {$o->reference} awaiting booking (~{$hours}h)",
                'detail' => 'Follow up to prompt consultation booking.',
                'subject_type' => 'order',
                'subject_id' => $o->id,
                'dedupe_key' => "orders.pending_booking:{$o->id}",
                'metadata' => [],
            ])->values()->all();

        return $this->raiseBatch('orders.pending_booking', 'warning', $rows);
    }

    /** Rolled-up abandoned-cart alert: one alert for the cohort of unpaid PendingPayment orders. */
    private function abandonedCarts(): array
    {
        $def = $this->def('orders.abandoned_carts');
        if (! $def) {
            return [0, []];
        }
        $hours = (int) $def->threshold('stale_hours', 24);
        $threshold = now()->subHours($hours);

        $count = Order::where('status', 'PendingPayment')
            ->where('created_at', '<', $threshold)
            ->count();

        if ($count === 0) {
            return [0, []]; // condition cleared — the sweep will auto-close any open cohort alert
        }

        return $this->raiseBatch('orders.abandoned_carts', 'informational', [[
            'title' => "{$count} cart(s) abandoned at PendingPayment",
            'detail' => "{$count} unpaid carts older than {$hours}h. Conversion follow-up, not an operational incident.",
            'subject_type' => 'order_cohort',
            'subject_id' => '0',
            'dedupe_key' => 'orders.abandoned_carts:cohort',
            'metadata' => ['count' => $count, 'min_age_hours' => $hours],
        ]]);
    }

    private function failedPayments(): array
    {
        if (! $this->def('payments.failed')) {
            return [0, []];
        }

        $rows = Payment::where('status', 'failed')
            ->where(fn ($q) => $q->where('updated_at', '>=', now()->subDay()))
            ->get()
            ->map(fn (Payment $p) => [
                'title' => "Payment {$p->reference} failed ({$p->provider})",
                'detail' => $p->failure_reason,
                'subject_type' => 'payment',
                'subject_id' => $p->id,
                'dedupe_key' => "payments.failed:{$p->id}",
                'metadata' => ['order_id' => $p->order_id, 'provider' => $p->provider],
            ])->values()->all();

        return $this->raiseBatch('payments.failed', 'warning', $rows);
    }

    /** Orders sitting in a repeat-failure status — the "multiple consecutive failures" critical alert. */
    private function repeatFailedPayments(): array
    {
        $def = $this->def('payments.repeat_failed');
        if (! $def) {
            return [0, []];
        }

        $rows = Order::whereIn('status', ['RepeatPaymentFailed', 'TwoRepeatFailures', 'ThreeRepeatFailures'])
            ->get()
            ->map(fn (Order $o) => [
                'title' => "Order {$o->reference}: repeat payment failure ({$o->status})",
                'detail' => 'Multiple consecutive payment failures on the same order.',
                'subject_type' => 'order',
                'subject_id' => $o->id,
                'dedupe_key' => "payments.repeat_failed:{$o->id}",
                'metadata' => ['status' => $o->status],
            ])->values()->all();

        return $this->raiseBatch('payments.repeat_failed', 'critical', $rows);
    }

    private function consultNoShow(): array
    {
        if (! $this->def('consults.no_show')) {
            return [0, []];
        }

        $rows = Appointment::where('status', 'no_show')
            ->get()
            ->map(fn (Appointment $a) => [
                'title' => "Consult no-show (appointment {$a->reference})",
                'detail' => null,
                'subject_type' => 'appointment',
                'subject_id' => $a->id,
                'dedupe_key' => "consults.no_show:{$a->id}",
                'metadata' => ['patient_id' => $a->patient_id],
            ])->values()->all();

        return $this->raiseBatch('consults.no_show', 'warning', $rows);
    }

    private function coldSignups(): array
    {
        $def = $this->def('crm.cold_signup');
        if (! $def) {
            return [0, []];
        }
        $days = (int) $def->threshold('stale_days', 7);
        $threshold = now()->subDays($days);

        $rows = CrmLead::whereIn('current_stage', ['signed_up', 'intake_started'])
            ->where('stage_entered_at', '<', $threshold)
            ->get()
            ->map(fn (CrmLead $l) => [
                'title' => "Cold sign-up at {$l->current_stage->value} (~{$days}d)",
                'detail' => null,
                'subject_type' => 'lead',
                'subject_id' => $l->id,
                'dedupe_key' => "crm.cold_signup:{$l->id}",
                'metadata' => ['stage' => $l->current_stage->value],
            ])->values()->all();

        return $this->raiseBatch('crm.cold_signup', 'informational', $rows);
    }

    /**
     * Auto-close active alerts whose condition cleared: for each auto_resolve definition, any active
     * alert whose dedupe_key was NOT seen this run is auto-closed.
     *
     * @param  array<int,string>  $seenKeys
     */
    private function autoResolveCleared(array $seenKeys): int
    {
        $autoResolveCodes = AlertDefinition::where('auto_resolve', true)->pluck('code')->all();

        $stale = Alert::query()
            ->whereIn('definition_code', $autoResolveCodes)
            ->whereIn('status', AlertStatus::activeValues())
            ->whereNotIn('dedupe_key', $seenKeys ?: ['__none__'])
            ->get();

        foreach ($stale as $alert) {
            $alert->autoClose();
        }

        return $stale->count();
    }
}
