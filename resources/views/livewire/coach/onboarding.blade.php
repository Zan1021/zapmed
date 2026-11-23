<div>
    @if ($step === 1)
        <div class="zc-top"><span class="zc-tag">Step 1 of 2</span></div>
        <div class="zc-bar" style="margin-bottom:18px"><i style="width:50%"></i></div>

        <h1 style="font-size:28px;margin-bottom:6px">What worries you most about starting?</h1>
        <p class="zc-sub" style="margin-bottom:14px">Pick one. There are no wrong answers.</p>

        @foreach ($worries as $key => $w)
            <button type="button" wire:click="chooseWorry('{{ $key }}')"
                    class="zc-opt {{ $worry === $key ? 'on' : '' }}" aria-pressed="{{ $worry === $key ? 'true' : 'false' }}">
                {{ $w['label'] }}
            </button>
        @endforeach

        <div class="zc-amy">
            <div class="zc-avatar">A</div>
            <div>
                <b>Amy, your dietitian coach</b>
                <div class="zc-sub" style="color:var(--zc-forest)">
                    {{ $reply ?? "Tell me what's on your mind and I'll tailor your plan." }}
                </div>
            </div>
        </div>

        <button type="button" class="zc-btn" wire:click="next" @disabled(! $worry)>Continue</button>
    @else
        <div class="zc-top">
            <button type="button" class="zc-back" wire:click="back" aria-label="Back">&#8592;</button>
            <h2>Your starting plan</h2>
        </div>
        <div class="zc-bar" style="margin-bottom:18px"><i style="width:100%"></i></div>

        <div class="zc-card">
            <h3>Daily targets</h3>
            <p class="zc-note" style="margin:0 0 10px">{{ $planNote }}</p>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center">
                <div><div class="zc-big" style="font-size:24px">{{ $targets['protein_g'] }} g</div><span class="zc-sub">protein</span></div>
                <div><div class="zc-big" style="font-size:24px">{{ $targets['fibre_g'] }} g</div><span class="zc-sub">fibre</span></div>
                <div><div class="zc-big" style="font-size:24px">{{ number_format($targets['water_ml'] / 1000, 1) }} L</div><span class="zc-sub">water</span></div>
            </div>
        </div>

        @if ($showCycleSwitch)
            <div class="zc-card zc-row zc-between">
                <div>
                    <b>Track my cycle</b>
                    <div class="zc-sub">Shows cycle tips across the app. You can change this any time.</div>
                </div>
                <button type="button" wire:click="toggleCycle" class="zc-switch {{ $trackCycle ? 'on' : '' }}"
                        role="switch" aria-checked="{{ $trackCycle ? 'true' : 'false' }}" aria-label="Track my cycle"></button>
            </div>
        @endif

        <div class="zc-amy">
            <div class="zc-avatar">A</div>
            <div class="zc-sub" style="color:var(--zc-forest)">{{ $intro }}</div>
        </div>

        <button type="button" class="zc-btn" wire:click="finish">Go to my tracker</button>
        <p class="zc-note" style="text-align:center;margin-top:10px">Booking a welcome call opens soon. Amy will also message you here.</p>
    @endif
</div>
