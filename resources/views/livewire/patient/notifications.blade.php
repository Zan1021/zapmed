<div>
    <x-patient-tabs active="notifications" />

    <h1 class="mb-4 text-2xl font-bold text-gray-900">Notifications</h1>

    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5" data-section="from-care-team">
        <h2 class="mb-3 text-sm font-semibold uppercase text-gray-500">From your care team</h2>
        @forelse($items as $i)
            <p class="mb-2 text-sm text-gray-800" data-item="{{ $i['key'] }}">
                {{ $i['text'] }}
                <a href="{{ route($i['route']) }}" class="ml-1 text-zapmed-700 hover:underline">Open</a>
                <span class="text-xs text-gray-400">{{ $i['at']->format('j M') }}</span>
            </p>
        @empty
            <p class="text-sm text-gray-500" data-empty="items">Nothing needs your attention right now.</p>
        @endforelse
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5" data-section="channel">
        <h2 class="mb-1 text-sm font-semibold uppercase text-gray-500">How should we contact you?</h2>
        <p class="mb-3 text-xs text-gray-500">We use this for service messages like appointments, orders and follow-ups. We never put your health details in a message. If one way does not work, we try the other.</p>

        <label class="mb-2 flex items-center text-sm text-gray-800"><input type="radio" wire:model.live="preferred" value="whatsapp" class="mr-2"> WhatsApp</label>
        <label class="mb-3 flex items-center text-sm text-gray-800"><input type="radio" wire:model.live="preferred" value="email" class="mr-2"> Email</label>

        <label class="mb-1 flex items-start text-sm text-gray-700">
            <input type="checkbox" wire:model="consent" class="mr-2 mt-1 rounded border-gray-300" data-consent>
            <span>{{ $wording }}</span>
        </label>
        @unless($hasPhone)<p class="mt-1 text-xs text-amber-700" data-no-phone>We do not have a cell phone number for you yet. Add one in your profile to use WhatsApp.</p>@endunless

        @foreach(['preferred', 'consent'] as $f) @error($f) <p class="mt-1 text-xs text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach

        <button type="button" wire:click="save" data-action="save-channel" class="mt-4 rounded-lg bg-zapmed-600 px-5 py-2 text-sm text-white">Save</button>
        @if($saved)<span class="ml-3 text-sm text-green-700" data-saved>Saved.</span>@endif

        @if($preferred === 'whatsapp' && ! $whatsappReady)
            <p class="mt-3 text-xs text-gray-500" data-whatsapp-pending>WhatsApp messages are not switched on yet, so for now we will use email.</p>
        @endif
    </div>
</div>
