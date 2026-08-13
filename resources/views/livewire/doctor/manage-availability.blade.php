<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">My Availability</h1>
    <p class="text-gray-600 mb-8">Set your weekly schedule so patients can book consultations with you. Each slot is 15 minutes.</p>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="mb-6 rounded-lg bg-green-50 border border-green-200 p-4">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-green-800 text-sm font-medium">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if (session()->has('warning'))
        <div class="mb-6 rounded-lg bg-amber-50 border border-amber-200 p-4">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-amber-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span class="text-amber-800 text-sm font-medium">{{ session('warning') }}</span>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column: Add Time Range --}}
        <div class="lg:col-span-1 space-y-6">
            {{-- Add Time Range Card --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Add Time Range</h2>

                <form wire:submit="addTimeRange" class="space-y-4">
                    <div>
                        <label for="selectedDay" class="block text-sm font-medium text-gray-700 mb-1">Day of Week</label>
                        <select wire:model="selectedDay" id="selectedDay" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                            <option value="0">Sunday</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="rangeStartTime" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                            <select wire:model="rangeStartTime" id="rangeStartTime" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                                @for ($h = 7; $h < 20; $h++)
                                    @foreach (['00', '15', '30', '45'] as $m)
                                        <option value="{{ sprintf('%02d:%s', $h, $m) }}">{{ sprintf('%02d:%s', $h, $m) }}</option>
                                    @endforeach
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label for="rangeEndTime" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                            <select wire:model="rangeEndTime" id="rangeEndTime" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                                @for ($h = 7; $h <= 20; $h++)
                                    @foreach (['00', '15', '30', '45'] as $m)
                                        @if ($h < 20 || $m === '00')
                                            <option value="{{ sprintf('%02d:%s', $h, $m) }}">{{ sprintf('%02d:%s', $h, $m) }}</option>
                                        @endif
                                    @endforeach
                                @endfor
                            </select>
                        </div>
                    </div>

                    @error('rangeEndTime')
                        <p class="text-red-600 text-xs">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="w-full bg-zapmed-600 hover:bg-zapmed-700 text-white rounded-lg px-4 py-2 text-sm font-medium transition-colors">
                        Add Time Range
                    </button>
                </form>
            </div>

            {{-- Blocked Dates Card --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Blocked Dates</h2>
                <p class="text-sm text-gray-500 mb-4">Mark specific dates when you're unavailable (leave, holidays, etc.)</p>

                <form wire:submit="addBlockedDate" class="space-y-3">
                    <div>
                        <label for="blockedDate" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" wire:model="blockedDate" id="blockedDate" min="{{ now()->format('Y-m-d') }}" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                        @error('blockedDate')
                            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="blockedReason" class="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
                        <input type="text" wire:model="blockedReason" id="blockedReason" placeholder="e.g. Annual leave" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    </div>

                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white rounded-lg px-4 py-2 text-sm font-medium transition-colors">
                        Block Date
                    </button>
                </form>

                {{-- Blocked dates list --}}
                @if ($this->blockedDatesList->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        @foreach ($this->blockedDatesList as $blocked)
                            <div class="flex items-center justify-between bg-red-50 rounded-lg px-3 py-2">
                                <div>
                                    <span class="text-sm font-medium text-red-800">{{ $blocked->blocked_date->format('D, d M Y') }}</span>
                                    @if ($blocked->reason)
                                        <span class="text-xs text-red-600 ml-1">({{ $blocked->reason }})</span>
                                    @endif
                                </div>
                                <button wire:click="removeBlockedDate({{ $blocked->id }})" wire:confirm="Remove this blocked date?" class="text-red-600 hover:text-red-800 text-xs font-medium">
                                    Remove
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Right Column: Weekly Schedule --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Weekly Schedule</h2>

                <div class="space-y-4">
                    @foreach ($this->availabilityByDay as $dayIndex => $day)
                        <div class="border border-gray-100 rounded-xl p-4 {{ !empty($day['ranges']) ? 'bg-green-50/50' : 'bg-gray-50/50' }}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $day['name'] }}</h3>
                                    @if ($day['slot_count'] > 0)
                                        <span class="text-xs bg-zapmed-100 text-zapmed-700 px-2 py-0.5 rounded-full font-medium">
                                            {{ $day['slot_count'] }} slots
                                        </span>
                                    @endif
                                </div>
                                @if (!empty($day['ranges']))
                                    <button wire:click="removeDaySlots({{ $dayIndex }})" wire:confirm="Remove all slots for {{ $day['name'] }}?" class="text-xs text-red-600 hover:text-red-800 font-medium">
                                        Clear Day
                                    </button>
                                @endif
                            </div>

                            @if (empty($day['ranges']))
                                <p class="text-sm text-gray-400 italic">No availability set</p>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($day['ranges'] as $range)
                                        <div class="inline-flex items-center bg-white border border-zapmed-200 rounded-lg px-3 py-1.5 shadow-sm">
                                            <span class="text-sm font-medium text-gray-800">{{ $range['start'] }} - {{ $range['end'] }}</span>
                                            <button wire:click="removeTimeRange({{ $dayIndex }}, '{{ $range['start'] }}', '{{ $range['end'] }}')" wire:confirm="Remove this time range?" class="ml-2 text-gray-400 hover:text-red-600 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
