<div>
    <div class="zc-top">
        <h2>Insight</h2>
        <button type="button" class="zc-chip" wire:click="openWeekly" data-weekly-open>Weekly insight</button>
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)" data-error>{{ $error }}</div>
    @endif

    <div class="zc-card" data-headline="{{ $daily['rule'] }}">
        <h3 data-title>{{ $daily['headline'] }}</h3>
        <p style="margin-top:6px" data-text>{{ $daily['text'] }}</p>
        <div class="zc-row" style="gap:8px;margin-top:10px">
            <span class="zc-sub">Was this useful?</span>
            <button type="button" class="zc-chip {{ $entry?->helpful === true ? 'on' : '' }}" wire:click="feedback(true)" aria-pressed="{{ $entry?->helpful === true ? 'true' : 'false' }}" aria-label="Useful" data-thumb="up">&#128077;</button>
            <button type="button" class="zc-chip {{ $entry?->helpful === false ? 'on' : '' }}" wire:click="feedback(false)" aria-pressed="{{ $entry?->helpful === false ? 'true' : 'false' }}" aria-label="Not useful" data-thumb="down">&#128078;</button>
        </div>
    </div>

    @if ($daily['levelPercent'] !== null)
        <div class="zc-card" data-level>
            <div class="zc-row zc-between"><h3>Your level today</h3><b data-level-percent>{{ $daily['levelPercent'] }}%</b></div>
            <div class="zc-bar" style="margin-top:8px"><i style="width:{{ $daily['levelPercent'] }}%"></i></div>
            <p class="zc-note" style="margin-top:6px">{{ $medDisclaimer }}</p>
        </div>
    @endif

    <div class="zc-card" data-noticed>
        <h3>What we noticed</h3>
        <ul style="margin:8px 0 0;padding-left:18px">
            @foreach ($daily['noticed'] as $line)<li style="padding:2px 0">{{ $line }}</li>@endforeach
        </ul>
    </div>

    <div class="zc-card" data-checkin>
        <h3>How are you feeling today?</h3>
        <div class="zc-row" style="flex-wrap:wrap;gap:8px;margin-top:8px">
            @foreach ($answers as $key => $label)
                <button type="button" class="zc-chip {{ $entry?->answer === $key ? 'on' : '' }}" wire:click="answer('{{ $key }}')" aria-pressed="{{ $entry?->answer === $key ? 'true' : 'false' }}" data-answer="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
        @if ($reply)<p class="zc-sub" style="margin-top:10px" data-reply>{{ $reply }}</p>@endif
    </div>

    <div class="zc-card" data-goal>
        <div class="zc-sub">Today's goal</div>
        <b>{{ $daily['goal']['text'] }}</b>
        <a href="{{ $daily['goal']['url'] }}" class="zc-btn" style="margin-top:10px" data-goal-button>{{ $daily['goal']['label'] }}</a>
    </div>

    @if ($daily['cycle'])
        <div class="zc-card" data-cycle-card>
            <h3>Cycle and weight</h3>
            <p class="zc-sub" style="margin-top:4px">{{ $daily['cycle']['text'] }}</p>
            <a href="{{ route('coach.cycle') }}" class="zc-chip" style="margin-top:8px">Open cycle</a>
        </div>
    @endif

    <p class="zc-disc" data-disclaimer>{{ $disclaimer }}</p>

    @if ($weekly)
        <div>
            <div class="zc-sheetbg" wire:click="closeWeekly"></div>
            <div class="zc-sheet" role="dialog" aria-label="Weekly insight" data-weekly>
                <div class="zc-sub">{{ $weekly['from']->format('j M') }} to {{ $weekly['to']->format('j M') }}</div>
                <h2 style="font-size:22px;margin-bottom:6px" data-weekly-title>{{ $weekly['headline'] }}</h2>
                <div class="zc-grid2">
                    <div class="zc-card"><div class="zc-sub">Entries</div><b data-entries>{{ $weekly['entries'] }}</b></div>
                    <div class="zc-card"><div class="zc-sub">Weight</div><b data-weight-change>{{ $weekly['weightChangeKg'] === null ? '-' : (($weekly['weightChangeKg'] > 0 ? '-' : ($weekly['weightChangeKg'] < 0 ? '+' : '')).number_format(abs($weekly['weightChangeKg']), 1).' kg') }}</b></div>
                </div>
                <h3 style="margin:8px 0">Wins this week</h3>
                @foreach ($weekly['wins'] as $w)
                    <div class="zc-meal" data-win>
                        <span class="zc-eat {{ $w['done'] ? 'on' : '' }}">{{ $w['done'] ? '✓' : '' }}</span>
                        <div class="zc-mbody"><b>{{ $w['label'] }}</b><small>{{ $w['detail'] }}</small></div>
                    </div>
                @endforeach
                <p class="zc-note" style="margin-top:8px">{{ $disclaimer }}</p>
                <button type="button" class="zc-btn ghost" style="margin-top:6px" wire:click="closeWeekly" data-weekly-close>Close</button>
            </div>
        </div>
    @endif
</div>
