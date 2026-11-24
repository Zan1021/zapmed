<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\Addon;
use App\Models\Order;
use App\Models\OrderAddon;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Notifications\OutboundMessage;
use App\Services\Patient\PatientMessenger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Add-ons on an order (Craig's guide 6.2).
 *
 *  - Operations keep the add-on list (name, monthly price, the service categories it is offered for) and add, pause or cancel an
 *    add-on on an order. A patient can pause or cancel their own add-on (a reason is needed) but cannot add one.
 *  - An add-on is only offered on an order whose service category matches (a word match, any case). No categories listed = everywhere.
 *  - The price is copied onto the order when added. An order can have each add-on only once while it is active or paused.
 *  - Active add-ons are added to the repeat charge. Paused and cancelled ones are not.
 *  - Messages (OFF until OPS_ADDON_NOTIFY=true) never name the treatment.
 */
class Addons
{
    public function __construct(
        private readonly OrderControls $controls,
        private readonly PatientMessenger $messenger,
        private readonly MessageTemplates $templates,
    ) {
    }

    private function assertOps(User $user): void
    {
        abort_unless($user->role === UserRole::Admin, 403);
    }

    // ---- the list Operations keep --------------------------------------------------------------------------------

    /** @param string[] $categories */
    public function save(User $ops, ?Addon $addon, string $name, int $priceCents, array $categories, bool $active): Addon
    {
        $this->assertOps($ops);

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw ValidationException::withMessages(['name' => 'Give the add-on a name (up to 80 characters).']);
        }
        if ($priceCents < 0 || $priceCents > 1_000_000) {
            throw ValidationException::withMessages(['price' => 'The monthly price must be between R0 and R10 000.']);
        }
        $words = collect($categories)->map(fn ($c) => trim((string) $c))->filter()->unique()->values();
        if ($words->contains(fn ($w) => mb_strlen($w) > 40)) {
            throw ValidationException::withMessages(['categories' => 'Each category word can be up to 40 characters.']);
        }

        $values = ['name' => $name, 'price_cents' => $priceCents, 'categories' => $words->all() ?: null, 'is_active' => $active, 'updated_by' => $ops->id];

        return $addon ? tap($addon)->update($values)->fresh() : Addon::create($values);
    }

    public function isOffered(Addon $addon, Order $order): bool
    {
        if (! $addon->is_active) {
            return false;
        }
        $words = $addon->categories ?? [];

        return $words === [] || collect($words)->contains(fn ($w) => $order->service_category && stripos($order->service_category, $w) !== false);
    }

    /** @return Collection<int,Addon> add-ons that can be put on this order right now */
    public function offeredFor(Order $order): Collection
    {
        $taken = OrderAddon::where('order_id', $order->id)->whereIn('status', ['active', 'paused'])->pluck('addon_id');

        return Addon::where('is_active', true)->orderBy('name')->get()
            ->filter(fn (Addon $a) => $this->isOffered($a, $order) && ! $taken->contains($a->id))->values();
    }

    // ---- on an order ---------------------------------------------------------------------------------------------

    public function add(User $ops, Order $order, Addon $addon): OrderAddon
    {
        $this->assertOps($ops);

        if (! $this->isOffered($addon, $order)) {
            throw ValidationException::withMessages(['addon' => 'That add-on is not offered for this kind of order.']);
        }
        if (in_array($order->status, ['Cancelled', 'RefundComplete'], true)) {
            throw ValidationException::withMessages(['addon' => 'This order is '.strtolower($order->status).'.']);
        }
        if (OrderAddon::where('order_id', $order->id)->where('addon_id', $addon->id)->whereIn('status', ['active', 'paused'])->exists()) {
            throw ValidationException::withMessages(['addon' => 'This order already has that add-on.']);
        }

        $row = OrderAddon::create([
            'order_id' => $order->id, 'addon_id' => $addon->id, 'price_cents' => $addon->price_cents, 'status' => 'active',
            'started_on' => CarbonImmutable::now(config('doctor.time.timezone'))->format('Y-m-d'), 'added_by' => $ops->id,
        ]);

        $this->controls->audit($order, $ops, 'addon_added', sprintf('Add-on added: %s at R%s a month.', $addon->name, number_format($addon->price_cents / 100, 2)), ['order_addon_id' => $row->id]);
        $this->tell($order, 'ops.addon_added', ['addon' => $addon->name]);

        return $row;
    }

    /** @param string $to paused | cancelled | active (continue a paused one) */
    public function change(User $actor, OrderAddon $oa, string $to, ?string $reason = null): OrderAddon
    {
        $isOps = $actor->role === UserRole::Admin;
        $isOwner = $actor->role === UserRole::Patient && $oa->order && $oa->order->patient_id === $actor->id;
        abort_unless($isOps || $isOwner, 403);

        if (! in_array($to, ['paused', 'cancelled', 'active'], true)) {
            throw ValidationException::withMessages(['status' => 'Choose pause, cancel or continue.']);
        }
        if ($to === 'active' && ! $isOps) {
            throw ValidationException::withMessages(['status' => 'Ask us to continue the add-on.']);
        }
        if ($oa->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => 'This add-on is already cancelled.']);
        }
        if ($oa->status === $to) {
            throw ValidationException::withMessages(['status' => 'This add-on is already '.$to.'.']);
        }
        if ($to === 'paused' && $oa->status !== 'active') {
            throw ValidationException::withMessages(['status' => 'Only an active add-on can be paused.']);
        }
        if ($to === 'active' && $oa->status !== 'paused') {
            throw ValidationException::withMessages(['status' => 'Only a paused add-on can be continued.']);
        }

        $reason = trim((string) $reason);
        if ($to !== 'active' && $reason === '') {
            throw ValidationException::withMessages(['reason' => 'Please give a reason.']);
        }

        $oa->update($to === 'active'
            ? ['status' => 'active', 'paused_at' => null, 'reason' => null, 'initiator' => null]
            : ['status' => $to, $to === 'paused' ? 'paused_at' : 'cancelled_at' => now(), 'reason' => mb_substr($reason, 0, 255), 'initiator' => $isOps ? 'operations' : 'patient']);

        $name = $oa->addon->name;
        $this->controls->audit($oa->order, $isOps ? $actor : null, 'addon_'.$to, sprintf('Add-on %s: %s%s.', $to === 'active' ? 'continued' : $to, $name, $to === 'active' ? '' : ' ('.($isOps ? 'by Operations' : 'by the patient').": {$reason})"), ['order_addon_id' => $oa->id, 'initiator' => $isOps ? 'operations' : 'patient']);

        if ($isOps) {
            $this->tell($oa->order, 'ops.addon_'.$to, ['addon' => $name]);
        }

        return $oa->fresh();
    }

    /** What the repeat charge adds for an order's active add-ons, in cents. */
    public function activeCents(Order $order): int
    {
        return (int) OrderAddon::where('order_id', $order->id)->where('status', 'active')->sum('price_cents');
    }

    public function activeCentsForSubscription(Subscription $sub): int
    {
        $order = Order::where('source_type', 'subscription')->where('source_ref', (string) $sub->id)->first();

        return $order ? $this->activeCents($order) : 0;
    }

    private function tell(Order $order, string $key, array $vars): void
    {
        if (! config('operations.addons.notify_enabled') || ! ($patient = $order->patient)) {
            return;
        }

        $t = $this->templates->render($key, $vars);
        if ($t === null) {
            return;   // switched off by Operations
        }

        $this->messenger->send(new OutboundMessage(
            templateKey: $key, category: 'transactional', user: $patient, phone: $patient->phone,
            subject: $t['subject'], body: $t['body'], meta: ['order_id' => $order->id, 'template_version' => $t['version']],
        ));
    }
}
