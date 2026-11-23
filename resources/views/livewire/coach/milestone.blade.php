<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.profile')])
        <h2>Milestone</h2>
    </div>

    @if ($s->hasProgress())
        <div class="zc-card" style="text-align:center" data-milestone="progress">
            <div class="zc-sub">You have lost</div>
            <div class="zc-big" style="font-size:44px" data-percent>{{ rtrim(rtrim(number_format($s->percentLost, 1), '0'), '.') }}%</div>
            <div class="zc-sub">of your starting weight</div>
        </div>
    @else
        <div class="zc-card" data-milestone="empty">{{ $empty }}</div>
    @endif

    <div class="zc-grid2" style="grid-template-columns:repeat(3,1fr)">
        <div class="zc-card" style="text-align:center" data-stat="lost"><div class="zc-big">{{ $lostText }}</div><div class="zc-sub">lost</div></div>
        <div class="zc-card" style="text-align:center" data-stat="days"><div class="zc-big">{{ $s->daysLogged }}</div><div class="zc-sub">days logged</div></div>
        <div class="zc-card" style="text-align:center" data-stat="checkins"><div class="zc-big">{{ $s->checkIns }}</div><div class="zc-sub">check-ins</div></div>
    </div>

    <div class="zc-card zc-amy" data-note>
        <b>A note from {{ $coachName }}</b>
        <p style="margin:6px 0 0">{{ $note }}</p>
    </div>

    @if ($hasCoach)
        <a href="{{ route('coach.book', ['for' => 'maintenance']) }}" class="zc-btn" data-action="maintenance">Book a maintenance call</a>
    @endif
</div>
