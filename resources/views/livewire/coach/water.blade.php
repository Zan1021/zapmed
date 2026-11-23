<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.today')])
        <h2>Water</h2>
    </div>

    <div class="zc-card" style="text-align:center">
        <svg viewBox="0 0 120 150" width="150" role="img" aria-label="Water drop, {{ number_format($total) }} of {{ number_format($goal) }} mL">
            <defs><clipPath id="zc-drop"><path d="M60 8 C60 8 14 62 14 98 a46 46 0 0 0 92 0 C106 62 60 8 60 8Z"/></clipPath></defs>
            <path d="M60 8 C60 8 14 62 14 98 a46 46 0 0 0 92 0 C106 62 60 8 60 8Z" fill="#EAF2F2" stroke="#2E767F" stroke-width="2"/>
            <rect x="0" y="{{ $dropY }}" width="120" height="146" fill="#2E767F" clip-path="url(#zc-drop)" opacity=".85" data-drop-y="{{ number_format($dropY, 1, '.', '') }}"/>
        </svg>
        <div class="zc-big" data-total>{{ number_format($total) }} mL</div>
        <div class="zc-sub">of {{ number_format($goal) }} mL goal</div>
        @if ($goalReached)<div style="margin-top:8px"><span class="zc-tag ok" data-goal-reached>Goal reached. Well done.</span></div>@endif
    </div>

    <div class="zc-grid2">
        @foreach ($quickAdds as $label => $ml)
            <button type="button" class="zc-opt" wire:click="add({{ $ml }})" wire:loading.attr="disabled" data-quick="{{ $ml }}">
                {{ $label }}<small>{{ number_format($ml) }} mL</small>
            </button>
        @endforeach
    </div>

    <div class="zc-row" style="margin:4px 0 12px">
        <input type="text" inputmode="numeric" wire:model="exact" wire:keydown.enter="addExact" placeholder="Exact amount (mL)" aria-label="Exact amount in mL">
        <button type="button" class="zc-btn sm" wire:click="addExact">Add</button>
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)">{{ $error }}</div>
    @endif

    <div class="zc-card">
        <div class="zc-row zc-between">
            <h3>Today</h3>
            <button type="button" class="zc-chip" wire:click="undo" @disabled($recent->isEmpty())>Undo last</button>
        </div>
        @forelse ($recent as $e)
            <div class="zc-row zc-between" style="padding:8px 0;border-bottom:1px solid var(--zc-line)" data-entry>
                <span class="zc-sub">&#128167; {{ $e['time'] }}</span><b>{{ number_format($e['ml']) }} mL</b>
            </div>
        @empty
            <div class="zc-sub">Nothing logged yet.</div>
        @endforelse
    </div>

    <a href="{{ route('coach.reminders') }}" class="zc-card zc-row zc-between" data-link="reminders"><span>Hydration reminders</span><span aria-hidden="true">&#8250;</span></a>

    @if ($lutealTip)
        <div class="zc-amy"><div class="zc-avatar">A</div><div class="zc-sub" style="color:var(--zc-forest)" data-luteal>{{ $lutealTip }}</div></div>
    @endif
</div>