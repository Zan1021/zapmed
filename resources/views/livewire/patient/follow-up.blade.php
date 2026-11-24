<div>
    <x-patient-tabs active="follow-up" />

    <h1 class="mb-4 text-2xl font-bold text-gray-900">Follow-up</h1>

    @if(session('followup-booked'))
        <p class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800" data-booked>{{ session('followup-booked') }}</p>
    @endif

    @if($requests->isNotEmpty())
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-5" data-section="requests">
            <h2 class="mb-2 text-sm font-semibold uppercase text-amber-900">Your doctor asked for a follow-up</h2>
            @foreach($requests as $r)
                <p class="mb-1 text-sm text-amber-900" data-request="{{ $r->id }}">
                    Please book by {{ $r->due_at->setTimezone('Africa/Johannesburg')->format('j M Y') }}
                    with Dr {{ $r->doctor?->last_name }} &middot; {{ $r->is_free ? 'no charge' : 'paid' }}
                    @if($requestId !== $r->id)<button type="button" wire:click="pickRequest({{ $r->id }})" class="ml-2 font-medium underline">Use this</button>@else <span class="ml-2 text-xs">(chosen below)</span> @endif
                </p>
            @endforeach
        </div>
    @endif

    @if($upcoming->isNotEmpty())
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5" data-section="upcoming">
            <h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Booked</h2>
            @foreach($upcoming as $a)
                <p class="text-sm text-gray-800" data-appointment="{{ $a->id }}">{{ $a->appointment_date->format('j M Y') }} at {{ \Illuminate\Support\Str::of((string) $a->start_time)->limit(5, '') }}
                    with Dr {{ $a->doctor?->last_name }} ({{ $a->duration_minutes }} min)
                    @if($a->status === 'pending_payment') <span class="text-amber-700">waiting for payment</span> @endif</p>
            @endforeach
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-5" data-section="book">
        <h2 class="mb-3 text-sm font-semibold uppercase text-gray-500">Book an appointment</h2>

        @if($doctors->isEmpty())
            <p class="text-sm text-gray-500" data-empty="doctors">You can book a follow-up once you have seen a doctor.</p>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="text-sm text-gray-700">What for
                    <select wire:model="type" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        @foreach($types as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm text-gray-700">Doctor
                    <select wire:model.live="doctorId" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Choose...</option>
                        @foreach($doctors as $d)<option value="{{ $d->id }}">Dr {{ $d->first_name }} {{ $d->last_name }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm text-gray-700">How long
                    <select wire:model.live="minutes" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        @foreach($durations as $m)<option value="{{ $m }}">{{ $m }} minutes</option>@endforeach
                    </select>
                </label>
                <label class="text-sm text-gray-700">Day
                    <input type="date" wire:model.live="date" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </label>
            </div>

            @if($doctorId)
                <p class="mt-4 mb-2 text-xs text-gray-500">Free times</p>
                <div class="flex flex-wrap gap-2" data-slots>
                    @forelse($slots as $s)
                        <button type="button" wire:click="$set('time', '{{ $s }}')" data-slot="{{ $s }}"
                            class="rounded-lg border px-3 py-1.5 text-sm {{ $time === $s ? 'border-zapmed-600 bg-zapmed-600 text-white' : 'border-gray-300 text-gray-700' }}">{{ $s }}</button>
                    @empty
                        <p class="text-sm text-gray-500">No free times on this day. Try another day.</p>
                    @endforelse
                </div>
            @endif

            @if($fee !== null)
                <p class="mt-4 text-sm text-gray-800" data-fee>
                    @if($fee === 0) No charge for this appointment. @else Cost: <strong>R{{ number_format($fee / 100, 2) }}</strong>. You pay by card on the next page. @endif
                </p>
            @endif

            @foreach(['type', 'doctor', 'minutes', 'date', 'time'] as $f) @error($f) <p class="mt-1 text-xs text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach

            <button type="button" wire:click="book" data-action="book" class="mt-4 rounded-lg bg-zapmed-600 px-5 py-2 text-sm text-white">
                {{ $fee === 0 ? 'Book it' : 'Book and pay' }}
            </button>
        @endif
    </div>
</div>
