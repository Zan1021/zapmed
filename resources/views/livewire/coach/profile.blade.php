<div>
    <div class="zc-top">
        <div style="flex:1">
            <div class="zc-sub">{{ $user->first_name }}</div>
            <h2>Profile</h2>
        </div>
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)" data-error>{{ $error }}</div>
    @endif

    {{-- Programme --}}
    <div class="zc-card zc-row zc-between" data-section="programme">
        <div><b>Zapmed programme</b><div class="zc-sub">Coaching with {{ $coachName }} included</div></div>
        <span class="zc-tag ok">Active</span>
    </div>
    <a href="{{ route('coach.milestone') }}" class="zc-card zc-row zc-between" data-link="milestone">
        <div><b>Your progress</b><div class="zc-sub">See how far you have come</div></div>
        <span aria-hidden="true">&#8250;</span>
    </a>
    <div class="zc-card zc-row zc-between" style="opacity:.6" data-link="rewards" aria-disabled="true">
        <div><b>Share Zapmed, earn rewards</b><div class="zc-sub">Coming soon</div></div>
    </div>

    {{-- Goals --}}
    <div class="zc-card" data-section="goals">
        <h3>Goals</h3>
        <div class="zc-sub" style="margin:6px 0">Start weight {{ $startText ?? '-' }} &middot; Now {{ $currentText ?? '-' }}</div>
        <label class="zc-sub" for="zc-goal">Goal weight (kg). Not below {{ $minGoalKg }} kg or at or above your current weight.</label>
        <div class="zc-row" style="margin:6px 0 12px">
            <input id="zc-goal" type="text" inputmode="decimal" wire:model="goal" wire:keydown.enter="saveGoal" placeholder="e.g. 70" aria-label="Goal weight in kg">
            <button type="button" class="zc-btn sm" wire:click="saveGoal" data-save="goal">Save</button>
        </div>
        <div class="zc-sub" style="margin-bottom:6px">How long do you want to take?</div>
        <div class="zc-row" style="flex-wrap:wrap;gap:8px">
            @foreach ($o['target_months'] as $m)
                <button type="button" class="zc-chip {{ (int) $p->target_months === $m ? 'on' : '' }}" wire:click="setMonths({{ $m }})" data-months="{{ $m }}">{{ $m }} months</button>
            @endforeach
        </div>
        <div class="zc-sub" style="margin-top:12px" data-targets>
            Daily water {{ number_format($targets['water_ml']) }} mL &middot; {{ number_format($targets['kcal']) }} kcal &middot; protein {{ $targets['protein_g'] }} g &middot; fibre {{ $targets['fibre_g'] }} g
        </div>
        <p class="zc-note" style="margin:6px 0 0">A starting point. Amy and your doctor adjust it for you.</p>
    </div>

    {{-- Personal details --}}
    <div class="zc-card" data-section="details">
        <h3>About you</h3>
        <div class="zc-sub" style="margin:6px 0">
            {{ $user->name }}@if ($user->gender) &middot; {{ ucfirst($user->gender) }}@endif @if ($birthYear) &middot; Born {{ $birthYear }}@endif
        </div>
        <label class="zc-sub" for="zc-height">Height (cm)</label>
        <div class="zc-row" style="margin:6px 0 12px">
            <input id="zc-height" type="text" inputmode="numeric" wire:model="height" wire:keydown.enter="saveHeight" placeholder="e.g. 168" aria-label="Height in cm">
            <button type="button" class="zc-btn sm" wire:click="saveHeight" data-save="height">Save</button>
        </div>
        <div class="zc-sub" style="margin-bottom:6px">Health concerns</div>
        <div class="zc-row" style="flex-wrap:wrap;gap:8px">
            @foreach ($o['health_concerns'] as $key => $label)
                <button type="button" class="zc-chip {{ in_array($key, (array) $p->health_concerns, true) ? 'on' : '' }}" wire:click="toggle('health_concerns','{{ $key }}')" data-concern="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- Lifestyle --}}
    <div class="zc-card" data-section="lifestyle">
        <h3>Lifestyle</h3>
        <div class="zc-sub" style="margin:6px 0">Diet</div>
        <div class="zc-row" style="flex-wrap:wrap;gap:8px">
            @foreach ($o['diets'] as $key => $label)
                <button type="button" class="zc-chip {{ $p->diet === $key ? 'on' : '' }}" wire:click="setDiet('{{ $key }}')" data-diet="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="zc-sub" style="margin:12px 0 6px">Allergies</div>
        <div class="zc-row" style="flex-wrap:wrap;gap:8px">
            @foreach ($o['allergies'] as $key => $label)
                <button type="button" class="zc-chip {{ in_array($key, (array) $p->allergies, true) ? 'on' : '' }}" wire:click="toggle('allergies','{{ $key }}')" data-allergy="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="zc-sub" style="margin:12px 0 6px">Foods you avoid</div>
        <div class="zc-row" style="flex-wrap:wrap;gap:8px">
            @foreach ($o['avoids'] as $key => $label)
                <button type="button" class="zc-chip {{ in_array($key, (array) $p->avoids, true) ? 'on' : '' }}" wire:click="toggle('avoids','{{ $key }}')" data-avoid="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="zc-sub" style="margin:12px 0 6px">Activity level</div>
        <div class="zc-row" style="flex-wrap:wrap;gap:8px">
            @foreach ($o['activity_levels'] as $key => $label)
                <button type="button" class="zc-chip {{ $p->activity_level === $key ? 'on' : '' }}" wire:click="setActivity('{{ $key }}')" data-activity="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- Cycle switch (hidden for men) --}}
    @if ($showCycleSwitch)
        <div class="zc-card zc-row zc-between" data-section="cycle">
            <div><b>Cycle tracker</b><div class="zc-sub">Shows your cycle on your weight and reminders</div></div>
            <button type="button" class="zc-switch {{ $p->track_cycle ? 'on' : '' }}" wire:click="toggleCycle"
                    role="switch" aria-checked="{{ $p->track_cycle ? 'true' : 'false' }}" aria-label="Cycle tracker"></button>
        </div>
    @endif

    {{-- Units (only when the kg/lb switch is turned on in config) --}}
    @if ($allowLb)
        <div class="zc-card zc-row zc-between" data-section="units">
            <b>Units</b>
            <div class="zc-row" style="gap:8px">
                @foreach (['kg', 'lb'] as $u)
                    <button type="button" class="zc-chip {{ $units === $u ? 'on' : '' }}" wire:click="setUnits('{{ $u }}')" data-unit="{{ $u }}">{{ $u }}</button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Settings --}}
    <a href="{{ route('coach.reminders') }}" class="zc-card zc-row zc-between" data-link="reminders"><b>Reminders</b><span aria-hidden="true">&#8250;</span></a>
    <a href="{{ route('profile') }}" class="zc-card zc-row zc-between" data-link="account"><b>Account</b><span aria-hidden="true">&#8250;</span></a>

    <p class="zc-disc">{{ config('coach.disclaimers.insight') }}</p>
</div>
