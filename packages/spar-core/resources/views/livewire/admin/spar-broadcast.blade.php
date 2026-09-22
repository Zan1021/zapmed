<div>
    <x-slot name="header">SPAR Broadcast</x-slot>

    <x-spar::page-header eyebrow="SPAR Group" title="Broadcast"
        subtitle="Send a special or a notice to your consented patients." />

    @if($result)
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
             role="status" wire:key="broadcast-result">
            {{ $result }}
        </div>
    @endif

    <div class="max-w-2xl">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                This will reach <span class="font-bold">{{ $this->audienceCount }}</span>
                consented patient{{ $this->audienceCount === 1 ? '' : 's' }} in your scope. Patients who have not
                consented are never messaged.
            </div>

            <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
            <input type="text" wire:model="subject" maxlength="120"
                   class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm mb-1"
                   placeholder="e.g. This week's wellness special" />
            @error('subject')<p class="text-sm text-red-600 mb-2">{{ $message }}</p>@enderror

            <label class="mt-4 block text-sm font-medium text-gray-700 mb-1">Message</label>
            <textarea wire:model="body" rows="5" maxlength="1000"
                      class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm"
                      placeholder="Keep it short and useful — a special, a reminder, or a change to store hours."></textarea>
            @error('body')<p class="text-sm text-red-600 mb-2">{{ $message }}</p>@enderror

            <div class="mt-5 flex items-center justify-end gap-3">
                <button type="button" wire:click="send"
                        wire:confirm="Send this broadcast to your {{ $this->audienceCount }} consented patients?"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-600 hover:bg-green-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                        @disabled($this->audienceCount === 0)>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Send broadcast
                </button>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-400">
            Broadcasts are consent-gated and scoped to your pharmacy. Delivery uses each patient's
            preferred channel (in-app, email, SMS). No patient health information is included.
        </p>
    </div>
</div>
