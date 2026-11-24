<div wire:poll.60s>
    <div class="zc-top"><h2>My members</h2></div>
    <p class="zc-sub">Members assigned to you. Safety flags first, then members who feel worse today, then new messages, then the quietest.</p>

    <div class="zc-row" style="gap:8px;margin-bottom:12px">
        <a href="{{ route('coach.inbox') }}" class="zc-chip" data-link="inbox">Messages and calls</a>
        <a href="{{ route('coach.flags') }}" class="zc-chip" data-link="flags">Safety flags</a>
    </div>

    @forelse ($rows as $r)
        @php($p = $r['patient'])
        <a href="{{ route('coach.board.patient', $p) }}" class="zc-card" data-member="{{ $p->id }}"
           @if ($r['openFlags']) style="box-shadow:inset 0 0 0 2px var(--zc-alert)" @endif>
            <div class="zc-row zc-between">
                <b>{{ $p->first_name }} {{ $p->last_name }}</b>
                <span class="zc-row" style="gap:6px">
                    @if ($r['openFlags'])<span class="zc-tag red" data-flag>{{ $r['openFlags'] }} {{ $r['openFlags'] === 1 ? 'flag' : 'flags' }}</span>@endif
                    @if ($r['worse'])<span class="zc-tag red" data-worse>feels worse today</span>@endif
    @if ($r['unread'])<span class="zc-tag red" data-unread>{{ $r['unread'] }} new</span>@endif
                    @if ($r['quiet'])<span class="zc-tag" data-quiet>quiet</span>@endif
                </span>
            </div>
            <div class="zc-sub">
                {{ $p->member_number }}
                &middot; {{ $r['percent'] !== null ? $r['percent'].'% to goal' : 'no goal yet' }}
                &middot; {{ $r['lastWeighIn'] ? 'weighed '.$r['lastWeighIn']->format('j M') : 'no weigh-in yet' }}
                @if ($r['nextCall']) &middot; call {{ $calls->when($r['nextCall']) }}@endif
            </div>
        </a>
    @empty
        <div class="zc-card" data-empty="board"><h3>No members yet</h3><p class="zc-sub">Members appear here once they are assigned to you.</p></div>
    @endforelse
</div>
