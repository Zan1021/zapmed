<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.recipes')])
        <h2 style="font-size:20px">{{ $recipe->name }}</h2>
        <button type="button" class="zc-heart {{ $favourite ? 'on' : '' }}" style="position:static" wire:click="toggleFavourite" aria-pressed="{{ $favourite ? 'true' : 'false' }}" aria-label="{{ $favourite ? 'Remove from favourites' : 'Add to favourites' }}" data-heart="{{ $recipe->id }}">{{ $favourite ? '♥' : '♡' }}</button>
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)" data-error>{{ $error }}</div>
    @endif

    <div class="zc-card" style="padding:0;overflow:hidden">
        <div class="zc-tile" style="height:150px;display:grid;place-items:center;font-size:52px;background:{{ $recipe->colour ?: '#DCE5DA' }}">@if ($recipe->photoUrl())<img src="{{ $recipe->photoUrl() }}" alt="{{ $recipe->name }}" style="width:100%;height:100%;object-fit:cover" data-photo>@else{{ $recipe->emoji }}@endif</div>
        <div style="padding:12px 16px">
            <span class="zc-tag">{{ config('coach.recipes.slots')[$recipe->slot] ?? ucfirst($recipe->slot) }}</span>
            <span class="zc-sub">&nbsp;{{ $recipe->minutes }} min</span>
            @if ($recipe->isDemo())<div class="zc-note" style="margin-top:6px" data-demo-note>{{ config('coach.recipes.demo_note') }}</div>@endif
        </div>
    </div>

    <div class="zc-row zc-between" style="margin-bottom:10px">
        <div class="zc-row" style="gap:8px">
            <button type="button" class="zc-chip {{ $tab === 'recipe' ? 'on' : '' }}" wire:click="setTab('recipe')" data-tab="recipe">Recipe</button>
            <button type="button" class="zc-chip {{ $tab === 'nutrition' ? 'on' : '' }}" wire:click="setTab('nutrition')" data-tab="nutrition">Nutrition</button>
        </div>
        <div class="zc-stepper" aria-label="Servings">
            <button type="button" wire:click="less" @disabled($servings <= $min) aria-label="Fewer servings" data-less>&minus;</button>
            <b data-servings>{{ $servings }}</b>
            <button type="button" wire:click="more" @disabled($servings >= $max) aria-label="More servings" data-more>+</button>
        </div>
    </div>

    @if ($tab === 'recipe')
        <div class="zc-card" data-pane="recipe">
            <h3>Ingredients</h3>
            <ul style="margin:8px 0 14px;padding:0;list-style:none">
                @foreach ($ingredients as $i)
                    <li class="zc-row zc-between" style="padding:4px 0"><span>{{ $i['name'] }}</span><span class="zc-sub" data-grams>{{ rtrim(rtrim(number_format($i['grams'], 1), '0'), '.') }} g</span></li>
                @endforeach
            </ul>
            <h3>Steps</h3>
            <ol style="margin:8px 0 0;padding-left:20px">
                @foreach ((array) $recipe->steps as $step)
                    <li style="padding:3px 0">{{ $step }}</li>
                @endforeach
            </ol>
        </div>
    @else
        <div class="zc-card" data-pane="nutrition">
            <div class="zc-row zc-between"><b>Calories</b><span data-kcal>{{ number_format($nutrition['kcal']) }} kcal</span></div>
            <div class="zc-row zc-between" style="margin-top:6px"><b>Protein</b><span data-protein>{{ $nutrition['protein_g'] }} g</span></div>
            <div class="zc-row zc-between" style="margin-top:6px"><b>Carbs</b><span>{{ $nutrition['carbs_g'] }} g</span></div>
            <div class="zc-row zc-between" style="margin-top:6px"><b>Fat</b><span>{{ $nutrition['fat_g'] }} g</span></div>
            <div class="zc-row zc-between" style="margin-top:6px"><b>Fibre</b><span>{{ $nutrition['fibre_g'] }} g</span></div>
            <div class="zc-row zc-between" style="margin-top:6px"><b>Sugars</b><span>{{ $nutrition['sugars_g'] }} g</span></div>
            @if (! empty($recipe->extras))
                <div class="zc-sub" style="margin-top:10px">Vitamins and minerals</div>
                @foreach ($recipe->extras as $label => $value)
                    <div class="zc-row zc-between" style="margin-top:4px"><span>{{ $label }}</span><span class="zc-sub">{{ $value }}</span></div>
                @endforeach
            @endif
            <p class="zc-note" style="margin-top:10px">{{ config('coach.recipes.estimate_note') }}</p>
        </div>
    @endif

    <div class="zc-card" data-add-to-plan>
        <h3>Add to my plan</h3>
        <label class="zc-sub" for="zc-day">Day</label>
        <select id="zc-day" wire:model="day" style="margin:6px 0 12px" data-day>
            @foreach ($days as $i => $label)
                <option value="{{ $i }}">{{ $label }}</option>
            @endforeach
        </select>
        <button type="button" class="zc-btn" wire:click="addToPlan" wire:loading.attr="disabled" data-add>Add to plan</button>
        <button type="button" class="zc-btn ghost" style="margin-top:10px" wire:click="logNow" wire:loading.attr="disabled" data-log-now>Log as eaten now</button>
        <p class="zc-note" style="margin-top:8px">This replaces the {{ strtolower($recipe->slot) }} on that day.</p>
    </div>
</div>
