<?php

namespace App\Services\Operations;

use App\Enums\CycleStatus;
use App\Enums\UserRole;
use App\Models\FollowUpRequest;
use App\Models\Medication;
use App\Models\Order;
use App\Models\OrderAuthorisation;
use App\Models\PrescriptionItem;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPauseEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The Operations reports (Craig's guide 6.3). Admin only. Read-only. No clinical text: no notes, no results, no assessment
 * answers. Dates are South African dates, strictly checked (2026-02-31 is refused, not moved to March).
 *
 * Every report returns the same shape so one screen and one CSV export serve all of them:
 *   ['key', 'title', 'columns' => string[], 'rows' => array[] (same order as columns), 'summary' => [label => value], 'note' => ?string]
 */
class OperationsReports
{
    public const REPORTS = [
        'upcoming_repeats' => 'Upcoming script repeats',
        'customer_tracker' => 'Customer tracker',
        'authorisations' => 'Authorisation report',
        'user_medication' => 'User medication report',
        'addon_clients' => 'Add-on client report',
        'pause_cancel' => 'Pause and cancel analysis',
    ];

    private function tz(): string
    {
        return config('doctor.time.timezone');
    }

    /** @param array<string,mixed> $filters month (YYYY-MM) or from/to (YYYY-MM-DD), product_id, state (active|expired|all), status */
    public function run(User $user, string $key, array $filters = []): array
    {
        abort_unless($user->role === UserRole::Admin, 403);

        if (! array_key_exists($key, self::REPORTS)) {
            throw ValidationException::withMessages(['report' => 'Choose one of the reports.']);
        }

        [$from, $to] = $this->range($filters);
        $report = $this->{'build'.str_replace(' ', '', ucwords(str_replace('_', ' ', $key)))}($from, $to, $filters);
        $report['rows'] = array_slice($report['rows'], 0, (int) config('operations.reports.max_rows'));

        return array_merge(['note' => null], $report, ['key' => $key, 'title' => self::REPORTS[$key]]);
    }

    /** CSV of one report. Cells that start like a formula are neutralised. */
    public function csv(User $user, string $key, array $filters = []): string
    {
        $r = $this->run($user, $key, $filters);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, $r['columns']);
        foreach ($r['rows'] as $row) {
            fputcsv($out, array_map([$this, 'safeCell'], $row));
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }

    // ---- date handling -------------------------------------------------------------------------------------------

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} start of the first day, end of the last day (South African time) */
    public function range(array $filters): array
    {
        $tz = $this->tz();
        $month = $filters['month'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if ($month) {
            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $month)) {
                throw ValidationException::withMessages(['month' => 'Choose a month like 2026-11.']);
            }
            $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', $tz)->startOfDay();

            return [$start, $start->endOfMonth()];
        }

        $start = $from ? $this->day($from, 'from') : CarbonImmutable::now($tz)->startOfMonth();
        $end = $to ? $this->day($to, 'to')->endOfDay() : $start->endOfMonth();

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['to' => 'The end date is before the start date.']);
        }

        return [$start, $end];
    }

    private function day(string $value, string $field): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw ValidationException::withMessages([$field => 'Choose a valid date.']);
        }
        try {
            $d = CarbonImmutable::createFromFormat('Y-m-d', $value, $this->tz())->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Choose a valid date.']);
        }
        if ($d->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Choose a valid date.']);
        }

        return $d;
    }

    // ---- 1. upcoming script repeats ------------------------------------------------------------------------------

    private function buildUpcomingRepeats(CarbonImmutable $from, CarbonImmutable $to, array $f): array
    {
        $cycles = SubscriptionCycle::with(['subscription.user', 'subscription.plan'])
            ->where('status', CycleStatus::Scheduled->value)
            ->whereBetween('scheduled_for', [$from->utc(), $to->utc()])
            ->orderBy('scheduled_for')->get();

        $orders = Order::where('source_type', 'subscription')
            ->whereIn('source_ref', $cycles->pluck('subscription_id')->map(fn ($i) => (string) $i)->unique())->get()->keyBy('source_ref');

        $rows = $cycles->map(function (SubscriptionCycle $c) use ($orders) {
            $sub = $c->subscription;
            $order = $orders->get((string) $c->subscription_id);
            $when = $c->scheduled_for->setTimezone($this->tz());

            return [
                $order?->reference ?? '-',
                $sub?->user?->name ?? '-',
                $sub?->user?->email ?? '-',
                $this->channelLabel($sub?->user),
                $order?->service_category ?? $sub?->plan?->name ?? '-',
                $sub?->charge_mode === 'adhoc' ? 'Card on file (we charge)' : 'PayFast recurring',
                $when->format('Y-m-d'),
                $this->batchDay($when)->format('Y-m-d'),
                ucfirst((string) $sub?->status),
            ];
        })->all();

        return [
            'columns' => ['Order', 'Patient', 'Email', 'Contact by', 'Category', 'Payment type', 'Repeat date', 'Batch day', 'Order state'],
            'rows' => $rows,
            'summary' => ['Repeats this period' => count($rows)],
            'note' => 'Batch day is the next Monday or Wednesday on or after the repeat date.',
        ];
    }

    /** The first batch weekday on or after the date. */
    public function batchDay(\Carbon\CarbonInterface $date): CarbonImmutable
    {
        $days = config('operations.reports.batch_weekdays');
        $d = CarbonImmutable::instance($date)->startOfDay();
        for ($i = 0; $i < 7; $i++) {
            if (in_array((int) $d->dayOfWeek, $days, true)) {
                return $d;
            }
            $d = $d->addDay();
        }

        return CarbonImmutable::instance($date)->startOfDay();
    }

    // ---- 2. customer tracker -------------------------------------------------------------------------------------

    private function buildCustomerTracker(CarbonImmutable $from, CarbonImmutable $to, array $f): array
    {
        $q = Order::with('patient')->whereBetween('ordered_at', [$from->utc(), $to->utc()]);
        if (! empty($f['status'])) {
            $q->where('status', (string) $f['status']);
        }
        $orders = $q->orderByDesc('updated_at')->get();

        $action = config('operations.reports.action_statuses');
        $rows = $orders->map(fn (Order $o) => [
            $o->reference,
            $o->patient?->name ?? '-',
            $o->patient?->email ?? '-',
            $o->status,
            $o->service_category ?? '-',
            $o->ordered_at?->setTimezone($this->tz())->format('Y-m-d') ?? '-',
            $o->updated_at?->setTimezone($this->tz())->format('Y-m-d') ?? '-',
            in_array($o->status, $action, true) ? 'Yes' : '',
        ])->all();

        $summary = $orders->groupBy('status')->map->count()->sortDesc()->mapWithKeys(fn ($n, $s) => [$s => $n])->all();
        $summary = ['Customers (distinct)' => $orders->pluck('patient_id')->unique()->count(), 'Records needing action' => $orders->whereIn('status', $action)->count()] + $summary;

        return [
            'columns' => ['Order', 'Patient', 'Email', 'Status', 'Category', 'Ordered', 'Last update', 'Needs action'],
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    // ---- 3. authorisation report ---------------------------------------------------------------------------------

    private function buildAuthorisations(CarbonImmutable $from, CarbonImmutable $to, array $f): array
    {
        $list = OrderAuthorisation::with(['patient', 'order', 'doctor'])
            ->whereIn('status', ['active', 'expired'])
            ->whereDate('expires_on', '>=', $from->format('Y-m-d'))->whereDate('expires_on', '<=', $to->format('Y-m-d'))
            ->orderBy('expires_on')->get();

        $open = FollowUpRequest::open()->whereIn('order_id', $list->pluck('order_id'))->pluck('order_id')->flip();

        $rows = $list->map(fn (OrderAuthorisation $a) => [
            $a->order?->reference ?? '-',
            $a->patient?->name ?? '-',
            $a->patient?->email ?? '-',
            $a->doctor?->name ?? '-',
            $a->starts_on->format('Y-m-d'),
            $a->expires_on->format('Y-m-d'),
            $a->status === 'expired' ? 'Ended, repeats on hold' : 'Active',
            $open->has($a->order_id) ? 'Follow-up open' : '',
        ])->all();

        return [
            'columns' => ['Order', 'Patient', 'Email', 'Authorised by', 'Started', 'Ends', 'State', 'Follow-up'],
            'rows' => $rows,
            'summary' => ['Authorisations ending in this period' => count($rows), 'Already ended' => $list->where('status', 'expired')->count()],
        ];
    }

    // ---- 4. user medication report -------------------------------------------------------------------------------

    private function buildUserMedication(CarbonImmutable $from, CarbonImmutable $to, array $f): array
    {
        $state = $f['state'] ?? 'all';
        if (! in_array($state, ['all', 'active', 'expired'], true)) {
            throw ValidationException::withMessages(['state' => 'Choose active, expired or all.']);
        }

        $q = PrescriptionItem::with(['prescription.patient', 'medication'])
            ->whereHas('prescription', fn ($p) => $p->whereIn('status', ['signed', 'dispensed', 'expired'])
                ->whereBetween('created_at', [$from->utc(), $to->utc()]));

        if (! empty($f['product_id'])) {
            $q->where('medication_id', (int) $f['product_id']);
        }

        $today = CarbonImmutable::now($this->tz())->format('Y-m-d');
        $rows = [];
        foreach ($q->orderByDesc('id')->limit((int) config('operations.reports.max_rows') * 2)->get() as $item) {
            $rx = $item->prescription;
            $ended = $rx->status === 'expired' || ($rx->valid_until && $rx->valid_until->format('Y-m-d') < $today);
            if (($state === 'active' && $ended) || ($state === 'expired' && ! $ended)) {
                continue;
            }
            $rows[] = [
                $item->medication_name ?: ($item->medication?->name ?? '-'),
                $rx->patient?->name ?? '-',
                $rx->patient?->email ?? '-',
                $rx->reference,
                $rx->created_at?->setTimezone($this->tz())->format('Y-m-d') ?? '-',
                $rx->valid_until?->format('Y-m-d') ?? '-',
                $ended ? 'Expired' : 'Active',
            ];
        }

        return [
            'columns' => ['Product', 'Patient', 'Email', 'Prescription', 'Prescribed', 'Valid until', 'State'],
            'rows' => $rows,
            'summary' => ['Patients' => collect($rows)->pluck(2)->unique()->count(), 'Lines' => count($rows)],
            'note' => 'To act on a stock problem: send these patients to a Doctor Request and pause the affected orders from the order board.',
        ];
    }

    // ---- 5. add-on client report ---------------------------------------------------------------------------------

    private function buildAddonClients(CarbonImmutable $from, CarbonImmutable $to, array $f): array
    {
        $subs = Subscription::with(['user', 'plan'])
            ->whereHas('plan', fn ($q) => $q->where('slug', config('coach.plan_slug')))
            ->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->orderByDesc('id')->get();

        $orders = Order::where('source_type', 'subscription')->whereIn('source_ref', $subs->pluck('id')->map(fn ($i) => (string) $i))->get()->keyBy('source_ref');

        $rows = $subs->map(function (Subscription $s) use ($orders) {
            $o = $orders->get((string) $s->id);

            return [
                $s->user?->name ?? '-',
                $s->user?->email ?? '-',
                $o?->reference ?? '-',
                ucfirst($s->status),
                $o?->service_category ?? 'Weight loss',
                $s->plan?->name ?? 'Health coach',
                'R'.number_format(((int) ($s->plan?->price ?? 0)) / 100, 2),
                $s->created_at?->setTimezone($this->tz())->format('Y-m-d') ?? '-',
            ];
        })->all();

        // add-ons put on an order by Operations (see Add-ons)
        $extras = \App\Models\OrderAddon::with(['addon', 'order.patient'])->whereBetween('created_at', [$from->utc(), $to->utc()])->orderByDesc('id')->get();
        foreach ($extras as $x) {
            $rows[] = [
                $x->order?->patient?->name ?? '-',
                $x->order?->patient?->email ?? '-',
                $x->order?->reference ?? '-',
                ucfirst($x->status),
                $x->order?->service_category ?? '-',
                $x->addon?->name ?? 'Add-on',
                'R'.number_format($x->price_cents / 100, 2),
                $x->started_on?->format('Y-m-d') ?? '-',
            ];
        }
        $extraMonthly = $extras->where('status', 'active')->sum('price_cents');

        $monthly = $subs->where('status', 'active')->sum(fn ($s) => (int) ($s->plan?->price ?? 0)) + $extraMonthly;

        return [
            'columns' => ['Patient', 'Email', 'Order', 'Status', 'Category', 'Add-on', 'Charge', 'Started'],
            'rows' => $rows,
            'summary' => ['Add-ons started in this period' => count($rows), 'Active now' => $subs->where('status', 'active')->count() + $extras->where('status', 'active')->count(), 'Monthly value of the active ones' => 'R'.number_format($monthly / 100, 2)],
        ];
    }

    // ---- 6. pause and cancel analysis ----------------------------------------------------------------------------

    private function buildPauseCancel(CarbonImmutable $from, CarbonImmutable $to, array $f): array
    {
        $pauses = SubscriptionPauseEvent::with('subscription.user')
            ->whereBetween('paused_at', [$from->utc(), $to->utc()])->orderBy('paused_at')->get();
        $cancels = Subscription::with('user')->where('status', 'cancelled')
            ->whereBetween('cancelled_at', [$from->utc(), $to->utc()])->orderBy('cancelled_at')->get();

        $rows = [];
        foreach ($pauses as $p) {
            $rows[] = ['Pause', $p->paused_at->setTimezone($this->tz())->format('Y-m-d'), $p->subscription?->user?->name ?? '-', $this->initiatorLabel($p->initiator), $this->reason($p->paused_reason)];
        }
        foreach ($cancels as $c) {
            $rows[] = ['Cancel', $c->cancelled_at->setTimezone($this->tz())->format('Y-m-d'), $c->user?->name ?? '-', $this->initiatorLabel($c->cancelled_initiator), $this->reason($c->cancellation_reason)];
        }
        usort($rows, fn ($a, $b) => strcmp($a[1], $b[1]));

        $summary = [];
        foreach (['Pause' => $pauses->count(), 'Cancel' => $cancels->count()] as $kind => $total) {
            $by = collect($rows)->where(0, $kind)->groupBy(3)->map->count();
            $summary["{$kind}s in total"] = $total;
            foreach (['Patient', 'Operations', 'System', 'Not recorded'] as $who) {
                $n = (int) ($by[$who] ?? 0);
                if ($n > 0) {
                    $summary["{$kind}s by {$who}"] = $n.' ('.($total ? round($n / $total * 100) : 0).'%)';
                }
            }
        }

        $reasons = collect($rows)->groupBy(4)->map->count()->sortDesc();
        foreach ($reasons->take(10) as $reason => $n) {
            $summary['Reason: '.$reason] = $n.' ('.round($n / max(1, count($rows)) * 100).'%)';
        }

        return [
            'columns' => ['Action', 'Date', 'Patient', 'Started by', 'Reason'],
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    // ---- helpers -------------------------------------------------------------------------------------------------

    private function initiatorLabel(?string $i): string
    {
        return match ($i) {
            'patient' => 'Patient',
            'operations' => 'Operations',
            'system' => 'System',
            default => 'Not recorded',
        };
    }

    /** Reasons are free text: trimmed and shortened so the same reason counts together. */
    private function reason(?string $r): string
    {
        $r = trim((string) $r);

        return $r === '' ? 'No reason given' : mb_substr(mb_strtolower($r), 0, 80);
    }

    private function channelLabel(?User $u): string
    {
        return $u?->preferred_contact === 'whatsapp' ? 'WhatsApp' : 'Email';
    }

    private function safeCell(mixed $v): string
    {
        $v = (string) $v;

        return $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;
    }

    /** Products for the report filter. */
    public function products(): Collection
    {
        return Medication::orderBy('name')->limit(2000)->get(['id', 'name']);
    }
}
