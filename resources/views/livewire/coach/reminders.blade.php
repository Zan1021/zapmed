<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.today')])
        <h2>Reminders</h2>
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)">{{ $error }}</div>
    @endif

    {{-- Hydration --}}
    <div class="zc-card" data-reminder="hydration">
        <div class="zc-row zc-between">
            <b>Hydration</b>
            <button type="button" class="zc-switch {{ $s['hydration']['enabled'] ? 'on' : '' }}" wire:click="toggle('hydration')"
                    role="switch" aria-checked="{{ $s['hydration']['enabled'] ? 'true' : 'false' }}" aria-label="Hydration reminders"></button>
        </div>
        @if ($s['hydration']['enabled'])
            <div class="zc-grid2" style="margin-top:10px">
                <select wire:change="setValue('hydration','every_hours',$event.target.value)" aria-label="How often">
                    @foreach ($o['every_hours'] as $h)<option value="{{ $h }}" @selected($s['hydration']['every_hours'] == $h)>Every {{ $h === 1 ? 'hour' : $h.' hours' }}</option>@endforeach
                </select>
                <select wire:change="setValue('hydration','channel',$event.target.value)" aria-label="Send by">
                    @foreach ($o['channels'] as $key => $label)<option value="{{ $key }}" @selected($s['hydration']['channel'] === $key)>{{ $label }}</option>@endforeach
                </select>
                <select wire:change="setValue('hydration','from',$event.target.value)" aria-label="From">
                    @foreach ($o['from'] as $t)<option value="{{ $t }}" @selected($s['hydration']['from'] === $t)>From {{ $t }}</option>@endforeach
                </select>
                <select wire:change="setValue('hydration','until',$event.target.value)" aria-label="Until">
                    @foreach ($o['until'] as $t)<option value="{{ $t }}" @selected($s['hydration']['until'] === $t)>Until {{ $t }}</option>@endforeach
                </select>
            </div>
            <div class="zc-row zc-between" style="margin-top:10px">
                <span class="zc-sub">Smart nudge (skip if you're on pace)</span>
                <button type="button" class="zc-switch {{ $s['hydration']['smart_skip'] ? 'on' : '' }}"
                        wire:click="setValue('hydration','smart_skip','{{ $s['hydration']['smart_skip'] ? '0' : '1' }}')"
                        role="switch" aria-checked="{{ $s['hydration']['smart_skip'] ? 'true' : 'false' }}" aria-label="Smart nudge"></button>
            </div>
            <button type="button" class="zc-chip" style="margin-top:8px" wire:click="preview('hydration')">Preview</button>
        @endif
    </div>

    {{-- Weigh-in --}}
    <div class="zc-card" data-reminder="weigh_in">
        <div class="zc-row zc-between">
            <b>Weekly weigh-in</b>
            <button type="button" class="zc-switch {{ $s['weigh_in']['enabled'] ? 'on' : '' }}" wire:click="toggle('weigh_in')"
                    role="switch" aria-checked="{{ $s['weigh_in']['enabled'] ? 'true' : 'false' }}" aria-label="Weigh-in reminder"></button>
        </div>
        @if ($s['weigh_in']['enabled'])
            <div class="zc-grid2" style="margin-top:10px">
                <select wire:change="setValue('weigh_in','day',$event.target.value)" aria-label="Day">
                    @foreach ($o['days'] as $d)<option value="{{ $d }}" @selected($s['weigh_in']['day'] === $d)>{{ ucfirst($d) }}</option>@endforeach
                </select>
                <select wire:change="setValue('weigh_in','frequency',$event.target.value)" aria-label="Frequency">
                    @foreach ($o['frequencies'] as $f)<option value="{{ $f }}" @selected($s['weigh_in']['frequency'] === $f)>{{ ucfirst($f) }}</option>@endforeach
                </select>
            </div>
        @endif
    </div>

    {{-- Dose day --}}
    <div class="zc-card" data-reminder="dose_day">
        <div class="zc-row zc-between">
            <b>Dose day</b>
            <button type="button" class="zc-switch {{ $s['dose_day']['enabled'] ? 'on' : '' }}" wire:click="toggle('dose_day')"
                    role="switch" aria-checked="{{ $s['dose_day']['enabled'] ? 'true' : 'false' }}" aria-label="Dose day reminder"></button>
        </div>
        @if ($s['dose_day']['enabled'])
            <div class="zc-sub" style="margin:6px 0">Sent the evening before and on the day, at the time you pick.</div>
            <select wire:change="setValue('dose_day','time',$event.target.value)" aria-label="Time">
                @foreach ($o['dose_times'] as $t)<option value="{{ $t }}" @selected($s['dose_day']['time'] === $t)>{{ $t }}</option>@endforeach
            </select>
        @endif
    </div>

    {{-- Meals --}}
    <div class="zc-card zc-row zc-between" data-reminder="meals">
        <div><b>Log your meals</b><div class="zc-sub">A gentle nudge at lunch and dinner. Starts when meal logging arrives.</div></div>
        <button type="button" class="zc-switch {{ $s['meals']['enabled'] ? 'on' : '' }}" wire:click="toggle('meals')"
                role="switch" aria-checked="{{ $s['meals']['enabled'] ? 'true' : 'false' }}" aria-label="Meal reminders"></button>
    </div>

    {{-- Cycle (women who track) --}}
    @if ($cycleOn)
        <div class="zc-card zc-row zc-between" data-reminder="cycle">
            <div><b>Cycle heads-up</b><div class="zc-sub">2 days before your period and at the luteal phase</div></div>
            <button type="button" class="zc-switch {{ $s['cycle']['enabled'] ? 'on' : '' }}" wire:click="toggle('cycle')"
                    role="switch" aria-checked="{{ $s['cycle']['enabled'] ? 'true' : 'false' }}" aria-label="Cycle reminder"></button>
        </div>
    @endif

    <p class="zc-note">Reminders never name your medication and carry no health detail.</p>
</div>