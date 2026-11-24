<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.plan')])
        <h2>Recipes</h2>
    </div>

    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search recipes" aria-label="Search recipes" style="margin-bottom:10px" data-search>

    <div class="zc-row" style="margin-bottom:10px;gap:8px">
        <button type="button" class="zc-chip {{ $tab === 'all' ? 'on' : '' }}" wire:click="setTab('all')" data-tab="all">All</button>
        <button type="button" class="zc-chip {{ $tab === 'favourites' ? 'on' : '' }}" wire:click="setTab('favourites')" data-tab="favourites">Favourites ({{ $favouriteCount }})</button>
    </div>

    <div class="zc-pinknote" data-fav-note>Heart the meals you love. Your meal plan is built around them.</div>

    @forelse ($rows as $label => $recipes)
        <div class="zc-row zc-between" style="margin:6px 0"><h3>{{ $label }}</h3></div>
        <div class="zc-hscroll" data-row="{{ $label }}">
            @foreach ($recipes as $r)
                @php $fav = in_array($r->id, $favouriteIds, true); @endphp
                <div class="zc-rcard" data-recipe="{{ $r->id }}">
                    <a href="{{ route('coach.recipe', $r) }}" aria-label="{{ $r->name }}">
                        <div class="zc-tile" style="background:{{ $r->colour ?: '#DCE5DA' }}">@if ($r->photoUrl())<img src="{{ $r->photoUrl() }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover" data-photo>@else{{ $r->emoji }}@endif</div>
                        <div class="zc-rbody">
                            <b>{{ $r->name }}</b>
                            <span class="zc-sub">{{ $r->kcal }} kcal &middot; {{ rtrim(rtrim(number_format($r->protein_g, 1), '0'), '.') }} g protein</span>
                            <span class="zc-sub" style="display:block">{{ $r->minutes }} min</span>
                        </div>
                    </a>
                    <button type="button" class="zc-heart {{ $fav ? 'on' : '' }}" wire:click="toggleFavourite({{ $r->id }})" aria-pressed="{{ $fav ? 'true' : 'false' }}" aria-label="{{ $fav ? 'Remove from favourites' : 'Add to favourites' }}" data-heart="{{ $r->id }}">{{ $fav ? '♥' : '♡' }}</button>
                </div>
            @endforeach
        </div>
    @empty
        <div class="zc-card" data-empty>
            @if ($tab === 'favourites' && trim($search) === '')
                <p>No favourites yet. Tap the heart on a recipe you love.</p>
            @elseif (trim($search) !== '')
                <p>No recipes match "{{ $search }}".</p>
            @else
                <p>No recipes are available yet. Check back soon.</p>
            @endif
        </div>
    @endforelse

    @if ($favouriteCount > 0)
        <button type="button" class="zc-btn" style="margin-top:12px" wire:click="buildPlan" wire:loading.attr="disabled" data-build-plan>
            Build my plan around my {{ $favouriteCount }} {{ $favouriteCount === 1 ? 'favourite' : 'favourites' }}
        </button>
    @endif

    <p class="zc-note" style="margin-top:12px">{{ config('coach.recipes.estimate_note') }}</p>
</div>
