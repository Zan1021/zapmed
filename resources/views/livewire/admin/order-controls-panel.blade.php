<div class="space-y-4" data-panel="order-controls">
    @foreach(['order', 'type', 'value', 'months', 'reason', 'transaction', 'amount', 'paid_at', 'result', 'tracking_number', 'status', 'courier', 'url', 'tracked_at'] as $f)
        @error($f) <p class="text-xs text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror
    @endforeach

    <div class="rounded-lg border border-gray-200 p-4" data-section="coupon">
        <h4 class="mb-2 text-sm font-semibold text-gray-700">Service fee coupon</h4>
        <p class="mb-2 text-xs text-gray-500">Service fee on this order: R{{ number_format($order->service_fee_minor / 100, 2) }}. A coupon never reduces the medication.</p>

        @if($current)
            <p class="mb-2 text-sm text-gray-800" data-active-coupon>
                Active: {{ $current->type === 'percent' ? ($current->value / 100).'%' : 'R'.number_format($current->value / 100, 2) }} off
                (R{{ number_format($off / 100, 2) }} a month) {{ $current->ends_on ? 'until '.$current->ends_on->format('j M Y') : 'until switched off' }}.
            </p>
            <input type="text" wire:model="disableReason" placeholder="Why switch it off?" class="mr-2 w-56 rounded-lg border-gray-300 text-sm">
            <button type="button" wire:click="disableDiscount({{ $current->id }})" data-action="disable-coupon" class="text-sm text-red-600">Switch off</button>
        @else
            <div class="flex flex-wrap items-end gap-2 text-sm">
                <select wire:model="type" class="rounded-lg border-gray-300 text-sm"><option value="percent">Percent off</option><option value="fixed">Rand off</option></select>
                <input type="text" wire:model="value" placeholder="{{ $type === 'percent' ? '20' : '50.00' }}" class="w-24 rounded-lg border-gray-300 text-sm">
                <select wire:model="months" class="rounded-lg border-gray-300 text-sm">
                    <option value="">Until switched off</option>
                    @for($m = 1; $m <= 6; $m++)<option value="{{ $m }}">{{ $m }} {{ $m === 1 ? 'month' : 'months' }}</option>@endfor
                </select>
                <input type="text" wire:model="reason" placeholder="Reason" class="w-48 rounded-lg border-gray-300 text-sm">
                <button type="button" wire:click="addDiscount" data-action="add-coupon" class="rounded-lg bg-indigo-600 px-3 py-2 text-white">Add coupon</button>
            </div>
        @endif

        @if($discounts->isNotEmpty())
            <ul class="mt-3 space-y-1 text-xs text-gray-500">
                @foreach($discounts as $d)
                    <li>{{ $d->created_at->format('j M Y') }}: {{ $d->type === 'percent' ? ($d->value / 100).'%' : 'R'.number_format($d->value / 100, 2) }} off,
                        {{ $d->months ? $d->months.' month(s)' : 'open ended' }} - {{ $d->is_active ? 'active' : 'off' }} ({{ $d->reason }})</li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="rounded-lg border border-gray-200 p-4" data-section="manual-payment">
        <h4 class="mb-2 text-sm font-semibold text-gray-700">Record a payment made outside the site</h4>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <input type="text" wire:model="transactionId" placeholder="Transaction ID" class="rounded-lg border-gray-300 text-sm">
            <input type="text" wire:model="amount" placeholder="Amount (R)" class="rounded-lg border-gray-300 text-sm">
            <input type="datetime-local" wire:model="paidAt" class="rounded-lg border-gray-300 text-sm">
            <select wire:model="result" class="rounded-lg border-gray-300 text-sm"><option value="completed">Completed</option><option value="failed">Failed</option></select>
            <input type="text" wire:model="paymentReference" placeholder="Reference" class="col-span-2 rounded-lg border-gray-300 text-sm">
        </div>
        <button type="button" wire:click="recordPayment" data-action="record-payment" class="mt-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white">Record payment</button>
    </div>

    @if($isRepeat)
    <div class="rounded-lg border border-gray-200 p-4" data-section="repeat-reset">
        <h4 class="mb-2 text-sm font-semibold text-gray-700">Repeat cycle</h4>
        <p class="mb-2 text-xs text-gray-500">Next repeat: {{ $nextRepeat ?? 'none scheduled' }}. Resetting cancels the waiting repeats (kept in history), clears failed-payment strikes and works out the first repeat again from today.</p>
        <input type="text" wire:model="resetReason" placeholder="Why reset?" class="mr-2 w-56 rounded-lg border-gray-300 text-sm">
        <button type="button" wire:click="resetRepeat" data-action="reset-repeat" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">Reset repeat</button>
    </div>
    @endif

    <div class="rounded-lg border border-gray-200 p-4" data-section="booking-links">
        <h4 class="mb-2 text-sm font-semibold text-gray-700">Booking link</h4>
        @foreach(['uses', 'days', 'link', 'patient', 'follow_up'] as $f) @error($f) <p class="mb-1 text-xs text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach
        @if($newLinkUrl)
            <p class="mb-2 break-all rounded bg-green-50 p-2 text-xs text-green-900" data-new-link>Copy this now, it is shown only once: <strong>{{ $newLinkUrl }}</strong></p>
            <div class="mb-2 flex items-center gap-3 text-xs">
                <button type="button" wire:click="sendLink" data-action="send-link" class="rounded-lg border border-indigo-600 px-3 py-1.5 text-indigo-700">Send it to the patient</button>
            </div>
        @endif
        @if($linkSendNote)<p class="mb-2 text-xs text-gray-600" data-send-note>{{ $linkSendNote }}</p>@endif
        @foreach($bookingLinks as $bl)
            <div class="mb-1 flex items-center justify-between text-xs text-gray-700" data-link="{{ $bl->id }}">
                <span>Made {{ $bl->created_at->format('j M') }}: used {{ $bl->uses }} of {{ $bl->max_uses }}, {{ $bl->revoked_at ? 'switched off' : ($bl->expires_at->isPast() ? 'expired' : 'until '.$bl->expires_at->format('j M')) }}</span>
                @if($bl->isUsable())
                    <span><button type="button" wire:click="resendLink({{ $bl->id }})" data-action="resend-link" class="mr-2 text-indigo-600">New link</button><button type="button" wire:click="revokeLink({{ $bl->id }})" data-action="revoke-link" class="text-red-600">Switch off</button></span>
                @endif
            </div>
        @endforeach
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
            <input type="number" wire:model="linkUses" min="1" max="{{ $linkDefaults['max_uses'] }}" placeholder="Uses ({{ $linkDefaults['default_uses'] }})" class="w-28 rounded-lg border-gray-300 text-sm">
            <input type="number" wire:model="linkDays" min="1" max="{{ $linkDefaults['max_days'] }}" placeholder="Days ({{ $linkDefaults['default_days'] }})" class="w-28 rounded-lg border-gray-300 text-sm">
            <button type="button" wire:click="makeLink" data-action="make-link" class="rounded-lg bg-indigo-600 px-3 py-2 text-white">Make a link</button>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 p-4" data-section="care-team">
        <h4 class="mb-2 text-sm font-semibold text-gray-700">Care team (doctors and coaches for this patient)</h4>
        @error('staff') <p class="mb-1 text-xs text-red-600" data-error="staff">{{ $message }}</p> @enderror
        @error('assignment') <p class="mb-1 text-xs text-red-600" data-error="assignment">{{ $message }}</p> @enderror
        @forelse($careTeam as $a)
            <div class="mb-1 flex items-center justify-between text-sm" data-assignment="{{ $a->id }}">
                <span>{{ $a->staff?->name }} ({{ $a->role === 'doctor' ? 'doctor' : 'health coach' }}) since {{ $a->started_at->setTimezone(config('doctor.time.timezone'))->format('j M Y') }}@if($a->ended_at), ended {{ $a->ended_at->setTimezone(config('doctor.time.timezone'))->format('j M Y') }}@endif</span>
                @unless($a->ended_at)<button type="button" wire:click="endAssignment({{ $a->id }})" data-action="end-assignment" class="text-xs text-red-600">End</button>@endunless
            </div>
        @empty
            <p class="text-xs text-gray-400" data-empty="care-team">Nobody is assigned yet.</p>
        @endforelse
        <div class="mt-2 flex gap-2 text-sm">
            <select wire:model="staffId" class="rounded-lg border-gray-300 text-sm"><option value="">Assign someone...</option>@foreach($assignableStaff as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->role === \App\Enums\UserRole::Doctor ? 'doctor' : 'coach' }})</option>@endforeach</select>
            <button type="button" wire:click="assignStaff" data-action="assign-staff" class="rounded-lg bg-indigo-600 px-3 py-2 text-white">Assign</button>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 p-4" data-section="addons">
        <h4 class="mb-2 text-sm font-semibold text-gray-700">Add-ons</h4>
        @error('addon') <p class="mb-1 text-xs text-red-600" data-error="addon">{{ $message }}</p> @enderror
        @error('status') <p class="mb-1 text-xs text-red-600" data-error="status">{{ $message }}</p> @enderror
        @foreach($orderAddons as $oa)
            <div class="mb-1 flex items-center justify-between text-sm" data-order-addon="{{ $oa->id }}">
                <span>{{ $oa->addon->name }}, R{{ number_format($oa->price_cents / 100, 2) }} a month: <strong>{{ $oa->status }}</strong>@if($oa->reason) <span class="text-xs text-gray-500">({{ $oa->initiator }}: {{ $oa->reason }})</span>@endif</span>
                <span class="text-xs">
                    @if($oa->status === 'active')<button type="button" wire:click="changeAddon({{ $oa->id }}, 'paused')" data-action="pause-addon" class="mr-2 text-gray-600">Pause</button>@endif
                    @if($oa->status === 'paused')<button type="button" wire:click="changeAddon({{ $oa->id }}, 'active')" data-action="continue-addon" class="mr-2 text-gray-600">Continue</button>@endif
                    @if($oa->status !== 'cancelled')<button type="button" wire:click="changeAddon({{ $oa->id }}, 'cancelled')" data-action="cancel-addon" class="text-red-600">Cancel</button>@endif
                </span>
            </div>
        @endforeach
        <input type="text" wire:model="addonReason" placeholder="Reason (for pause or cancel)" class="mt-2 w-full rounded-lg border-gray-300 text-sm">
        @if($offeredAddons->isNotEmpty())
            <div class="mt-2 flex gap-2 text-sm">
                <select wire:model="addonId" class="rounded-lg border-gray-300 text-sm"><option value="">Add an add-on...</option>@foreach($offeredAddons as $a)<option value="{{ $a->id }}">{{ $a->name }} (R{{ number_format($a->price_cents / 100, 2) }})</option>@endforeach</select>
                <button type="button" wire:click="addAddon" data-action="add-addon" class="rounded-lg bg-indigo-600 px-3 py-2 text-white">Add</button>
            </div>
        @endif
    </div>

    <div class="rounded-lg border border-gray-200 p-4" data-section="tracking">
        <h4 class="mb-2 text-sm font-semibold text-gray-700">Courier tracking</h4>
        @if($tracking)
            <p class="mb-2 text-sm text-gray-800" data-current-tracking>{{ $tracking->courier }} {{ $tracking->tracking_number }}: {{ $trackingStatuses[$tracking->status] ?? $tracking->status }} ({{ $tracking->tracked_at->setTimezone(config('doctor.time.timezone'))->format('j M H:i') }})
                @if($tracking->url)<a href="{{ $tracking->url }}" target="_blank" rel="noopener" class="text-indigo-600">link</a>@endif</p>
        @endif
        <div class="grid grid-cols-2 gap-2 text-sm">
            <input type="text" wire:model="trackNumber" placeholder="Tracking number" class="rounded-lg border-gray-300 text-sm">
            <select wire:model="trackStatus" class="rounded-lg border-gray-300 text-sm">@foreach($trackingStatuses as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
            <input type="text" wire:model="trackCourier" placeholder="Courier (default {{ config('operations.tracking.default_courier') }})" class="rounded-lg border-gray-300 text-sm">
            <input type="text" wire:model="trackPharmacyRef" placeholder="Pharmacy reference" class="rounded-lg border-gray-300 text-sm">
            <input type="url" wire:model="trackUrl" placeholder="Link (optional)" class="col-span-2 rounded-lg border-gray-300 text-sm">
        </div>
        <button type="button" wire:click="recordTracking" data-action="record-tracking" class="mt-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white">Save tracking</button>
    </div>

    <div class="rounded-lg border border-gray-200 p-4" data-section="audit">
        <h4 class="mb-2 text-sm font-semibold text-gray-700">What Operations did to this order</h4>
        @forelse($audit as $a)
            <p class="text-xs text-gray-600" data-audit="{{ $a->event }}">{{ $a->created_at?->format('j M Y H:i') }} &middot; {{ $a->user?->name ?? 'System' }}: {{ $a->summary }}</p>
        @empty
            <p class="text-xs text-gray-400" data-empty="audit">Nothing yet.</p>
        @endforelse
    </div>
</div>
