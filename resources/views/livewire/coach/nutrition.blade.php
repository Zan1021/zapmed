<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.today')])
        <h2>Nutrition</h2>
    </div>

    <div class="zc-card" data-totals>
        <div class="zc-row zc-between">
            <div><div class="zc-big" data-kcal>{{ number_format($totals['kcal']) }}</div><div class="zc-sub">of {{ number_format($targets['kcal']) }} kcal today</div></div>
            <div style="text-align:right"><b data-fat>{{ rtrim(rtrim(number_format($totals['fat_g'], 1), '0'), '.') }} g</b><div class="zc-sub">fat (goal {{ $targets['fat_g'] }} g)</div></div>
        </div>
        @foreach ([['Protein', $totals['protein_g'], $targets['protein_g']], ['Fibre', $totals['fibre_g'], $targets['fibre_g']], ['Carbs', $totals['carbs_g'], $targets['carbs_g']]] as [$label, $value, $goal])
            <div style="margin-top:12px">
                <div class="zc-row zc-between zc-sub"><span>{{ $label }}</span><span data-total="{{ strtolower($label) }}">{{ rtrim(rtrim(number_format($value, 1), '0'), '.') }} / {{ $goal }} g</span></div>
                <div class="zc-bar"><i style="width:{{ $goal > 0 ? min(100, round($value / $goal * 100)) : 0 }}%"></i></div>
            </div>
        @endforeach
        <p class="zc-note" style="margin-top:10px">Estimates. Goals are a starting point that Amy and your doctor adjust.</p>
    </div>

    @foreach ($slots as $slot => $label)
        <div class="zc-card" data-slot="{{ $slot }}">
            <div class="zc-row zc-between">
                <h3>{{ $label }}</h3>
                <a href="{{ route('coach.nutrition.log', ['slot' => $slot]) }}" class="zc-btn sm" style="width:40px;padding:0" aria-label="Log {{ strtolower($label) }}" data-add="{{ $slot }}">+</a>
            </div>
            @forelse ($entries->get($slot, collect()) as $e)
                <div class="zc-meal" data-entry="{{ $e->id }}">
                    <div class="zc-mbody">
                        <b>{{ $e->name }}</b>
                        <small>{{ $e->kcal }} kcal &middot; {{ rtrim(rtrim(number_format($e->protein_g, 1), '0'), '.') }} g protein @if ($e->servings > 1)&middot; {{ $e->servings }} servings @endif</small>
                    </div>
                    <button type="button" class="zc-chip" wire:click="remove({{ $e->id }})" aria-label="Remove {{ $e->name }}" data-remove="{{ $e->id }}">Remove</button>
                </div>
            @empty
                <div class="zc-sub" style="margin-top:6px">Nothing logged yet.</div>
            @endforelse
        </div>
    @endforeach
</div>
