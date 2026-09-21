<div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-4">
    <div class="flex items-center gap-2 mb-3">
        <div class="w-9 h-9 rounded-full bg-green-100 flex items-center justify-center">
            <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.9 9.9 0 01-4-.83L3 20l1.3-3.2A7.6 7.6 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
        </div>
        <div>
            <h3 class="font-semibold text-gray-900 leading-tight">Your Health Coach</h3>
            <p class="text-xs text-gray-500">Chat with your SPAR pharmacy — ask a question or get product tips.</p>
        </div>
    </div>

    @if($error)
        <p class="text-xs text-red-600 mb-2">{{ $error }}</p>
    @endif

    {{-- Thread --}}
    <div class="space-y-3 max-h-80 overflow-y-auto pr-1 mb-3">
        @forelse($this->messages as $message)
            @if($message->isSystem())
                <div class="text-center">
                    <span class="inline-block text-xs text-gray-500 bg-gray-100 rounded-full px-3 py-1">{{ $message->body }}</span>
                </div>
            @elseif($message->isProductSuggestion())
                @php $s = $message->productSuggestion; @endphp
                <div class="flex justify-start">
                    <div class="max-w-[85%] rounded-2xl border border-green-200 bg-green-50 p-3">
                        <p class="text-[11px] uppercase tracking-wide text-green-700 font-semibold mb-1">Recommended for you</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $s?->product_name }}</p>
                        @if($s?->price_cents !== null)
                            <p class="text-sm text-gray-700">R{{ number_format($s->price_cents / 100, 2) }}</p>
                        @endif
                        @if($s?->note)<p class="text-xs text-gray-600 mt-1">{{ $s->note }}</p>@endif

                        @if($s?->isOffered())
                            <div class="flex gap-2 mt-3">
                                <button type="button" wire:click="accept({{ $s->id }})"
                                        class="flex-1 text-xs font-semibold px-3 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">
                                    Add to my order
                                </button>
                                <button type="button" wire:click="decline({{ $s->id }})"
                                        class="text-xs font-medium px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">
                                    No thanks
                                </button>
                            </div>
                        @elseif($s?->isAccepted())
                            <p class="text-xs text-green-700 font-medium mt-2">✓ Added to your order</p>
                        @else
                            <p class="text-xs text-gray-400 mt-2">You declined this suggestion</p>
                        @endif
                    </div>
                </div>
            @else
                <div class="flex {{ $message->isFromPatient() ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] rounded-2xl p-3 {{ $message->isFromPatient() ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-900' }}">
                        <p class="text-sm whitespace-pre-line">{{ $message->body }}</p>
                        <p class="text-[11px] mt-1 {{ $message->isFromPatient() ? 'text-green-100' : 'text-gray-400' }} text-right">
                            {{ $message->isFromPatient() ? 'You' : 'Pharmacy' }} · {{ $message->created_at->format('d M H:i') }}
                        </p>
                    </div>
                </div>
            @endif
        @empty
            <p class="text-sm text-gray-400 text-center py-6">No messages yet. Send your pharmacy a message below.</p>
        @endforelse
    </div>

    {{-- Composer --}}
    <form wire:submit.prevent="send" class="flex items-end gap-2">
        <textarea wire:model="body" rows="2" placeholder="Message your pharmacy…"
                  class="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2 resize-none"></textarea>
        <button type="submit"
                class="text-sm font-semibold px-4 py-2 rounded-xl bg-green-600 text-white hover:bg-green-700">
            Send
        </button>
    </form>
    @error('body') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
</div>
