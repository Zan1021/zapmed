<div>
    <x-patient-tabs active="orders" />

    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">{{ $showArchived ? 'Archived orders' : 'My orders' }}</h1>
        <button type="button" wire:click="$toggle('showArchived')" data-action="toggle-archive" class="text-sm text-zapmed-700 hover:underline">
            {{ $showArchived ? 'Back to my orders' : 'See archived orders' }}
        </button>
    </div>

    @foreach(['order', 'reason'] as $f) @error($f) <p class="mb-3 text-sm text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach

    @forelse($cards as $o)
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5" data-order="{{ $o['id'] }}">
            <div class="flex items-start justify-between">
                <div>
                    <p class="font-semibold text-gray-900">Order {{ $o['reference'] }}</p>
                    <p class="text-xs text-gray-500">{{ $o['ordered_at']?->format('j M Y') }}@if($o['category']) &middot; {{ ucfirst(str_replace('_', ' ', $o['category'])) }}@endif</p>
                </div>
                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-700" data-status-label>{{ $o['status_label'] }}</span>
            </div>

            @if($o['repeats'])
                <p class="mt-3 text-sm text-gray-800" data-repeats>Repeats left: <strong>{{ $o['repeats']['left'] }} of {{ $o['repeats']['total'] }}</strong></p>
            @endif

            @if($o['next_payment'])
                <p class="mt-1 text-sm text-gray-800" data-next-payment>
                    Next payment: <strong>R{{ number_format($o['next_payment']['amount_cents'] / 100, 2) }}</strong>
                    on {{ $o['next_payment']['date']->setTimezone('Africa/Johannesburg')->format('j M Y') }}
                    @if($o['next_payment']['on_hold']) <span class="text-amber-700">(on hold)</span> @endif
                </p>
            @endif

            @if($o['hold'])
                <p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900" data-hold>{{ $o['hold'] }}</p>
            @endif

            @if($o['renewal'])
                <p class="mt-1 text-sm text-gray-700" data-renewal>
                    Your treatment is approved until {{ $o['renewal']['ends_on']->format('j M Y') }}.
                    @if($o['renewal']['due'])
                        <a href="{{ route('patient.follow-up') }}" class="font-medium text-zapmed-700 hover:underline" data-action="check-in">Book a check-in</a> to renew it.
                    @endif
                </p>
            @endif

            @if($o['follow_up'])
                <p class="mt-1 text-sm text-gray-700" data-follow-up>
                    Your doctor asked for a follow-up by {{ $o['follow_up']['due_at']->setTimezone('Africa/Johannesburg')->format('j M Y') }}.
                    @if($o['follow_up']['status'] === 'requested')<a href="{{ route('patient.follow-up') }}" class="font-medium text-zapmed-700 hover:underline">Book it</a>@else Booked. @endif
                </p>
            @endif

            @if($o['tracking_url'])
                <p class="mt-1 text-sm"><a href="{{ $o['tracking_url'] }}" target="_blank" rel="noopener" class="text-zapmed-700 hover:underline">Track your delivery</a></p>
            @endif

            @if($o['can_continue'])
                <p class="mt-2 text-sm text-gray-700">You can continue this order until {{ $o['paused_until_deadline']->format('j M Y') }}.</p>
            @endif

            @if($acting === $o['id'])
                <div class="mt-3 border-t border-gray-100 pt-3" data-form="{{ $mode }}">
                    <label class="mb-1 block text-sm text-gray-700">{{ $mode === 'cancel' ? 'Why do you want to cancel?' : 'Why do you want to pause?' }}</label>
                    <textarea wire:model="reason" rows="2" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                    <div class="mt-2 flex gap-3">
                        <button type="button" wire:click="confirm" data-action="confirm-{{ $mode }}" class="rounded-lg bg-zapmed-600 px-4 py-2 text-sm text-white">{{ $mode === 'cancel' ? 'Cancel my order' : 'Pause my order' }}</button>
                        <button type="button" wire:click="close" class="text-sm text-gray-500">Never mind</button>
                    </div>
                </div>
            @else
                <div class="mt-3 flex flex-wrap gap-4 text-sm">
                    @if($o['resume'])
                        @if($o['resume']['type'] === 'payment')
                            <a href="{{ $o['resume']['url'] }}" data-action="resume" class="font-medium text-zapmed-700 hover:underline">Finish paying</a>
                        @else
                            <button type="button" wire:click="resumeCheckout({{ $o['id'] }})" data-action="resume" class="font-medium text-zapmed-700 hover:underline">Finish paying</button>
                        @endif
                    @endif
                    @if($o['can_continue'])<button type="button" wire:click="continueOrder({{ $o['id'] }})" data-action="continue" class="font-medium text-zapmed-700 hover:underline">Continue my order</button>@endif
                    @if($o['can_pause'])<button type="button" wire:click="ask({{ $o['id'] }}, 'pause')" data-action="pause" class="text-gray-700 hover:underline">Pause</button>@endif
                    @if($o['can_cancel'])<button type="button" wire:click="ask({{ $o['id'] }}, 'cancel')" data-action="cancel" class="text-gray-700 hover:underline">Cancel</button>@endif
                    @if($o['can_archive'])<button type="button" wire:click="archive({{ $o['id'] }})" data-action="archive" class="text-gray-500 hover:underline">Archive</button>@endif
                    @if($o['archived'])<button type="button" wire:click="unarchive({{ $o['id'] }})" data-action="unarchive" class="text-gray-500 hover:underline">Bring back</button>@endif
                </div>
            @endif
        </div>
    @empty
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500" data-empty="orders">
            {{ $showArchived ? 'No archived orders.' : 'You have no orders yet.' }}
        </div>
    @endforelse
</div>
