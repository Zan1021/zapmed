<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.nutrition')])
        <h2>Log a meal</h2>
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)" data-error>{{ $error }}</div>
    @endif

    <div class="zc-row" style="flex-wrap:wrap;gap:8px;margin-bottom:12px" data-slots>
        @foreach ($slots as $key => $label)
            <button type="button" class="zc-chip {{ $slot === $key ? 'on' : '' }}" wire:click="setSlot('{{ $key }}')" aria-pressed="{{ $slot === $key ? 'true' : 'false' }}" data-slot="{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($planned)
        <div class="zc-card" data-planned>
            <div class="zc-sub">Planned for today</div>
            <b>{{ $planned->recipe->name }}</b>
            <div class="zc-sub">{{ $planned->recipe->nutrition($planned->servings)['kcal'] }} kcal &middot; {{ rtrim(rtrim(number_format($planned->recipe->nutrition($planned->servings)['protein_g'], 1), '0'), '.') }} g protein</div>
            <button type="button" class="zc-btn" style="margin-top:10px" wire:click="logPlanned" wire:loading.attr="disabled" data-log-planned>Log this meal</button>
        </div>
    @endif

    <div class="zc-card" data-quick-add>
        <h3>Quick add</h3>
        <label class="zc-sub" for="zc-name">Name</label>
        <input id="zc-name" type="text" wire:model="name" maxlength="120" style="margin:4px 0 10px" data-field="name">
        <div class="zc-grid2">
            <div><label class="zc-sub" for="zc-kcal">Calories</label><input id="zc-kcal" type="text" inputmode="numeric" wire:model="kcal" data-field="kcal"></div>
            <div><label class="zc-sub" for="zc-protein">Protein (g)</label><input id="zc-protein" type="text" inputmode="decimal" wire:model="protein" data-field="protein"></div>
        </div>
        <div class="zc-sub" style="margin:10px 0 4px">Optional</div>
        <div class="zc-grid2" style="grid-template-columns:repeat(3,1fr)">
            <div><label class="zc-sub" for="zc-fibre">Fibre</label><input id="zc-fibre" type="text" inputmode="decimal" wire:model="fibre"></div>
            <div><label class="zc-sub" for="zc-carbs">Carbs</label><input id="zc-carbs" type="text" inputmode="decimal" wire:model="carbs"></div>
            <div><label class="zc-sub" for="zc-fat">Fat</label><input id="zc-fat" type="text" inputmode="decimal" wire:model="fat"></div>
        </div>
        <button type="button" class="zc-btn" style="margin-top:12px" wire:click="quickAdd" wire:loading.attr="disabled" data-quick-save>Add to {{ strtolower($slots[$slot]) }}</button>
    </div>

    @if ($recent->isNotEmpty())
        <div class="zc-card" data-recent>
            <h3>Recent</h3>
            @foreach ($recent as $r)
                <div class="zc-meal">
                    <div class="zc-mbody"><b>{{ $r->name }}</b><small>{{ $r->kcal }} kcal &middot; {{ rtrim(rtrim(number_format($r->protein_g, 1), '0'), '.') }} g protein</small></div>
                    <button type="button" class="zc-chip" wire:click="relog({{ $r->id }})" data-relog="{{ $r->id }}">Add</button>
                </div>
            @endforeach
        </div>
    @endif

    <a href="{{ route('coach.recipes') }}" class="zc-btn ghost" data-recipes-link>Browse recipes</a>
    <p class="zc-note" style="margin-top:10px">Food scan, menu scan and voice logging are coming later.</p>
</div>
