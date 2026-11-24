<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Pharmacy messages</h1>
        <p class="mt-1 text-sm text-gray-500">Every prescription sent to the pharmacy. A prescription the pharmacy refused is sent again by itself (up to {{ $max }} times). If we got no answer at all, it is NOT sent again by itself: check with the pharmacy first. A prescription the pharmacy already has is never sent twice.</p>
    </div>

    <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 text-sm">
        <label>Status
            <select wire:model.live="status" class="mt-1 block rounded-lg border-gray-300 text-sm">
                <option value="">All</option><option value="success">Accepted</option><option value="failed">Refused or not reached</option><option value="unknown">No answer</option>
            </select>
        </label>
        <label>Search <input type="text" wire:model.live.debounce.400ms="search" placeholder="Prescription, order or patient email" class="mt-1 block w-72 rounded-lg border-gray-300 text-sm"></label>
    </div>

    @error('resend') <p class="mb-3 text-sm text-red-600" data-error="resend">{{ $message }}</p> @enderror
    @if($message)<p class="mb-3 rounded-lg bg-gray-50 p-3 text-sm text-gray-800" data-result>{{ $message }}</p>@endif

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-4 py-3 text-left">When</th><th class="px-4 py-3 text-left">What</th><th class="px-4 py-3 text-left">Order</th>
                <th class="px-4 py-3 text-left">Result</th><th class="px-4 py-3 text-left">Next try</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $m)
                    <tr data-message="{{ $m->id }}" data-status="{{ $m->status }}">
                        <td class="px-4 py-2 text-gray-700">{{ $m->created_at->setTimezone(config('doctor.time.timezone'))->format('j M H:i') }}</td>
                        <td class="px-4 py-2 text-gray-900">{{ $m->request_summary }}<p class="text-xs text-gray-400">{{ $m->patient?->email }}@if($m->resent_by) &middot; sent again by a person @endif</p></td>
                        <td class="px-4 py-2 text-gray-700">{{ $m->order?->reference ?? '-' }}</td>
                        <td class="px-4 py-2">
                            @if($m->status === 'success')<span class="text-green-700">Accepted</span>
                            @elseif($m->status === 'failed')<span class="text-red-700">Refused or not reached</span>
                            @else<span class="text-amber-700">No answer</span>@endif
                            @if($m->response_code) <span class="text-xs text-gray-400">({{ $m->response_code }})</span>@endif
                            @if($m->error)<p class="text-xs text-gray-500">{{ $m->error }}</p>@endif
                        </td>
                        <td class="px-4 py-2 text-gray-600">{{ $m->next_retry_at ? $m->next_retry_at->setTimezone(config('doctor.time.timezone'))->format('j M H:i') : '-' }}</td>
                        <td class="px-4 py-2 text-right">
                            @if($m->status !== 'success' && ($canResend[$m->id] ?? false))
                                @if($confirming === $m->id)
                                    @if($m->status === 'unknown')
                                        <label class="mb-1 block text-xs text-gray-700"><input type="checkbox" wire:model="confirmed" class="mr-1 rounded border-gray-300" data-confirm> I checked with the pharmacy and they do NOT have it</label>
                                    @endif
                                    <button type="button" wire:click="resend({{ $m->id }})" data-action="confirm-resend" class="rounded-lg bg-indigo-600 px-3 py-1 text-xs text-white">Send it now</button>
                                    <button type="button" wire:click="cancel" class="ml-1 text-xs text-gray-500">Not now</button>
                                @else
                                    <button type="button" wire:click="askResend({{ $m->id }})" data-action="resend" class="text-xs text-indigo-600">Send again</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400" data-empty="messages">Nothing has been sent to the pharmacy yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
