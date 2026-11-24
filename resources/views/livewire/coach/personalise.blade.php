<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.plan')])
        <h2>Personalise plan</h2>
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)" data-error>{{ $error }}</div>
    @endif

    <div class="zc-card" data-section="diet">
        <h3>Diet</h3>
        <div class="zc-row" style="flex-wrap:wrap;gap:8px;margin-top:8px">
            @foreach ($diets as $key => $label)
                <button type="button" class="zc-chip {{ $diet === $key ? 'on' : '' }}" wire:click="setDiet('{{ $key }}')" aria-pressed="{{ $diet === $key ? 'true' : 'false' }}" data-diet="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="zc-card" data-section="allergies">
        <h3>Allergies</h3>
        <div class="zc-row" style="flex-wrap:wrap;gap:8px;margin-top:8px">
            @foreach ($allergyOptions as $key => $label)
                <button type="button" class="zc-chip {{ in_array($key, $allergies, true) ? 'on' : '' }}" wire:click="toggle('allergies','{{ $key }}')" aria-pressed="{{ in_array($key, $allergies, true) ? 'true' : 'false' }}" data-allergy="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="zc-card" data-section="avoids">
        <h3>Avoid</h3>
        <div class="zc-row" style="flex-wrap:wrap;gap:8px;margin-top:8px">
            @foreach ($avoidOptions as $key => $label)
                <button type="button" class="zc-chip {{ in_array($key, $avoids, true) ? 'on' : '' }}" wire:click="toggle('avoids','{{ $key }}')" aria-pressed="{{ in_array($key, $avoids, true) ? 'true' : 'false' }}" data-avoid="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <button type="button" class="zc-btn" wire:click="save" wire:loading.attr="disabled" data-save>Save and rebuild plan</button>
    <p class="zc-note" style="margin-top:8px">Meals that do not suit your choices are removed from your plan, even locked ones.</p>
</div>
