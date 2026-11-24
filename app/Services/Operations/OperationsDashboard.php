<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\CoachFlag;
use App\Models\CoachingAssignment;
use App\Models\CoachMessage;
use App\Models\DoctorRequest;
use App\Models\FollowUpRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPauseEvent;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The Operations dashboard figures (Craig's guide 6.1) that the older Stats page does not have. Admin only.
 * Everything is a count or a total: no patient names, no weights, no symptoms, no notes. Dates are South African dates.
 *
 * @phpstan-type Dash array{range:array{from:string,to:string}, orders:array, signups:array, revenue:array, cancelled:array, abandoned:array, coaching:array, doctors:int}
 */
class OperationsDashboard
{
    public function __construct(
        private readonly OperationsReports $reports,
        private readonly AbandonedCheckouts $abandoned,
    ) {
    }

    private function tz(): string
    {
        return config('doctor.time.timezone');
    }

    /** @param array<string,mixed> $filters month or from/to, same as the reports */
    public function build(User $ops, array $filters = []): array
    {
        abort_unless($ops->role === UserRole::Admin, 403);

        [$from, $to] = $this->reports->range($filters);
        $now = CarbonImmutable::now($this->tz());

        return [
            'range' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'orders' => $this->orders($from, $to, $now),
            'signups' => $this->signups($from, $to),
            'revenue' => $this->revenue($from, $to),
            'cancelled' => $this->cancelled($from, $to),
            'abandoned' => $this->abandonedCarts(),
            'coaching' => $this->coaching($from, $to),
            'doctors' => User::where('role', UserRole::Doctor->value)->where('is_active', true)
                ->whereHas('doctorProfile', fn ($q) => $q->where('is_verified', true))->count(),
        ];
    }

    private function orders(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now): array
    {
        $in = fn () => Order::whereBetween('ordered_at', [$from->utc(), $to->utc()]);

        // one number per day, including the days with none, so the chart has no gaps
        $perDay = [];
        foreach ($in()->get(['ordered_at']) as $o) {
            $d = $o->ordered_at->setTimezone($this->tz())->format('Y-m-d');
            $perDay[$d] = ($perDay[$d] ?? 0) + 1;
        }
        $series = [];
        for ($d = $from; $d->lte($to) && count($series) < 366; $d = $d->addDay()) {
            $series[$d->format('Y-m-d')] = $perDay[$d->format('Y-m-d')] ?? 0;
        }

        return [
            'today' => Order::whereBetween('ordered_at', [$now->startOfDay()->utc(), $now->endOfDay()->utc()])->count(),
            'this_month' => Order::whereBetween('ordered_at', [$now->startOfMonth()->utc(), $now->endOfMonth()->utc()])->count(),
            'in_range' => array_sum($series),
            'per_day' => $series,
            'by_category' => $in()->selectRaw("coalesce(service_category, 'Other') as category, count(*) as n")->groupBy('category')->orderByDesc('n')->orderBy('category')->pluck('n', 'category')->map(fn ($n) => (int) $n)->all(),
        ];
    }

    private function signups(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $patients = fn () => User::where('role', UserRole::Patient->value);
        $onRepeat = Subscription::whereIn('status', ['active', 'payment_failed', 'paused'])->pluck('user_id')->unique()->flip();

        $split = function ($query) use ($onRepeat) {
            $rows = $query->get(['id', 'is_active']);

            return [
                'total' => $rows->count(),
                'active' => $rows->where('is_active', true)->count(),
                'inactive' => $rows->where('is_active', false)->count(),
                'subscription' => $rows->filter(fn ($u) => $onRepeat->has($u->id))->count(),
                'one_off' => $rows->reject(fn ($u) => $onRepeat->has($u->id))->count(),
            ];
        };

        return [
            'all_time' => $split($patients()),
            'in_range' => $split($patients()->whereBetween('created_at', [$from->utc(), $to->utc()])),
        ];
    }

    /** Money in (completed payments) and money still waiting (orders that are unpaid), in cents. */
    private function revenue(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $received = Payment::where('status', 'completed')->whereBetween('paid_at', [$from->utc(), $to->utc()]);
        $pending = Order::where('status', 'PendingPayment')->whereBetween('ordered_at', [$from->utc(), $to->utc()]);

        return [
            'received_cents' => (int) (clone $received)->sum('amount'),
            'received_count' => (clone $received)->count(),
            'pending_cents' => (int) (clone $pending)->sum('total_minor'),
            'pending_count' => (clone $pending)->count(),
            'failed_count' => Payment::where('status', 'failed')->whereBetween('created_at', [$from->utc(), $to->utc()])->count(),
        ];
    }

    /** Cancelled orders and cancelled repeat orders with their reasons as counts and percentages. */
    private function cancelled(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $orders = Order::where('status', 'Cancelled')->whereBetween('cancelled_at', [$from->utc(), $to->utc()])->get(['cancellation_reason']);
        $subs = Subscription::where('status', 'cancelled')->whereBetween('cancelled_at', [$from->utc(), $to->utc()])->get(['cancellation_reason']);

        $reasons = $orders->pluck('cancellation_reason')->merge($subs->pluck('cancellation_reason'))
            ->map(fn ($r) => trim((string) $r) === '' ? 'No reason given' : mb_substr(mb_strtolower(trim((string) $r)), 0, 80));
        $total = $reasons->count();

        return [
            'orders' => $orders->count(),
            'repeat_orders' => $subs->count(),
            'total' => $total,
            'reasons' => $reasons->countBy()->sortDesc()->map(fn ($n) => ['count' => $n, 'percent' => $total ? (int) round($n / $total * 100) : 0])->all(),
        ];
    }

    private function abandonedCarts(): array
    {
        $unpaid = Order::where('status', 'PendingPayment')->whereNull('archived_at');

        return [
            'unpaid_now' => (clone $unpaid)->count(),
            'unpaid_value_cents' => (int) (clone $unpaid)->sum('total_minor'),
            'older_than_a_day' => (clone $unpaid)->where('ordered_at', '<=', now()->subDay())->count(),
            'waiting_for_a_reminder' => $this->abandoned->candidates()->count(),
            'reminders_on' => (bool) config('operations.abandoned_checkout.notify_enabled'),
        ];
    }

    /** Counts only. Nothing about any one patient's weight, symptoms or messages. */
    private function coaching(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $coachMessages = CoachMessage::where('from', 'coach')->whereBetween('created_at', [$from->utc(), $to->utc()]);
        $reached = (clone $coachMessages)->distinct('patient_id')->count('patient_id');
        $replied = CoachMessage::where('from', '!=', 'coach')->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->whereIn('patient_id', (clone $coachMessages)->select('patient_id'))->distinct('patient_id')->count('patient_id');

        return [
            'active_coached_patients' => CoachingAssignment::active()->count(),
            'outreach_messages' => (clone $coachMessages)->count(),
            'patients_reached' => $reached,
            'patients_replied' => $replied,
            'reply_percent' => $reached ? (int) round($replied / $reached * 100) : 0,
            'safety_flags_in_range' => CoachFlag::whereBetween('created_at', [$from->utc(), $to->utc()])->count(),
            'safety_flags_open' => CoachFlag::open()->count(),
            'follow_ups_open' => FollowUpRequest::open()->count(),
            'follow_ups_overdue' => FollowUpRequest::open()->where('due_at', '<', now())->count(),
            'doctor_requests_open' => DoctorRequest::where('status', '!=', DoctorRequest::COMPLETED)->count(),
            'pauses_in_range' => SubscriptionPauseEvent::whereBetween('paused_at', [$from->utc(), $to->utc()])->count(),
            'weight' => $this->weightProgress($from, $to),
        ];
    }

    /**
     * Average kilograms lost over the period by coached patients who logged at least twice. Shown only when three or more patients
     * are behind the number, so it can never point at one person.
     *
     * @return array{patients:int, average_kg_lost:?float}
     */
    private function weightProgress(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $ids = CoachingAssignment::active()->pluck('patient_id');
        $changes = \App\Models\WeightEntry::whereIn('user_id', $ids)
            ->whereDate('logged_on', '>=', $from->format('Y-m-d'))->whereDate('logged_on', '<=', $to->format('Y-m-d'))
            ->orderBy('logged_on')->get(['user_id', 'weight_kg'])
            ->groupBy('user_id')->filter(fn ($g) => $g->count() >= 2)
            ->map(fn ($g) => (float) $g->first()->weight_kg - (float) $g->last()->weight_kg);

        return [
            'patients' => $changes->count(),
            'average_kg_lost' => $changes->count() >= 3 ? round($changes->avg(), 1) : null,
        ];
    }
}
