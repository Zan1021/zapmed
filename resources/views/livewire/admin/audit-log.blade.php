<div>
    <x-slot name="header">Audit Log</x-slot>

    <p class="mb-4 text-sm text-gray-500">A record of what happened on the platform. You can read it here but nobody can change it. Times are South African time.</p>

    <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 text-sm" data-section="filters">
        <label>Show
            <select wire:model.live="source" class="mt-1 block rounded-lg border-gray-300 text-sm">
                <option value="platform">What people changed</option>
                <option value="access">Who opened medical records</option>
            </select>
        </label>
        @if($source === 'platform')
            <label>Area
                <select wire:model.live="domain" class="mt-1 block rounded-lg border-gray-300 text-sm">
                    <option value="">Everything</option>
                    @foreach($domains as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
                </select>
            </label>
        @endif
        <label>From <input type="date" wire:model.live="from" class="mt-1 block rounded-lg border-gray-300 text-sm"></label>
        <label>To <input type="date" wire:model.live="to" class="mt-1 block rounded-lg border-gray-300 text-sm"></label>
        <label class="grow">Search <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ $source === 'platform' ? 'action, who, or reference' : 'person, address or words' }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm"></label>
        @if($source === 'platform')
            <button type="button" wire:click="verifyChain" data-action="verify-chain" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-700">Check nothing was changed</button>
        @endif
    </div>
    @if($chainResult)<p class="mb-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-800" data-chain-result>{{ $chainResult }}</p>@endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        @if($source === 'platform')
            <table class="w-full text-sm" data-list="platform">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3 text-left">When</th><th class="px-4 py-3 text-left">Who</th><th class="px-4 py-3 text-left">Area</th><th class="px-4 py-3 text-left">What</th><th class="px-4 py-3 text-left">About</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($events as $e)
                        <tr data-event="{{ $e->id }}">
                            <td class="px-4 py-2 text-gray-600">{{ $e->occurred_at->setTimezone($tz)->format('j M Y H:i') }}</td>
                            <td class="px-4 py-2 text-gray-800">{{ $e->actor ? trim($e->actor->first_name.' '.$e->actor->last_name) : ($e->actor_label ?: 'The system') }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $e->domain }}</td>
                            <td class="px-4 py-2 text-gray-900">{{ $e->action }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ trim(class_basename((string) $e->subject_type).' '.$e->subject_id) ?: '-' }}</td>
                            <td class="px-4 py-2 text-right">@if($e->before || $e->after)<button type="button" wire:click="toggle({{ $e->id }})" data-action="details" class="text-indigo-600">{{ $open === $e->id ? 'Hide' : 'Details' }}</button>@endif</td>
                        </tr>
                        @if($open === $e->id)
                            <tr data-details="{{ $e->id }}"><td colspan="6" class="bg-gray-50 px-4 py-3">
                                <pre class="whitespace-pre-wrap break-all text-xs text-gray-700">{{ \Illuminate\Support\Str::limit(json_encode(['before' => $e->before, 'after' => $e->after], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1500) }}</pre>
                            </td></tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400" data-empty="audit">Nothing found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $events->links() }}</div>
        @else
            <table class="w-full text-sm" data-list="access">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3 text-left">When</th><th class="px-4 py-3 text-left">Who</th><th class="px-4 py-3 text-left">What</th><th class="px-4 py-3 text-left">Address</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $r)
                        <tr data-access="{{ $r->id }}">
                            <td class="px-4 py-2 text-gray-600">{{ \Illuminate\Support\Carbon::parse($r->created_at)->setTimezone($tz)->format('j M Y H:i') }}</td>
                            <td class="px-4 py-2 text-gray-800">{{ $r->first_name ? trim($r->first_name.' '.$r->last_name) : 'Nobody signed in' }}</td>
                            <td class="px-4 py-2 text-gray-900">{{ $r->description ?: $r->action }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $r->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400" data-empty="audit">Nothing found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $rows->links() }}</div>
        @endif
    </div>
</div>