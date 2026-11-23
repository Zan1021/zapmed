<div wire:poll.30s>
    <div class="zc-top"><h2>Coach inbox</h2></div>
    <p class="zc-sub">Messages from the members assigned to you.</p>

    @if ($calls->isNotEmpty())
        <div class="zc-card" data-upcoming-calls>
            <h3>Upcoming calls</h3>
            @foreach ($calls as $c)
                <div class="zc-row zc-between" style="padding:8px 0;border-bottom:1px solid var(--zc-line)" data-call>
                    <span>{{ $c->patient?->first_name }} {{ $c->patient?->last_name }} @if ($c->purpose)<span class="zc-tag">{{ str_replace('_', ' ', $c->purpose) }}</span>@endif</span>
                    <span class="zc-sub">{{ $callsSvc->when($c) }} &middot; {{ $c->length_minutes }} min</span>
                </div>
            @endforeach
        </div>
    @endif

    @forelse ($threads as $t)
        <a href="{{ route('coach.inbox.thread', $t['patient']) }}" class="zc-card" data-thread-link="{{ $t['patient']->id }}">
            <div class="zc-row zc-between">
                <b>{{ $t['patient']->first_name }} {{ $t['patient']->last_name }}</b>
                @if ($t['unread'])<span class="zc-tag red" data-unread>{{ $t['unread'] }} new</span>@endif
            </div>
            <div class="zc-sub">{{ $t['patient']->member_number }} &middot; {{ $t['last']->created_at->setTimezone($tz)->format('D j M, H:i') }}</div>
        </a>
    @empty
        <div class="zc-card" data-empty="inbox"><h3>No messages</h3><p class="zc-sub">Nothing from your members yet.</p></div>
    @endforelse
</div>