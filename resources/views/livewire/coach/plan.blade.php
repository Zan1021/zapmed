<div>
    <div class="zc-top">
        <h2>Meal plan</h2>
        <button type="button" class="zc-chip on" wire:click="openTemplate" data-template-chip>{{ $templateLabel }} &#9662;</button>
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)" data-error>{{ $error }}</div>
    @endif

    <div class="zc-days" role="tablist" aria-label="Plan days">
        @foreach ($days as $i => $d)
            <button type="button" role="tab" class="zc-day {{ $i === (int) $day ? 'on' : '' }} {{ $d['today'] ? 'today' : '' }}" wire:click="selectDay({{ $i }})" aria-selected="{{ $i === (int) $day ? 'true' : 'false' }}" data-day="{{ $i }}">
                {{ $d['dow'] }}<b>{{ $d['num'] }}</b>
            </button>
        @endforeach
    </div>

    {{-- Targets for the day --}}
    <div class="zc-card" data-targets>
        <div class="zc-row zc-between">
            <b>{{ $days[(int) $day]['label'] }}</b>
            @if ($totals['tag'])
                <span class="zc-tag {{ $totals['tag'] === 'On target' ? 'ok' : 'sage' }}" data-target-tag>{{ $totals['tag'] }}</span>
            @endif
        </div>
        @php
            $bars = [
                ['Calories', $totals['kcal'], $targets['kcal'], 'kcal'],
                ['Protein', $totals['protein_g'], $targets['protein_g'], 'g'],
                ['Fibre', $totals['fibre_g'], $targets['fibre_g'], 'g'],
            ];
        @endphp
        @foreach ($bars as [$label, $value, $goal, $unit])
            <div style="margin-top:10px">
                <div class="zc-row zc-between zc-sub"><span>{{ $label }}</span><span data-total="{{ strtolower($label) }}">{{ rtrim(rtrim(number_format($value, 1), '0'), '.') }} / {{ number_format($goal) }} {{ $unit }}</span></div>
                <div class="zc-bar"><i style="width:{{ $goal > 0 ? min(100, round($value / $goal * 100)) : 0 }}%"></i></div>
            </div>
        @endforeach
    </div>

    {{-- The four slots --}}
    <div class="zc-card" data-slots>
        @foreach ($slots as $slot => $label)
            @php $item = $items->get($slot); @endphp
            <div class="zc-meal" data-slot="{{ $slot }}">
                @if ($item)
                    <button type="button" class="zc-eat {{ $item->eaten_at ? 'on' : '' }}" wire:click="toggleEaten('{{ $slot }}')" aria-pressed="{{ $item->eaten_at ? 'true' : 'false' }}" aria-label="Mark {{ $item->recipe->name }} as eaten" data-eat="{{ $slot }}">{{ $item->eaten_at ? '✓' : '' }}</button>
                    <button type="button" class="zc-mbody" wire:click="openMeal('{{ $slot }}')" data-meal="{{ $slot }}">
                        <span class="zc-slot">{{ $label }}</span>
                        <b style="display:block">{{ $item->recipe->name }}</b>
                        <small>
                            {{ $item->recipe->nutrition($item->servings)['kcal'] }} kcal &middot; {{ rtrim(rtrim(number_format($item->recipe->nutrition($item->servings)['protein_g'], 1), '0'), '.') }} g protein
                            @if ($item->servings > 1) &middot; {{ $item->servings }} servings @endif
                        </small>
                        @if ($item->is_new)<span class="zc-tag" data-tag="new">new for you</span>@endif
                        @if ($item->protein_boost)<span class="zc-tag sage" data-tag="boost">protein boost</span>@endif
                        @if ($item->locked)<span class="zc-tag ok" data-tag="locked">locked</span>@endif
                    </button>
                @else
                    <span class="zc-eat" style="visibility:hidden"></span>
                    <div class="zc-mbody"><span class="zc-slot">{{ $label }}</span><small>Nothing planned</small></div>
                    <button type="button" class="zc-btn sm ghost" wire:click="openMeal('{{ $slot }}')" data-add="{{ $slot }}">+ Add</button>
                @endif
            </div>
        @endforeach
        @if ($items->isEmpty())
            <p class="zc-note" style="margin-top:8px" data-empty>No meals could be planned for this day. Add one, or check back when more recipes are available.</p>
        @endif
    </div>

    <div class="zc-row" style="gap:10px">
        <button type="button" class="zc-btn ghost" wire:click="regenerate" wire:loading.attr="disabled" data-regenerate>Regenerate</button>
        <a href="{{ route('coach.plan.personalise') }}" class="zc-btn ghost" data-personalise>Personalise</a>
    </div>
    <a href="{{ route('coach.recipes') }}" class="zc-btn" style="margin-top:10px" data-recipes-link>Browse recipes</a>
    <p class="zc-note" style="margin-top:10px">Nutrition values are estimates. Tap a meal to swap, trade, lock, remove or favourite it.</p>

    {{-- Sheets --}}
    @if ($sheet)
        <div>
            <div class="zc-sheetbg" wire:click="closeSheet"></div>
            <div class="zc-sheet" role="dialog" aria-label="Meal plan" data-sheet="{{ $sheet }}">
                @if ($sheet === 'template')
                    <h2 style="font-size:20px;margin-bottom:10px">Plan template</h2>
                    @foreach ($templates as $key => $t)
                        <button type="button" class="zc-opt {{ $plan->template === $key ? 'on' : '' }}" wire:click="chooseTemplate('{{ $key }}')" data-template="{{ $key }}">{{ $t['label'] }}<small>{{ $t['blurb'] }}</small></button>
                    @endforeach
                    <p class="zc-note">Choosing a template rebuilds your plan. Locked meals stay.</p>

                @elseif ($sheet === 'meal' && $open)
                    <h2 style="font-size:20px">{{ $open->recipe->name }}</h2>
                    <div class="zc-sub" style="margin-bottom:10px">{{ $slots[$this->slot] }} &middot; {{ $days[(int) $day]['label'] }}</div>
                    <a href="{{ route('coach.recipe', $open->recipe) }}" class="zc-opt" data-act="view">View recipe</a>
                    <button type="button" class="zc-opt" wire:click="openSwap" data-act="swap">Swap for another meal</button>
                    <button type="button" class="zc-opt" wire:click="openTrade" data-act="trade">Trade with another day</button>
                    <button type="button" class="zc-opt" wire:click="toggleLock" data-act="lock">{{ $open->locked ? 'Unlock this meal' : 'Lock this meal' }}<small>Locked meals stay when you regenerate.</small></button>
                    <button type="button" class="zc-opt" wire:click="toggleFavourite" data-act="favourite">{{ $openIsFavourite ? 'Remove from favourites' : 'Add to favourites' }}</button>
                    <button type="button" class="zc-opt" wire:click="removeMeal" data-act="remove">Remove from plan</button>

                @elseif ($sheet === 'meal')
                    <h2 style="font-size:20px;margin-bottom:10px">Add {{ strtolower($slots[$this->slot] ?? '') }}</h2>
                    @forelse ($addOptions as $r)
                        <button type="button" class="zc-opt" wire:click="chooseMeal({{ $r->id }})" data-choose="{{ $r->id }}">{{ $r->emoji }} {{ $r->name }}<small>{{ $r->kcal }} kcal &middot; {{ rtrim(rtrim(number_format($r->protein_g, 1), '0'), '.') }} g protein</small></button>
                    @empty
                        <p>No suitable {{ strtolower($slots[$this->slot] ?? 'meal') }} recipes are available yet.</p>
                    @endforelse

                @elseif ($sheet === 'swap')
                    <h2 style="font-size:20px;margin-bottom:10px">Swap {{ strtolower($slots[$this->slot] ?? '') }}</h2>
                    @forelse ($options as $r)
                        <button type="button" class="zc-opt" wire:click="chooseMeal({{ $r->id }})" data-choose="{{ $r->id }}">{{ $r->emoji }} {{ $r->name }}<small>{{ $r->kcal }} kcal &middot; {{ rtrim(rtrim(number_format($r->protein_g, 1), '0'), '.') }} g protein</small></button>
                    @empty
                        <p>There is no other suitable {{ strtolower($slots[$this->slot] ?? 'meal') }} yet.</p>
                    @endforelse

                @elseif ($sheet === 'trade')
                    <h2 style="font-size:20px;margin-bottom:10px">Trade {{ strtolower($slots[$this->slot] ?? '') }} with</h2>
                    @foreach ($days as $i => $d)
                        @continue($i === (int) $day)
                        <button type="button" class="zc-opt" wire:click="tradeWith({{ $i }})" data-trade="{{ $i }}">{{ $d['label'] }}</button>
                    @endforeach
                @endif

                <button type="button" class="zc-btn ghost" style="margin-top:6px" wire:click="closeSheet" data-close>Close</button>
            </div>
        </div>
    @endif
</div>
