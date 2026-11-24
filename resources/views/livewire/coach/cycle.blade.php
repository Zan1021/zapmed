<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.today')])
        <h2>Cycle</h2>
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)" data-error>{{ $error }}</div>
    @endif

    @if ($status)
        <div class="zc-card" style="text-align:center" data-wheel>
            <svg viewBox="0 0 200 200" width="220" role="img" aria-label="Cycle day {{ $status['day'] }}, {{ $status['label'] }}">
                @foreach ($arcs as $a)
                    <path d="{{ $a['d'] }}" fill="none" stroke="{{ $a['colour'] }}" stroke-width="{{ $a['width'] }}" stroke-linecap="butt" @if ($a['today']) data-today-arc @endif/>
                @endforeach
                <text x="100" y="96" text-anchor="middle" font-size="12" fill="#506765">Cycle day</text>
                <text x="100" y="124" text-anchor="middle" font-size="32" font-weight="700" fill="#203E3E" data-day>{{ $status['day'] }}</text>
            </svg>
            <div><span class="zc-tag sage" data-phase>{{ $status['label'] }}</span></div>
            <div class="zc-sub" style="margin-top:6px" data-next>Period expected in about {{ $status['days_to_period'] }} {{ \Illuminate\Support\Str::plural('day', $status['days_to_period']) }}</div>
        </div>

        <div class="zc-grid2" data-cards>
            @foreach (['weight' => 'Weight', 'hunger' => 'Hunger', 'water' => 'Water', 'mood' => 'Mood'] as $k => $label)
                <div class="zc-card" data-card="{{ $k }}">
                    <b>{{ $label }} {!! $arrows[$status['arrows'][$k] ?? 'steady'] !!}</b>
                    <div class="zc-sub" style="margin-top:4px">{{ $cards[$k] ?? '' }}</div>
                </div>
            @endforeach
        </div>

        @if ($tip)
            <div class="zc-amy zc-card" data-tip><div class="zc-avatar">A</div><div><b>Amy's tip</b><div class="zc-sub">{{ $tip }}</div></div></div>
        @endif

        <div class="zc-card" data-mood>
            <h3>How is your mood today?</h3>
            <div class="zc-row" style="gap:8px;margin-top:8px;justify-content:space-between">
                @foreach ($moods as $m => $face)
                    <button type="button" class="zc-chip {{ $entry?->mood === $m ? 'on' : '' }}" style="font-size:22px;padding:6px 10px" wire:click="mood({{ $m }})" aria-pressed="{{ $entry?->mood === $m ? 'true' : 'false' }}" aria-label="{{ $face['label'] }}" data-mood-pick="{{ $m }}">{{ $face['emoji'] }}</button>
                @endforeach
            </div>
            <h3 style="margin-top:14px">Symptoms</h3>
            <div class="zc-row" style="flex-wrap:wrap;gap:8px;margin-top:8px">
                @foreach ($symptoms as $key => $label)
                    @php($on = in_array($key, (array) ($entry?->symptoms ?? []), true))
                    <button type="button" class="zc-chip {{ $on ? 'on' : '' }}" wire:click="symptom('{{ $key }}')" aria-pressed="{{ $on ? 'true' : 'false' }}" data-symptom="{{ $key }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <button type="button" class="zc-btn" wire:click="startToday" wire:loading.attr="disabled" data-period-today>Period started today</button>
    @else
        <div class="zc-card" data-setup>
            <h3>When did your last period start?</h3>
            <p class="zc-sub" style="margin:6px 0 10px">Pick the first day. We work out your cycle day and phase from it.</p>
            <input type="date" wire:model="periodDate" max="{{ $today }}" aria-label="First day of your last period" data-field="date" style="width:100%;height:46px;border-radius:14px;border:1.5px solid var(--zc-line);padding:0 12px">
            <button type="button" class="zc-btn" style="margin-top:12px" wire:click="savePeriodDate" data-save-date>Save</button>
        </div>
    @endif

    <div class="zc-card" data-lengths>
        <h3>Your cycle</h3>
        <div class="zc-grid2" style="margin-top:8px">
            <div><label class="zc-sub" for="zc-cl">Cycle length (days)</label><input id="zc-cl" type="text" inputmode="numeric" wire:model="cycleLength" data-field="cycle"></div>
            <div><label class="zc-sub" for="zc-pl">Period length (days)</label><input id="zc-pl" type="text" inputmode="numeric" wire:model="periodLength" data-field="period"></div>
        </div>
        <button type="button" class="zc-btn ghost" style="margin-top:10px" wire:click="saveLengths" data-save-lengths>Save lengths</button>
        <p class="zc-note" style="margin-top:6px">Cycle {{ config('coach.cycle.min_length') }} to {{ config('coach.cycle.max_length') }} days. Default {{ config('coach.cycle.default_length') }}.</p>
    </div>

    <p class="zc-note" data-notice>{{ $notice }}</p>
</div>
