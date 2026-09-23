<div class="max-w-6xl mx-auto px-4 py-6" wire:poll.30s>
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
            Health Coach
            @if($this->unreadTotal > 0)
                <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full
                             bg-red-600 text-white text-xs font-bold">{{ $this->unreadTotal }}</span>
            @endif
        </h1>
        <p class="text-sm text-gray-500">Patient messages for your pharmacy. Select a conversation to read and reply.</p>
    </div>

    <div class="grid gap-4 md:grid-cols-[320px_1fr]">
        {{-- ===================== CONVERSATION LIST ===================== --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Conversations</h2>
            </div>
            <div class="divide-y divide-gray-50 max-h-[70vh] overflow-y-auto">
                @forelse($this->conversations as $conversation)
                    @php
                        $patient = $conversation->patient;
                        $name = $patient ? trim($patient->first_name . ' ' . $patient->last_name) : 'Unknown patient';
                        $unread = (int) $conversation->staff_unread_count;
                        $isActive = $this->conversationId === $conversation->id;
                    @endphp
                    <button type="button" wire:click="select({{ $conversation->id }})"
                            class="w-full text-left px-4 py-3 flex items-start gap-3 hover:bg-gray-50 transition-colors
                                   {{ $isActive ? 'bg-green-50' : '' }}">
                        <span class="mt-1 shrink-0 w-2 h-2 rounded-full {{ $unread > 0 ? 'bg-red-500' : 'bg-transparent' }}"></span>
                        <span class="flex-1 min-w-0">
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium text-gray-900 truncate {{ $unread > 0 ? 'font-semibold' : '' }}">
                                    {{ $name }}
                                </span>
                                @if($unread > 0)
                                    <span class="shrink-0 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1
                                                 rounded-full bg-red-600 text-white text-[10px] font-bold">{{ $unread }}</span>
                                @endif
                            </span>
                            <span class="block text-xs text-gray-400 mt-0.5">
                                {{ $conversation->last_message_at ? $conversation->last_message_at->diffForHumans() : 'No messages yet' }}
                            </span>
                        </span>
                    </button>
                @empty
                    <p class="px-4 py-8 text-sm text-gray-400 text-center">No conversations yet.</p>
                @endforelse
            </div>
        </div>

        {{-- ===================== DETAIL PANE ===================== --}}
        <div>
            @if($this->conversationId && $activePatientId)
                {{-- Reuse the existing staff thread component (send + suggest + audit). --}}
                <livewire:spar.staff-coach-messages
                    :patient-id="$activePatientId"
                    :pharmacy-id="$activePharmacyId"
                    :key="'inbox-thread-'.$this->conversationId" />
            @else
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
                    <p class="text-sm text-gray-400">Select a conversation on the left to open it.</p>
                </div>
            @endif
        </div>
    </div>
</div>
