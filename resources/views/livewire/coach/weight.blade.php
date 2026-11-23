<div x-data="{ bmi: false }">
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.today')])
        <h2>Weight</h2>
        <a href="{{ route('coach.weight.log') }}" class="zc-btn sm">Log</a>
    </div>

    @if (! $chart['hasData'])
        <div class="zc-card" data-empty="weight">
            <h3>Start your chart</h3>
            <p class="zc-sub" style="margin:4px 0 12px">Log your first weigh-in and your progress will show here.</p>
            <a href="{{ route('coach.weight.log') }}" class="zc-btn">Log my weight</a>
        </div>
    @else
        <div class="zc-card">
            <div class="zc-row" style="gap:8px;margin-bottom:8px">
                @foreach ($ranges as $r)
                    <button type="button" wire:click="setRange({{ $r }})" class="zc-chip {{ $range === $r ? 'on' : '' }}">{{ $r }} days</button>
                @endforeach
            </div>
            <svg viewBox="0 0 330 170" width="100%" role="img" aria-label="Weight over the last {{ $range }} days" data-chart>
                @foreach ($chart['luteal'] as $l)
                    <rect x="{{ $l['x'] }}" y="0" width="{{ $l['w'] }}" height="150" fill="#DCE5DA" opacity=".8"/>
                @endforeach
                @if ($chart['goalY'] !== null)
                    <line x1="18" x2="312" y1="{{ $chart['goalY'] }}" y2="{{ $chart['goalY'] }}" stroke="#526E60" stroke-dasharray="5 4"/>
                    <text x="312" y="{{ $chart['goalY'] - 4 }}" font-size="10" text-anchor="end" fill="#526E60">Goal</text>
                @endif
                @if ($chart['line'])
                    <polyline points="{{ $chart['line'] }}" fill="none" stroke="#2E767F" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"/>
                @endif
                @php($last = end($chart['points']))
                <circle cx="{{ $last['x'] }}" cy="{{ $last['y'] }}" r="5" fill="#203E3E"/>
                <text x="18" y="164" font-size="10" fill="#506765">{{ $range }} days ago</text>
                <text x="312" y="164" font-size="10" text-anchor="end" fill="#506765">today</text>
            </svg>
            @if ($trackCycle && $chart['luteal'])
                <div class="zc-note" style="margin-top:4px"><span style="display:inline-block;width:10px;height:10px;background:var(--zc-sage-l);border-radius:2px"></span> Luteal phase (water can hide fat loss)</div>
            @endif
        </div>

        <div class="zc-amy">
            <div class="zc-avatar">A</div>
            <div class="zc-sub" style="color:var(--zc-forest)" data-tip>{{ $tip }}</div>
        </div>

        <div class="zc-grid2">
            <div class="zc-card" style="margin:0"><span class="zc-sub">Total change</span><div class="zc-big" style="font-size:22px" data-tile="change">{{ $changeText }}</div></div>
            <button type="button" class="zc-card" style="margin:0;text-align:left" @click="bmi = true">
                <span class="zc-sub">BMI &#9432;</span>
                <div class="zc-big" style="font-size:22px" data-tile="bmi">{{ $stats->bmi !== null ? number_format($stats->bmi, 1) : '-' }}</div>
            </button>
            <div class="zc-card" style="margin:0"><span class="zc-sub">Goal</span><div class="zc-big" style="font-size:22px" data-tile="goal">{{ $goalText ?? '-' }}</div></div>
            <div class="zc-card" style="margin:0"><span class="zc-sub">Progress</span><div class="zc-big" style="font-size:22px" data-tile="progress">{{ $stats->percentToGoal !== null ? $stats->percentToGoal.'%' : '-' }}</div></div>
        </div>

        <p class="zc-note" style="margin:10px 2px" data-predicted>
            @if ($stats->predictedGoalDate)
                Predicted goal date: about {{ $stats->predictedGoalDate->format('F Y') }}, based on your average loss over the last 90 days.
            @elseif ($stats->goal !== null)
                Predicted goal date: - (it needs a few weigh-ins that show a loss).
            @endif
        </p>

        <div class="zc-card" style="margin-top:12px">
            <h3>History</h3>
            @foreach ($history as $h)
                <div class="zc-row zc-between" style="padding:8px 0;border-bottom:1px solid var(--zc-line)">
                    <span class="zc-sub">{{ $h->logged_on->format('D j M') }}</span>
                    <b>{{ \App\Services\Coach\CoachUnits::format((float) $h->weight_kg, $units) }}</b>
                </div>
            @endforeach
        </div>
    @endif

    <div class="zc-card zc-row zc-between">
        <span>Weigh-in reminder</span>
        <span class="zc-tag">{{ $weighInDay ? $weighInDay.'s' : 'Off' }}</span>
    </div>

    <button type="button" class="zc-card zc-row zc-between" style="width:100%;text-align:left" wire:click="openGoal">
        <span>Weight goal</span><b>{{ $goalText ?? 'Set a goal' }}</b>
    </button>

    {{-- BMI info (local, no server trip) --}}
    <div x-show="bmi" x-cloak>
        <div class="zc-sheetbg" @click="bmi = false"></div>
        <div class="zc-sheet" role="dialog" aria-label="About BMI">
            <h2 style="font-size:20px;margin-bottom:6px">About BMI</h2>
            <p class="zc-sub">BMI is your weight divided by your height squared. {{ $bmiNote }} Your doctor looks at the full picture.</p>
            <button type="button" class="zc-btn" style="margin-top:10px" @click="bmi = false">Got it</button>
        </div>
    </div>

    {{-- Goal editor --}}
    @if ($showGoal)
        <div class="zc-sheetbg" wire:click="closeGoal"></div>
        <div class="zc-sheet" role="dialog" aria-label="Weight goal">
            <h2 style="font-size:20px;margin-bottom:6px">Weight goal</h2>
            <p class="zc-sub">Step by 1 {{ $units }}. A goal can't be below {{ $minGoalKg }} kg or at or above your current weight. Amy reviews it with you each month.</p>
            @if ($goalError)
                <div role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert);border-radius:14px;padding:10px 12px;margin-top:10px">{{ $goalError }}</div>
            @endif
            <div class="zc-row" style="justify-content:center;gap:20px;margin:14px 0">
                <button type="button" class="zc-back" style="width:52px;height:52px" wire:click="stepGoal(-1)" aria-label="Lower goal">&minus;</button>
                <div class="zc-big" data-goal-input>{{ number_format($goalInput, 0) }} {{ $units }}</div>
                <button type="button" class="zc-back" style="width:52px;height:52px" wire:click="stepGoal(1)" aria-label="Raise goal">+</button>
            </div>
            <button type="button" class="zc-btn" wire:click="saveGoal">Save goal</button>
        </div>
    @endif
</div>