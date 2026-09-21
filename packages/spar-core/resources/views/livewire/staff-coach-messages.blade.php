<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
    <div class="flex items-center justify-between mb-3">
        <div>
            <h3 class="font-semibold text-gray-900">Health Coach — messages</h3>
            <p class="text-xs text-gray-500">Chat with this patient and suggest products. Coach = you (pharmacy staff).</p>
        </div>
        <button type="button" wire:click="toggleSuggest"
                class="text-xs font-medium px-3 py-1.5 rounded-lg bg-green-600 text-white hover:bg-green-700">
            {{ $showSuggest ? 'Cancel' : '＋ Suggest a product' }}
        </button>
    </div>

    @if($showSuggest)
        <div class="rounded-xl border border-green-100 bg-green-50 p-4 mb-4">
            <h4 class="text-sm font-semibold text-gray-900 mb-2">Suggest a product</h4>
            <div class="grid gap-2 sm:grid-cols-3">
                <input type="text" wire:model="productName" placeholder="Product name (e.g. Magnesium)"
                       class="sm:col-span-2 text-sm border border-gray-200 rounded-lg px-3 py-2" />
                <input type="number" step="0.01" min="0" wire:model="productPrice" placeholder="Price (R)"
                       class="text-sm border border-gray-200 rounded-lg px-3 py-2" />
            </div>
            @error('productName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            <input type="text" wire:model="productNote" placeholder="Optional note (why you recommend it)"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 mt-2" />
            <div class="flex justify-end mt-2">
                <button type="button" wire:click="suggest"
                        class="text-xs font-medium px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">
                    Send suggestion
                </button>
            </div>
        </div>
    @endif

    {{-- Thread --}}
    <div class="space-y-3 max-h-96 overflow-y-auto pr-1 mb-4">
        @forelse($this->messages as $message)
            @if($message->isSystem())
                <div class="text-center">
                    <span class="inline-block text-xs text-gray-500 bg-gray-100 rounded-full px-3 py-1">{{ $message->body }}</span>
                </div>
            @elseif($message->isProductSuggestion())
                @php $s = $message->productSuggestion; @endphp
                <div class="flex justify-end">
                    <div class="max-w-xs rounded-xl border border-green-200 bg-green-50 p-3">
                        <p class="text-[11px] uppercase tracking-wide text-green-700 font-semibold mb-1">Product suggestion</p>
                        <p class="text-sm font-medium text-gray-900">{{ $s?->product_name }}</p>
                        @if($s?->price_cents !== null)
                            <p class="text-sm text-gray-700">R{{ number_format($s->price_cents / 100, 2) }}</p>
                        @endif
                        @if($s?->note)<p class="text-xs text-gray-500 mt-1">{{ $s->note }}</p>@endif
                        <p class="text-[11px] mt-2
                            {{ $s?->isAccepted() ? 'text-green-700' : ($s?->isDeclined() ? 'text-red-600' : 'text-amber-600') }}">
                            {{ $s?->isAccepted() ? '✓ Added to order' : ($s?->isDeclined() ? '✗ Declined by patient' : '• Awaiting patient') }}
                        </p>
                        <p class="text-[11px] text-gray-400 mt-1 text-right">{{ $message->author_name }} · {{ $message->created_at->format('d M H:i') }}</p>
                    </div>
                </div>
            @else
                <div class="flex {{ $message->isFromStaff() ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-xs rounded-xl p-3 {{ $message->isFromStaff() ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-900' }}">
                        <p class="text-sm whitespace-pre-line">{{ $message->body }}</p>
                        <p class="text-[11px] mt-1 {{ $message->isFromStaff() ? 'text-green-100' : 'text-gray-400' }} text-right">
                            {{ $message->isFromStaff() ? ($message->author_name ?: 'Staff') : 'Patient' }} · {{ $message->created_at->format('d M H:i') }}
                        </p>
                    </div>
                </div>
            @endif
        @empty
            <p class="text-sm text-gray-400 text-center py-6">No messages yet. Say hello or suggest a product.</p>
        @endforelse
    </div>

    {{-- Composer --}}
    <form wire:submit.prevent="send" class="flex items-end gap-2">
        <textarea wire:model="body" rows="2" placeholder="Type a message to the patient…"
                  class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 resize-none"></textarea>
        <button type="submit"
                class="text-sm font-medium px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">
            Send
        </button>
    </form>
    @error('body') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
</div>
