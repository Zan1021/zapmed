<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.today')])
        <h2>Medication</h2>
        <a href="{{ route('coach.medication.log') }}" class="zc-btn sm">Log dose</a>
    </div>

    @if (! $chart['hasData'])
        <div class="zc-card" data-empty="medication">
            <h3>Nothing logged yet</h3>
            <p class="zc-sub" style="margin:4px 0 12px">Log your first dose and we'll show your estimated level and your next dose date.</p>
            <a href="{{ route('coach.medication.log') }}" class="zc-btn">Log my first dose</a>
            <p class="zc-note" style="margin-top:10px">{{ config('coach.medication.no_dose_advice') }}</p>
        </div>
    @else
        <div class="zc-card">
            <div class="zc-row zc-between">
                <div>
                    <div class="zc-big" data-level>{{ $level->levelNowPercent }}%</div>
                    <div class="zc-sub">estimated level today</div>
                </div>
                <span class="zc-tag" style="background:var(--zc-warn-l);color:#8a5a12">Estimate only</span>
            </div>
            <svg viewBox="0 0 330 150" width="100%" style="margin-top:8px" role="img" aria-label="Estimated level, 14 days back and 7 days ahead" data-chart>
                @if ($chart['past'])<polyline points="{{ $chart['past'] }}" fill="none" stroke="#2E767F" stroke-width="2.6" stroke-linejoin="round"/>@endif
                @if ($chart['future'])<polyline points="{{ $chart['future'] }}" fill="none" stroke="#2E767F" stroke-width="2.4" stroke-dasharray="5 4" stroke-linejoin="round"/>@endif
                <line x1="{{ $chart['todayX'] }}" x2="{{ $chart['todayX'] }}" y1="6" y2="136" stroke="#203E3E" stroke-dasharray="2 3"/>
                <text x="{{ $chart['todayX'] }}" y="148" font-size="10" text-anchor="middle" fill="#506765">today</text>
                <text x="15" y="148" font-size="10" fill="#506765">14 days ago</text>
                <text x="315" y="148" font-size="10" text-anchor="end" fill="#506765">+7 days</text>
            </svg>
            <div class="zc-note" data-disclaimer>{{ $disclaimer }}</div>
        </div>

        <div class="zc-grid2">
            <div class="zc-card" style="margin:0">
                <span class="zc-sub">Next dose</span>
                <div class="zc-big" style="font-size:20px" data-next>{{ $level->nextDoseDate->format('D j M') }}</div>
                <div class="zc-bar" style="margin:8px 0 4px"><i style="width: {{ $progress }}%"></i></div>
                <span class="zc-note" data-days>
                    @if ($level->daysToNext > 0) {{ $level->daysToNext }} {{ \Illuminate\Support\Str::plural('day', $level->daysToNext) }} to go
                    @elseif ($level->daysToNext === 0) Today
                    @else Was due {{ abs($level->daysToNext) }} {{ \Illuminate\Support\Str::plural('day', abs($level->daysToNext)) }} ago
                    @endif
                </span>
            </div>
            <div class="zc-card" style="margin:0">
                <span class="zc-sub">Last dose</span>
                <div class="zc-big" style="font-size:20px" data-last>{{ $level->lastDoseAt->format('D j M') }}</div>
                <span class="zc-note">{{ $level->lastSite }}</span>
            </div>
        </div>

        <div class="zc-amy">
            <div class="zc-avatar">A</div>
            <div class="zc-sub" style="color:var(--zc-forest)" data-tip>{{ $tips['appetite'] }}@if ($showLutealTip) {{ $tips['luteal'] }}@endif</div>
        </div>

        <div class="zc-card" style="margin-top:12px">
            <h3>Dosage history</h3>
            @foreach ($history as $h)
                <div class="zc-row zc-between" style="padding:8px 0;border-bottom:1px solid var(--zc-line)">
                    <span class="zc-sub">{{ $h['when'] }} &middot; {{ $h['site'] }}</span>
                    <span class="zc-note">Pain {{ $h['pain'] }}/10</span>
                </div>
            @endforeach
        </div>
    @endif

    <a href="{{ route('coach.reminders') }}" class="zc-card zc-row zc-between" data-link="reminders">
        <span>Dose reminder</span>
        <span class="zc-tag">{{ $reminderText }}</span>
    </a>
    <p class="zc-note">{{ config('coach.medication.no_dose_advice') }}</p>
</div>