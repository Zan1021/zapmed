<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.weight')])
        <h2>Log weight</h2>
    </div>

    <div class="zc-card" style="text-align:center;padding:28px 16px">
        <div class="zc-row" style="justify-content:center;gap:18px">
            <button type="button" class="zc-back" style="width:56px;height:56px;font-size:26px" wire:click="step(-1)" aria-label="Less 0.1">&minus;</button>
            <div>
                <input type="number" inputmode="decimal" step="0.1" wire:model="value" aria-label="Weight in {{ $unit }}"
                       style="width:150px;height:auto;border:0;background:transparent;text-align:center;font-family:'Prata',serif;font-size:48px;padding:0">
                <div class="zc-sub">{{ $unit }}</div>
            </div>
            <button type="button" class="zc-back" style="width:56px;height:56px;font-size:26px" wire:click="step(1)" aria-label="More 0.1">+</button>
        </div>

        @if ($allowLb)
        <div class="zc-row" style="justify-content:center;margin-top:16px">
            <button type="button" wire:click="setUnit('kg')" class="zc-chip {{ $unit === 'kg' ? 'on' : '' }}">kg</button>
            <button type="button" wire:click="setUnit('lb')" class="zc-chip {{ $unit === 'lb' ? 'on' : '' }}">lb</button>
        </div>
        @endif

        @if ($cycle)
            <div style="margin-top:14px"><span class="zc-tag sage">Cycle day {{ $cycle['day'] }} &middot; {{ $cycle['label'] }}</span></div>
        @endif
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)">{{ $error }}</div>
    @endif

    <button type="button" class="zc-btn" wire:click="save" wire:loading.attr="disabled">Save today's weight</button>
</div>