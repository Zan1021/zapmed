<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.activity')])
        <h2>Log activity</h2>
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)" data-error>{{ $error }}</div>
    @endif

    @if ($workout)
        <div class="zc-card" data-chosen>
            <div class="zc-row zc-between"><b>{{ $workout['emoji'] }} {{ $workout['name'] }}</b><button type="button" class="zc-chip" wire:click="cancel" data-change>Change</button></div>
            <div class="zc-sub" style="margin:10px 0 6px">How long?</div>
            <div class="zc-row" style="flex-wrap:wrap;gap:8px">
                @foreach ($quick as $q)
                    <button type="button" class="zc-chip {{ (string) $q === trim($minutes) ? 'on' : '' }}" wire:click="setMinutes({{ $q }})" data-minutes="{{ $q }}">{{ $q }} min</button>
                @endforeach
            </div>
            <label class="zc-sub" for="zc-min" style="display:block;margin-top:10px">Or type the minutes</label>
            <input id="zc-min" type="text" inputmode="numeric" wire:model.live.debounce.300ms="minutes" style="margin-top:4px" data-field="minutes">
            @if ($estimate !== null)<div class="zc-sub" style="margin-top:8px" data-estimate>About <b>{{ $estimate }} kcal</b> (estimate)</div>@endif
            <button type="button" class="zc-btn" style="margin-top:12px" wire:click="save" wire:loading.attr="disabled" data-save>Add workout</button>
        </div>
    @else
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search workouts" aria-label="Search workouts" style="margin-bottom:12px" data-search>
        @forelse ($groups as $label => $items)
            <div class="zc-card" data-group="{{ $label }}">
                <h3>{{ $label }}</h3>
                @foreach ($items as $key => $w)
                    <button type="button" class="zc-opt" style="margin:8px 0 0" wire:click="select('{{ $key }}')" data-workout="{{ $key }}">{{ $w['emoji'] }} {{ $w['name'] }}</button>
                @endforeach
            </div>
        @empty
            <div class="zc-card" data-empty>No workouts match "{{ $search }}".</div>
        @endforelse
    @endif
</div>
