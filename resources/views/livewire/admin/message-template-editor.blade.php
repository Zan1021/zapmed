<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Patient messages</h1>
        <p class="mt-1 text-sm text-gray-500">Reword the messages patients get, or switch one off. No health detail and no medicine names are allowed. Every change is kept, and the version number is written into the message log. Messages only go out when their sending switch is on.</p>
    </div>

    <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3 text-left">Message</th><th class="px-4 py-3 text-left">Wording</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($registry as $key => $d)
                    <tr data-template="{{ $key }}">
                        <td class="px-4 py-2 text-gray-900">{{ $d['name'] }}<p class="text-xs text-gray-400">{{ $key }}</p></td>
                        <td class="px-4 py-2 text-gray-600">
                            @if(! $custom->has($key)) Built-in
                            @elseif($custom[$key]) Changed by Operations
                            @else <span class="text-red-600">Switched off</span> @endif
                        </td>
                        <td class="px-4 py-2 text-right"><button type="button" wire:click="edit('{{ $key }}')" data-action="edit" class="text-indigo-600">Edit</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($editing && $def)
        <div class="rounded-xl border border-gray-200 bg-white p-5" data-form="template" data-editing="{{ $editing }}">
            <h2 class="mb-1 text-lg font-semibold text-gray-900">{{ $def['name'] }}</h2>
            <p class="mb-3 text-xs text-gray-500">
                You can use: @forelse($def['placeholders'] as $p)<code>{{ '{'.$p.'}' }}</code> @empty nothing extra @endforelse
                @if($def['required']) &middot; must keep: @foreach($def['required'] as $p)<code>{{ '{'.$p.'}' }}</code> @endforeach @endif
            </p>
            @foreach(['key', 'subject', 'body'] as $f) @error($f) <p class="mb-2 text-sm text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach

            <label class="mb-1 block text-sm text-gray-700">Subject</label>
            <input type="text" wire:model="subject" maxlength="120" class="mb-3 w-full rounded-lg border-gray-300 text-sm">
            <label class="mb-1 block text-sm text-gray-700">Message</label>
            <textarea wire:model="body" rows="5" maxlength="1000" class="mb-3 w-full rounded-lg border-gray-300 text-sm"></textarea>
            <label class="mb-3 flex items-center text-sm text-gray-700"><input type="checkbox" wire:model="enabled" class="mr-2 rounded border-gray-300"> Send this message</label>

            <button type="button" wire:click="save" data-action="save" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">Save</button>
            @if($custom->has($editing))<button type="button" wire:click="reset_" data-action="reset" class="ml-2 rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">Go back to the built-in wording</button>@endif
            <button type="button" wire:click="close" class="ml-2 text-sm text-gray-500">Close</button>
            @if($saved)<span class="ml-3 text-sm text-green-700" data-saved>Saved.</span>@endif

            @if($history->isNotEmpty())
                <h3 class="mt-5 mb-1 text-xs font-semibold uppercase text-gray-500">Earlier versions</h3>
                <ul class="text-xs text-gray-600" data-history>
                    @foreach($history as $h)<li>Version {{ $h->version }}, {{ \Illuminate\Support\Carbon::parse($h->created_at)->format('j M Y H:i') }}: {{ $h->change === 'reset' ? 'went back to the built-in wording' : ($h->is_enabled ? 'saved' : 'saved and switched off') }}</li>@endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
