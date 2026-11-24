<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.today')])
        <h2>Activity</h2>
        <a href="{{ route('coach.activity.log') }}" class="zc-btn sm" data-log>Log</a>
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)" data-error>{{ $error }}</div>
    @endif

    <div class="zc-grid2">
        <div class="zc-card"><div class="zc-sub">Burned today</div><div class="zc-big" style="font-size:30px" data-kcal>{{ number_format($totals['kcal']) }} kcal</div></div>
        <div class="zc-card"><div class="zc-sub">Steps</div><div class="zc-big" style="font-size:30px" data-steps>{{ $totals['steps'] ? number_format($totals['steps']) : '-' }}</div></div>
    </div>

    <div class="zc-card" data-week>
        <h3>Last 7 days</h3>
        <svg viewBox="0 0 330 120" width="100%" role="img" aria-label="Calories burned in the last 7 days">
            @foreach ($week as $i => $d)
                @php $h = $d['kcal'] > 0 ? max(4, round($d['kcal'] / $weekMax * 80)) : 2; @endphp
                <rect x="{{ 10 + $i * 46 }}" y="{{ 90 - $h }}" width="30" height="{{ $h }}" rx="6" fill="{{ $d['today'] ? '#2E767F' : '#9CC3C7' }}" data-bar="{{ $d['date'] }}" data-kcal="{{ $d['kcal'] }}"/>
                <text x="{{ 25 + $i * 46 }}" y="108" text-anchor="middle" font-size="12" fill="#506765">{{ $d['label'] }}</text>
            @endforeach
        </svg>
    </div>

    <div class="zc-card" data-steps-form>
        <h3>Steps today</h3>
        <div class="zc-row" style="margin-top:8px">
            <input type="text" inputmode="numeric" wire:model="steps" wire:keydown.enter="saveSteps" placeholder="e.g. 6420" aria-label="Steps today" data-field="steps">
            <button type="button" class="zc-btn sm" wire:click="saveSteps" data-save-steps>Save</button>
        </div>
        <p class="zc-note" style="margin-top:6px">Typing a new number replaces today's steps.</p>
    </div>

    <div class="zc-card zc-row zc-between" style="opacity:.65" data-sync aria-disabled="true">
        <div><b>Sync with Apple Health or Google Fit</b><div class="zc-sub">Coming soon. Steps will come in by themselves and you stay in control.</div></div>
    </div>

    <div class="zc-card" data-today>
        <h3>Today</h3>
        @forelse ($entries as $e)
            <div class="zc-meal" data-entry="{{ $e->id }}">
                <div class="zc-mbody">
                    <b>{{ $e->name }}@if ($e->kind === 'workout'), {{ $e->minutes }} min @endif</b>
                    <small>@if ($e->kind === 'steps'){{ number_format($e->steps) }} steps &middot; @endif{{ $e->kcal }} kcal</small>
                </div>
                <button type="button" class="zc-chip" wire:click="remove({{ $e->id }})" aria-label="Delete {{ $e->name }}" data-remove="{{ $e->id }}">Delete</button>
            </div>
        @empty
            <div class="zc-sub" style="margin-top:6px">Nothing logged yet today.</div>
        @endforelse
    </div>

    <p class="zc-note">Calories are estimates from your weight and the type of activity.</p>
</div>
