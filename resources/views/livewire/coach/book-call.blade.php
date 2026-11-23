<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.today')])
        <h2>Book a call with {{ $coachName }}</h2>
    </div>

    @if (! $hasCoach)
        <div class="zc-card" data-state="no-coach"><p class="zc-sub" style="margin:0">{{ $unassigned }}</p></div>
    @elseif ($upcoming)
        <div class="zc-card" data-state="upcoming">
            <span class="zc-tag ok">Your call is booked</span>
            <h3 style="margin-top:8px" data-upcoming>{{ $upcomingText }}</h3>
            <p class="zc-sub" style="margin:2px 0 12px">{{ $upcoming->length_minutes }} minutes with {{ $coachName }}</p>
            <button type="button" class="zc-btn ghost" wire:click="cancel({{ $upcoming->id }})" wire:confirm="Cancel this call?">Cancel this call</button>
        </div>
        <p class="zc-note">You can hold one call at a time. Cancel it if you would like a different time.</p>
    @else
        <div class="zc-row" style="gap:8px;overflow-x:auto;margin:0 -18px;padding:0 18px 6px">
            @foreach ($days as $d)
                <button type="button" wire:click="pickDate('{{ $d->toDateString() }}')" data-day="{{ $d->toDateString() }}"
                        class="zc-chip {{ $date === $d->toDateString() ? 'on' : '' }}" style="flex:none;text-align:center;min-width:56px">
                    {{ $d->format('D') }}<br><b>{{ $d->format('j') }}</b>
                </button>
            @endforeach
        </div>

        <div class="zc-row" style="margin:8px 0 10px">
            @foreach ($lengths as $l)
                <button type="button" wire:click="pickLength({{ $l }})" data-length="{{ $l }}" class="zc-chip {{ $length === $l ? 'on' : '' }}">{{ $l }} min</button>
            @endforeach
        </div>

        <div class="zc-grid2" data-slots>
            @forelse ($slots as $s)
                <button type="button" wire:click="pickSlot('{{ $s['time'] }}')" data-slot="{{ $s['time'] }}" @disabled(! $s['available'])
                        class="zc-opt {{ $slot === $s['time'] ? 'on' : '' }}" style="text-align:center;{{ $s['available'] ? '' : 'opacity:.4' }}">{{ $s['time'] }}</button>
            @empty
                <p class="zc-sub">No times on this day.</p>
            @endforelse
        </div>

        @if ($slots && ! $anyOpen)
            <p class="zc-note" data-full style="margin:6px 0">{{ $coachName }} has no free times on this day. Please pick another day.</p>
        @endif
        <p class="zc-note" style="margin:10px 0">{{ $note }}</p>

        @if ($error)
            <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)">{{ $error }}</div>
        @endif

        <button type="button" class="zc-btn" wire:click="confirm" wire:loading.attr="disabled" @disabled(! $slot)>Confirm booking</button>
        <p class="zc-note" style="margin-top:8px">You will get a confirmation and a reminder an hour before. Times are South African time.</p>
    @endif
</div>