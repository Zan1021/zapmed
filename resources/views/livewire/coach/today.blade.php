<div>
    <div class="zc-top">
        <div style="flex:1">
            <div class="zc-sub">Hello, {{ $firstName }}</div>
            <h2>Today</h2>
        </div>
    </div>

    {{-- Next reminder --}}
    @if ($reminders)
        <div class="zc-card zc-row">
            <span style="font-size:22px" aria-hidden="true">&#128276;</span>
            <div style="flex:1">
                <b>Next reminder</b>
                <div class="zc-sub">{{ implode(' · ', $reminders) }}</div>
            </div>
        </div>
    @endif

    {{-- Weight --}}
    <a href="{{ route('coach.weight') }}" class="zc-card" data-card="weight">
        <div class="zc-row zc-between">
            <h3>Weight</h3>
            @if ($weight->percentToGoal !== null)
                <span class="zc-tag ok">{{ $weight->percentToGoal }}% to goal</span>
            @endif
        </div>
        @if ($weight->current !== null)
            <div class="zc-row zc-between" style="align-items:flex-end">
                <div>
                    <div class="zc-big">{{ $weightText($weight->current) }}</div>
                    <div class="zc-sub">
                        @if ($weight->goal !== null)
                            Goal {{ $weightText($weight->goal) }} &middot;
                            {{ $weight->predictedGoalDate ? 'Predicted '.$weight->predictedGoalDate->format('M Y') : 'Predicted date: -' }}
                        @else
                            Last logged {{ $weight->lastLoggedOn->format('j M') }}
                        @endif
                    </div>
                </div>
                @if ($weightSpark)
                    <svg width="130" height="46" role="img" aria-label="Weight over the last 30 days">
                        <polyline fill="none" stroke="#2E767F" stroke-width="2.4" points="{{ $weightSpark }}"/>
                    </svg>
                @endif
            </div>
        @else
            <div class="zc-sub">No weight logged yet. Your first weigh-in starts the chart.</div>
        @endif
    </a>

    {{-- Medication --}}
    <a href="{{ route('coach.medication') }}" class="zc-card" data-card="medication">
        <div class="zc-row zc-between">
            <h3>Medication</h3>
            @if ($med->daysToNext !== null)
                <span class="zc-tag">
                    @if ($med->daysToNext > 0) Next dose in {{ $med->daysToNext }} {{ \Illuminate\Support\Str::plural('day', $med->daysToNext) }}
                    @elseif ($med->daysToNext === 0) Next dose today
                    @else Next dose was due {{ abs($med->daysToNext) }} {{ \Illuminate\Support\Str::plural('day', abs($med->daysToNext)) }} ago
                    @endif
                </span>
            @endif
        </div>
        @if ($med->levelNowPercent !== null)
            <div class="zc-row zc-between" style="align-items:flex-end">
                <div>
                    <div class="zc-big">{{ $med->levelNowPercent }}%</div>
                    <div class="zc-sub">estimated level today</div>
                </div>
                <svg width="130" height="46" role="img" aria-label="Estimated level, 14 days back and 7 days ahead">
                    @if ($medCurve['past'])<polyline fill="none" stroke="#2E767F" stroke-width="2.4" points="{{ $medCurve['past'] }}"/>@endif
                    @if ($medCurve['future'])<polyline fill="none" stroke="#2E767F" stroke-width="2.2" stroke-dasharray="5 4" points="{{ $medCurve['future'] }}"/>@endif
                </svg>
            </div>
            <p class="zc-note" style="margin:6px 0 0">{{ config('coach.disclaimers.medication_curve') }}</p>
        @else
            <div class="zc-sub">Log your first dose to see your estimate.</div>
        @endif
    </a>

    {{-- Side effects --}}
    <a href="{{ route('coach.side-effects') }}" class="zc-card zc-row zc-between" data-card="side-effects">
        <div><h3>How are you feeling?</h3><div class="zc-sub">Score your symptoms. Anything serious reaches a doctor straight away.</div></div>
        <span aria-hidden="true">&#8250;</span>
    </a>

    {{-- Cycle (only when the member switched it on) --}}
    @if ($cycleOn)
        <div class="zc-card" data-card="cycle">
            <div class="zc-row zc-between">
                <h3>Cycle</h3>
                @if ($cycle)<span class="zc-tag sage">{{ $cycle['label'] }}</span>@endif
            </div>
            @if ($cycle)
                <div class="zc-sub">Day {{ $cycle['day'] }} &middot; period expected in {{ $cycle['days_to_period'] }} {{ \Illuminate\Support\Str::plural('day', $cycle['days_to_period']) }}</div>
                @php($arrow = ['up' => '&#8599;', 'down' => '&#8600;', 'steady' => '&#8594;'])
                <div class="zc-row" style="gap:16px;margin-top:8px;font-size:13px">
                    @foreach (['weight' => 'Weight', 'hunger' => 'Hunger', 'water' => 'Water', 'mood' => 'Mood'] as $k => $label)
                        <span>{{ $label }} {!! $arrow[$cycle['arrows'][$k] ?? 'steady'] !!}</span>
                    @endforeach
                </div>
                <p class="zc-note" style="margin:6px 0 0">{{ config('coach.disclaimers.cycle') }}</p>
            @else
                <div class="zc-sub">Add the date your last period started to see your phase.</div>
            @endif
        </div>
    @endif

    {{-- Water --}}
    <div class="zc-card" data-card="water">
        <div class="zc-row zc-between">
            <h3>Water</h3>
            <button type="button" class="zc-btn sm" wire:click="addWater({{ $quickAdd }})" wire:loading.attr="disabled">+{{ $quickAdd }} mL</button>
        </div>
        <div class="zc-sub"><b>{{ number_format($waterToday) }}</b> / {{ number_format($waterGoal) }} mL today</div>
        <div class="zc-bar" style="margin-top:8px"><i style="width: {{ min(100, $waterGoal ? round($waterToday / $waterGoal * 100) : 0) }}%"></i></div>
    </div>

    <p class="zc-disc">{{ config('coach.disclaimers.insight') }}</p>
</div>
