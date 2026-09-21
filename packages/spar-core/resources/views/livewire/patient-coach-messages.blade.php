<div @class([
        'max-w-md mx-auto' => true,
        'h-full flex flex-col' => $page,
        'py-6' => ! $page,
    ])>
    @if($page)
        <a href="{{ route('my-meds.track') }}"
           class="inline-flex items-center gap-1 text-sm text-green-700 mb-3 shrink-0 pt-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to my medication
        </a>
    @endif

    <div @class([
        'rounded-2xl bg-white shadow-sm border border-gray-100 p-4' => true,
        'flex-1 flex flex-col min-h-0 mb-2' => $page,
    ])>
    <div class="flex items-center gap-2 mb-3 shrink-0">
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

    {{-- Filter chips — views over the one thread (scoped by kind, not by the
         encrypted body). Counts span the whole thread, not just the window. --}}
    @php $counts = $this->counts; @endphp
    <div class="flex flex-wrap gap-2 mb-3 shrink-0">
        @foreach([
            'all' => 'All',
            'recommendations' => 'Recommendations',
            'orders' => 'My Orders',
        ] as $key => $label)
            <button type="button" wire:click="setFilter('{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-full border transition-colors',
                        'bg-green-600 text-white border-green-600' => $filter === $key,
                        'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' => $filter !== $key,
                    ])>
                {{ $label }}
                <span @class([
                    'inline-flex items-center justify-center min-w-[1.1rem] h-4 px-1 rounded-full text-[10px] font-semibold',
                    'bg-white/25 text-white' => $filter === $key,
                    'bg-gray-100 text-gray-500' => $filter !== $key,
                ])>{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>

    @if($error)
        <p class="text-xs text-red-600 mb-2">{{ $error }}</p>
    @endif

    {{-- Thread (newest at the bottom; auto-scrolled to latest on load/update).
         Page mode: fills the space between chips and composer and scrolls
         internally (flex-1). Embedded mode: a bounded scroll box. --}}
    <div @class([
             'space-y-3 overflow-y-auto pr-1 mb-3' => true,
             'flex-1 min-h-0' => $page,
             'max-h-80' => ! $page,
         ])
         x-data
         x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
         x-on:coach-scroll-bottom.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">

        @if($this->hasMore)
            <div class="text-center">
                <button type="button" wire:click="loadEarlier"
                        class="text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 rounded-full px-4 py-1.5">
                    Load earlier messages
                </button>
            </div>
        @endif

        @php $lastDay = null; @endphp
        @forelse($this->messages as $message)
            @php
                $day = $message->created_at->startOfDay();
                $showDate = $lastDay === null || ! $day->equalTo($lastDay);
                $lastDay = $day;
            @endphp

            @if($showDate)
                <div class="text-center my-2">
                    <span class="inline-block text-[11px] font-medium text-gray-400 bg-gray-50 rounded-full px-3 py-0.5">
                        @if($message->created_at->isToday()) Today
                        @elseif($message->created_at->isYesterday()) Yesterday
                        @elseif($message->created_at->isCurrentYear()) {{ $message->created_at->format('D, d M') }}
                        @else {{ $message->created_at->format('d M Y') }}
                        @endif
                    </span>
                </div>
            @endif

            @if($message->isProductSuggestion())
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
            @elseif($message->isSystem())
                <div class="text-center">
                    <span class="inline-block text-xs {{ $message->isOrderEvent() ? 'text-green-700 bg-green-50' : 'text-gray-500 bg-gray-100' }} rounded-full px-3 py-1">
                        {{ $message->body }}
                    </span>
                </div>
            @else
                <div class="flex {{ $message->isFromPatient() ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] rounded-2xl p-3 {{ $message->isFromPatient() ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-900' }}">
                        <p class="text-sm whitespace-pre-line">{{ $message->body }}</p>
                        <p class="text-[11px] mt-1 {{ $message->isFromPatient() ? 'text-green-100' : 'text-gray-400' }} text-right">
                            {{ $message->isFromPatient() ? 'You' : 'Pharmacy' }} · {{ $message->created_at->format('H:i') }}
                        </p>
                    </div>
                </div>
            @endif
        @empty
            <p class="text-sm text-gray-400 text-center py-6">
                @if($filter === 'recommendations') No product recommendations yet.
                @elseif($filter === 'orders') Nothing added to an order yet.
                @else No messages yet. Send your pharmacy a message below.
                @endif
            </p>
        @endforelse
    </div>

    {{-- Composer — bottom row of the full-height flex card (page mode), so it
         stays visible while the thread scrolls above it. No fixed positioning;
         the safe-area inset clears the mobile home-bar. --}}
    <div @class([
        'shrink-0' => true,
        'pb-[max(0.25rem,env(safe-area-inset-bottom))]' => $page,
    ])>
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
    </div>
</div>
