<div class="space-y-4" data-panel="order-controls">
    @foreach(['order', 'type', 'value', 'months', 'reason', 'transaction', 'amount', 'paid_at', 'result'] as $f)
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

    <div class="rounded-lg border border-gray-200 p-4" data-section="audit">
        <h4 class="mb-2 text-sm font-semibold text-gray-700">What Operations did to this order</h4>
        @forelse($audit as $a)
            <p class="text-xs text-gray-600" data-audit="{{ $a->event }}">{{ $a->created_at?->format('j M Y H:i') }} &middot; {{ $a->user?->name ?? 'System' }}: {{ $a->summary }}</p>
        @empty
            <p class="text-xs text-gray-400" data-empty="audit">Nothing yet.</p>
        @endforelse
    </div>
</div>
