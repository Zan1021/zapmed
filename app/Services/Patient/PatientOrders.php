<?php

namespace App\Services\Patient;

use App\Enums\CycleStatus;
use App\Models\FollowUpRequest;
use App\Models\Order;
use App\Models\OrderAuthorisation;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Doctor\AuthorisationService;
use App\Services\Orders\OrderStatusMachine;
use App\Services\Subscriptions\SubscriptionLifecycle;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * What a patient sees and may do on the My Orders tab (Craig's guide 3.1, 3.2).
 *
 *  - Every method takes the signed-in patient and only ever loads THEIR orders. Someone else's order id is a 404.
 *  - Pause and cancel need a reason and are recorded as done by the patient. Continue works within 90 days of the pause.
 *    Paused and cancelled orders never charge or repeat: that is the lifecycle's job, this class only asks it.
 *  - Archiving only hides an order from the list. It changes no status and deletes nothing.
 *  - Only repeat (subscription) orders can be paused, continued or cancelled here. A one-off order is archived or left.
 */
class PatientOrders
{
    public function __construct(
        private readonly SubscriptionLifecycle $lifecycle,
        private readonly AuthorisationService $authorisations,
    ) {
    }

    /** @return Collection<int,array<string,mixed>> */
    public function cards(User $patient, bool $archived = false): Collection
    {
        $q = Order::where('patient_id', $patient->id)->orderByDesc('id');
        $archived ? $q->whereNotNull('archived_at') : $q->whereNull('archived_at');

        return $q->limit(200)->get()->map(fn (Order $o) => $this->card($o));
    }

    /** @return array<string,mixed> */
    public function card(Order $order): array
    {
        $sub = $this->subscriptionOf($order);
        $status = (string) $order->status;
        $subStatus = $sub?->status;
        $openFollowUp = FollowUpRequest::open()->where('order_id', $order->id)->first();
        $auth = OrderAuthorisation::where('order_id', $order->id)->whereIn('status', ['active', 'expired'])->latest('id')->first();
        $held = $sub ? $this->lifecycle->repeatBlockedBy($sub) : null;
        $tracking = app(\App\Services\Operations\OrderTrackingService::class)->latest($order);

        $paused = $subStatus === 'paused';
        $cancelled = $subStatus === 'cancelled' || $status === 'Cancelled';

        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'ordered_at' => $order->ordered_at,
            'category' => $order->service_category,
            'status' => $status,
            'status_label' => $this->label($paused ? 'Paused' : ($cancelled ? 'Cancelled' : $status)),
            'is_repeat' => (bool) $sub,
            'subscription_status' => $subStatus,
            'repeats' => $this->repeats($sub, $auth),
            'next_payment' => $this->nextPayment($sub, $held),
            'hold' => $this->holdText($held, $openFollowUp, $auth),
            'renewal' => $auth ? [
                'ends_on' => $auth->expires_on,
                'due' => $this->authorisations->isDueForReview($auth),
            ] : null,
            'follow_up' => $openFollowUp ? ['due_at' => $openFollowUp->due_at, 'status' => $openFollowUp->status, 'is_free' => $openFollowUp->is_free] : null,
            'tracking_url' => $tracking?->url ?? ($order->metadata['tracking_url'] ?? null),
            'tracking' => $tracking ? [
                'courier' => $tracking->courier,
                'number' => $tracking->tracking_number,
                'at' => $tracking->tracked_at,
                'status' => config('operations.tracking.statuses.'.$tracking->status, $tracking->status),
            ] : null,
            'resume' => $this->resume($order, $sub),
            'paused_until_deadline' => $paused ? $this->resumeDeadline($sub) : null,
            'can_pause' => $sub && in_array($subStatus, ['active', 'payment_failed'], true),
            'can_continue' => $paused && $this->resumeDeadline($sub) && ! $this->resumeDeadline($sub)->isPast(),
            'can_cancel' => $sub && $subStatus !== 'cancelled' && $subStatus !== 'pending',
            'can_archive' => $order->archived_at === null && $this->archivable($order, $sub),
            'archived' => $order->archived_at !== null,
        ];
    }

    public function pause(User $patient, int $orderId, string $reason): void
    {
        [$order, $sub] = $this->ownedRepeatOrder($patient, $orderId);

        $this->guard(fn () => $this->lifecycle->pause($sub, null, $reason, $patient->id, 'patient'), 'reason');
        $this->moveOrder($order, 'Paused', 'patient', $patient, 'Patient paused the order');
    }

    public function continue(User $patient, int $orderId): void
    {
        [$order, $sub] = $this->ownedRepeatOrder($patient, $orderId);

        $this->guard(fn () => $this->lifecycle->resume($sub, $patient->id), 'order');
        $this->moveOrder($order, 'Processing', 'patient', $patient, 'Patient continued the order');
    }

    public function cancel(User $patient, int $orderId, string $reason): void
    {
        [$order, $sub] = $this->ownedRepeatOrder($patient, $orderId);

        if ($sub->status === 'pending') {
            throw ValidationException::withMessages(['order' => 'This order has not started yet. You can archive it instead.']);
        }

        $this->guard(fn () => $this->lifecycle->cancel($sub, $reason, 'patient', $patient->id), 'reason');
        $this->moveOrder($order, 'Cancelled', 'patient', $patient, 'Patient cancelled the order');
    }

    /** Restart the checkout of a repeat order that was never paid. The caller sends the patient on to the payment page. */
    public function restartSubscriptionCheckout(User $patient, int $orderId): void
    {
        [$order, $sub] = $this->ownedRepeatOrder($patient, $orderId);

        if ($sub->status !== 'pending' || ! $sub->plan) {
            throw ValidationException::withMessages(['order' => 'This order has no unfinished checkout.']);
        }

        app(\App\Services\Subscriptions\SubscriptionCheckout::class)->begin($patient, $sub->plan, $sub);
    }

    public function archive(User $patient, int $orderId): void
    {
        $order = $this->owned($patient, $orderId);

        if (! $this->archivable($order, $this->subscriptionOf($order))) {
            throw ValidationException::withMessages(['order' => 'You can only archive orders that are cancelled, ended or not finished.']);
        }

        $order->update(['archived_at' => now()]);
    }

    public function unarchive(User $patient, int $orderId): void
    {
        $this->owned($patient, $orderId)->update(['archived_at' => null]);
    }

    public function owned(User $patient, int $orderId): Order
    {
        return Order::where('patient_id', $patient->id)->findOrFail($orderId);
    }

    public function subscriptionOf(Order $order): ?Subscription
    {
        if ($order->source_type !== 'subscription' || ! $order->source_ref) {
            return null;
        }

        return Subscription::where('id', (int) $order->source_ref)->where('user_id', $order->patient_id)->first();
    }

    public function label(string $status): string
    {
        return config("patient.orders.status_labels.{$status}") ?? 'In progress';
    }

    private function ownedRepeatOrder(User $patient, int $orderId): array
    {
        $order = $this->owned($patient, $orderId);
        $sub = $this->subscriptionOf($order);

        if (! $sub) {
            throw ValidationException::withMessages(['order' => 'This order is not a repeat order, so it cannot be paused or cancelled here.']);
        }

        return [$order, $sub];
    }

    /** Lifecycle problems come back as plain RuntimeExceptions; show them to the patient as a message. */
    private function guard(callable $do, string $field): void
    {
        try {
            $do();
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }

    /** Keep the order's own status in step with the repeat order when the rules allow it. Never forces a move. */
    private function moveOrder(Order $order, string $to, string $trigger, User $patient, string $note): void
    {
        $order->refresh();

        if ($order->status !== $to && OrderStatusMachine::isTransitionAllowed((string) $order->status, $to, $trigger)) {
            $order->transitionTo($to, $trigger, ['triggered_by' => $patient->id, 'notes' => $note]);
        }
    }

    private function archivable(Order $order, ?Subscription $sub): bool
    {
        if (in_array($order->status, config('patient.orders.archivable_statuses'), true)) {
            return true;
        }
        if ($sub && $sub->status === 'cancelled') {
            return true;
        }

        // ended: it has authorisation records, none is valid any more and nothing is still repeating
        $stillRepeating = $sub && in_array($sub->status, ['active', 'paused', 'payment_failed', 'pending'], true);

        return ! $stillRepeating && $this->authorisations->blocksRepeat($order);
    }

    /** "4 of 6": how many repeats the current authorisation covers and how many have been used. */
    private function repeats(?Subscription $sub, ?OrderAuthorisation $auth): ?array
    {
        if (! $sub || ! $sub->repeat_cycle_days || ! $auth) {
            return null;
        }

        $days = max(1, $auth->starts_on->diffInDays($auth->expires_on));
        $total = (int) floor($days / (int) $sub->repeat_cycle_days);
        $used = $sub->cycles()->whereIn('status', [CycleStatus::Fulfilled->value, CycleStatus::Placed->value])
            ->where('created_at', '>=', $auth->starts_on)->count();

        return ['used' => min($used, $total), 'total' => $total, 'left' => max(0, $total - $used)];
    }

    private function nextPayment(?Subscription $sub, ?string $held): ?array
    {
        if (! $sub || $sub->status !== 'active') {
            return null;
        }

        $cycle = $sub->cycles()->where('status', CycleStatus::Scheduled->value)->orderBy('scheduled_for')->first();
        if (! $cycle) {
            return null;
        }

        return [
            'date' => $cycle->scheduled_for,
            'amount_cents' => (int) ($cycle->metadata['amount_cents'] ?? $sub->plan?->price ?? 0),
            'on_hold' => $held !== null,
        ];
    }

    private function holdText(?string $held, ?FollowUpRequest $followUp, ?OrderAuthorisation $auth): ?string
    {
        return match (true) {
            $held === null => null,
            $held === 'AuthorisationExpired' => 'Your doctor needs to renew your treatment before the next repeat. Book a short check-in.',
            $followUp !== null || $held === 'PendingFollowUpRequest' => 'Your next repeat is on hold until your follow-up with your doctor is done.',
            in_array($held, ['Paused', 'ThreeRepeatFailures'], true) => 'Your next repeat is on hold because the order is paused.',
            $held === 'Cancelled' => null,
            default => 'Your next repeat is on hold for now.',
        };
    }

    /** The unfinished payment this order is waiting for, if any: a card page to open or a repeat order to restart. */
    private function resume(Order $order, ?Subscription $sub): ?array
    {
        if (! in_array($order->status, ['PendingPayment', 'PaymentFailed'], true)) {
            return null;
        }

        if ($sub && $sub->status === 'pending') {
            return ['type' => 'subscription'];
        }

        $payment = Payment::where('patient_id', $order->patient_id)->where('status', 'pending')
            ->where(fn ($q) => $q->where('order_id', $order->id)->when($order->source_type === 'appointment', fn ($q) => $q->orWhere('appointment_id', (int) $order->source_ref)))
            ->latest('id')->first();

        return $payment ? ['type' => 'payment', 'url' => route('payment.checkout', $payment->reference)] : null;
    }

    private function resumeDeadline(?Subscription $sub): ?\Illuminate\Support\Carbon
    {
        $event = $sub?->pauseEvents()->whereNull('resumed_at')->latest('paused_at')->first();

        return $event?->paused_at?->copy()->addDays((int) config('subscriptions.pause_resume_window_days', 90));
    }
}
