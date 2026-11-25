<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Coupons</h1>
        <p class="mt-1 text-sm text-gray-500">A patient types the code on the payment page. A coupon takes money off a consultation, never off medication, and never below R5. Leave the email empty to let anyone with the code use it, or give a patient's email to make it theirs alone.</p>
    </div>

    @if($created)
        <p class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-900" data-created>Coupon made: <strong class="font-mono">{{ $created }}</strong></p>
    @endif
    @foreach(['code', 'type', 'amount', 'expires', 'max_uses', 'patient'] as $f) @error($f) <p class="mb-2 text-sm text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4" data-section="new-coupon">
        <h2 class="mb-3 text-sm font-semibold uppercase text-gray-500">Make a coupon</h2>
        <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-6">
            <label>Code (empty = make one)<input type="text" wire:model="code" maxlength="30" class="mt-1 block w-full rounded-lg border-gray-300 text-sm uppercase"></label>
            <label>Takes off
                <select wire:model.live="type" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"><option value="percent">A percentage</option><option value="fixed">An amount (rand)</option></select>
            </label>
            <label>{{ $type === 'percent' ? 'Percent' : 'Rand' }}<input type="text" wire:model="amount" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></label>
            <label>Last day<input type="date" wire:model="expires" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></label>
            <label>Times it can be used<input type="text" wire:model="maxUses" placeholder="no limit" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></label>
            <label>For one patient (email)<input type="email" wire:model="patientEmail" placeholder="anyone with the code" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></label>
        </div>
        <button type="button" wire:click="create" data-action="create-coupon" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">Make coupon</button>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3 text-left">Code</th><th class="px-4 py-3 text-left">Takes off</th><th class="px-4 py-3 text-left">For</th><th class="px-4 py-3 text-left">Last day</th><th class="px-4 py-3 text-right">Used</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($coupons as $c)
                    @php $off = $c->type === 'percent' ? rtrim(rtrim(number_format($c->value / 100, 2, '.', ''), '0'), '.').'%' : 'R'.number_format($c->value / 100, 2); @endphp
                    <tr data-coupon="{{ $c->code }}">
                        <td class="px-4 py-2 font-mono text-gray-900">{{ $c->code }}</td>
                        <td class="px-4 py-2">{{ $off }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ data_get($c->metadata, 'patient_id') ? 'One patient' : 'Anyone with the code' }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $c->expiry_dt ? $c->expiry_dt->setTimezone(config('doctor.time.timezone'))->format('j M Y') : 'No end' }}</td>
                        <td class="px-4 py-2 text-right">{{ $c->number_of_uses }}{{ $c->max_uses !== null ? ' of '.$c->max_uses : '' }}</td>
                        <td class="px-4 py-2" data-status>{{ $c->is_cancelled ? 'Switched off' : (($c->expiry_dt && $c->expiry_dt->isPast()) ? 'Expired' : (($c->max_uses !== null && $c->number_of_uses >= $c->max_uses) ? 'Used up' : 'Working')) }}</td>
                        <td class="px-4 py-2 text-right">@unless($c->is_cancelled)<button type="button" wire:click="cancelCoupon({{ $c->id }})" wire:confirm="Switch this coupon off?" data-action="cancel-coupon" class="text-red-600">Switch off</button>@endunless</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400" data-empty="coupons">No coupons yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>