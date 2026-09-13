<div>
    <x-slot name="header">Coach Console</x-slot>

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('message') }}</div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Assigned patients --}}
        <div class="md:col-span-1">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">
                {{ $isAdmin ? 'All active assignments' : 'My patients' }}
            </h3>
            <div class="border border-gray-200 rounded-lg divide-y divide-gray-100">
                @forelse($this->assignments as $assignment)
                    <button type="button" wire:click="selectPatient({{ $assignment->patient_id }})" wire:key="asg-{{ $assignment->id }}"
                            class="w-full text-left px-4 py-3 hover:bg-gray-50 {{ $selectedPatientId === $assignment->patient_id ? 'bg-indigo-50' : '' }}">
                        <div class="text-sm font-medium text-gray-800">
                            {{ $assignment->patient?->first_name }} {{ $assignment->patient?->last_name }}
                        </div>
                        <div class="text-xs text-gray-400 font-mono">{{ $assignment->patient?->member_number }}</div>
                        @if($isAdmin)
                            <div class="text-[11px] text-gray-400">Coach: {{ $assignment->coach?->first_name }} {{ $assignment->coach?->last_name }}</div>
                        @endif
                    </button>
                @empty
                    <p class="px-4 py-6 text-sm text-gray-400 text-center">No active assignments.</p>
                @endforelse
            </div>
        </div>

        {{-- Selected patient panel --}}
        <div class="md:col-span-2">
            @if($this->selectedPatient)
                @php $p = $this->selectedPatient; @endphp
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">{{ $p->first_name }} {{ $p->last_name }}</h3>
                    <button wire:click="closePatient" class="text-xs text-gray-500 hover:underline">Close</button>
                </div>

                {{-- Admin reassignment --}}
                @if($isAdmin)
                    <div class="border border-gray-200 rounded-lg p-4 mb-6">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Assign / reassign coach</h4>
                        <div class="flex gap-2">
                            <select wire:model="assignCoachId" class="flex-1 text-sm border-gray-300 rounded-lg">
                                <option value="">Select coach…</option>
                                @foreach($this->coaches as $coach)
                                    <option value="{{ $coach->id }}">{{ $coach->first_name }} {{ $coach->last_name }}</option>
                                @endforeach
                            </select>
                            <input type="text" wire:model="assignReason" placeholder="Reason (optional)" class="flex-1 text-sm border-gray-300 rounded-lg" />
                            <button wire:click="assignCoach" class="px-3 py-1.5 rounded-lg text-sm text-white bg-gray-700 hover:bg-gray-800">Assign</button>
                        </div>
                        @error('assignCoachId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Log touchpoint --}}
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Log touchpoint</h4>
                        <div class="space-y-2">
                            <div class="flex gap-2">
                                <select wire:model="tpKind" class="flex-1 text-sm border-gray-300 rounded-lg">
                                    @foreach($touchpointKinds as $kind)
                                        <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                    @endforeach
                                </select>
                                <select wire:model="tpChannel" class="flex-1 text-sm border-gray-300 rounded-lg">
                                    @foreach($touchpointChannels as $ch)
                                        <option value="{{ $ch->value }}">{{ $ch->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <select wire:model="tpDirection" class="w-full text-sm border-gray-300 rounded-lg">
                                <option value="outbound">Outbound (coach → patient)</option>
                                <option value="inbound">Inbound (patient → coach)</option>
                            </select>
                            <textarea wire:model="tpSummary" rows="2" placeholder="Summary" class="w-full text-sm border-gray-300 rounded-lg"></textarea>
                            <select wire:model="tpSentiment" class="w-full text-sm border-gray-300 rounded-lg">
                                <option value="">Sentiment (optional)</option>
                                <option value="positive">Positive</option>
                                <option value="neutral">Neutral</option>
                                <option value="negative">Negative</option>
                            </select>
                            <button wire:click="logTouchpoint" class="px-3 py-1.5 rounded-lg text-sm text-white bg-indigo-600 hover:bg-indigo-700">Log</button>
                        </div>
                    </div>

                    {{-- Cross-sell offer --}}
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">New cross-sell offer</h4>
                        <div class="space-y-2">
                            <input type="text" wire:model="offerServiceLine" placeholder="Service line (e.g. weight-loss)" class="w-full text-sm border-gray-300 rounded-lg" />
                            @error('offerServiceLine') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <textarea wire:model="offerNotes" rows="2" placeholder="Notes (optional)" class="w-full text-sm border-gray-300 rounded-lg"></textarea>
                            <button wire:click="makeOffer" class="px-3 py-1.5 rounded-lg text-sm text-white bg-emerald-600 hover:bg-emerald-700">Create offer</button>
                        </div>
                    </div>
                </div>

                {{-- Offers list --}}
                <div class="mt-6">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Offers</h4>
                    @forelse($this->offers as $offer)
                        <div class="flex items-center justify-between text-sm border border-gray-100 rounded-lg px-3 py-2 mb-1" wire:key="offer-{{ $offer->id }}">
                            <div>
                                <span class="font-medium">{{ $offer->service_line }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full ml-2
                                    {{ $offer->status->value === 'accepted' ? 'bg-emerald-100 text-emerald-700' :
                                       ($offer->status->value === 'open' ? 'bg-sky-100 text-sky-700' : 'bg-gray-100 text-gray-500') }}">
                                    {{ $offer->status->label() }}
                                </span>
                            </div>
                            @if($offer->status->value === 'open')
                                <div class="space-x-2">
                                    <button wire:click="acceptOffer({{ $offer->id }})" class="text-xs text-emerald-700 hover:underline">Accept</button>
                                    <button wire:click="declineOffer({{ $offer->id }})" class="text-xs text-gray-500 hover:underline">Decline</button>
                                    <button wire:click="withdrawOffer({{ $offer->id }})" class="text-xs text-gray-400 hover:underline">Withdraw</button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">No offers.</p>
                    @endforelse
                </div>

                {{-- Touchpoint history --}}
                <div class="mt-6">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Touchpoints</h4>
                    @forelse($this->touchpoints as $tp)
                        <div class="text-sm border border-gray-100 rounded-lg px-3 py-2 mb-1" wire:key="tp-{{ $tp->id }}">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ $tp->kind->label() }} · {{ $tp->channel->label() }}</span>
                                <span class="text-xs text-gray-400">{{ $tp->direction }} · {{ $tp->occurred_at?->diffForHumans() }}</span>
                            </div>
                            @if($tp->summary) <p class="text-xs text-gray-600 mt-0.5">{{ $tp->summary }}</p> @endif
                            <p class="text-[11px] text-gray-400">by {{ $tp->coach?->first_name }} {{ $tp->coach?->last_name }}</p>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">No touchpoints logged.</p>
                    @endforelse
                </div>
            @else
                <div class="border border-dashed border-gray-200 rounded-lg p-12 text-center text-gray-400">
                    Select a patient to view coaching activity.
                </div>
            @endif
        </div>
    </div>
</div>
