<div>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.medication')])
        <h2>Log dose</h2>
    </div>

    <p class="zc-sub">Where did you inject?@if ($nextSite) The star shows the next site in your rotation.@endif</p>

    <div class="zc-grid2" style="margin-top:8px">
        @foreach ($sites as $s)
            <button type="button" wire:click="chooseSite('{{ $s }}')" data-site="{{ $s }}"
                    class="zc-opt {{ $site === $s ? 'on' : '' }}" style="text-align:center" aria-pressed="{{ $site === $s ? 'true' : 'false' }}">
                @if ($nextSite === $s)<span aria-label="Next in rotation">&#9733;</span> @endif{{ $s }}
            </button>
        @endforeach
    </div>

    <div class="zc-card" style="margin-top:12px">
        <b>Pain level</b>
        <div class="zc-row zc-between zc-sub"><span>None</span><b style="color:var(--zc-forest)" data-pain>{{ $pain }}</b><span>Severe</span></div>
        <input type="range" min="0" max="{{ $painMax }}" step="1" wire:model.live="pain" aria-label="Pain level 0 to {{ $painMax }}">
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)">{{ $error }}</div>
    @endif

    <button type="button" class="zc-btn" wire:click="save" wire:loading.attr="disabled" @disabled(! $site)>Save dose</button>
    <p class="zc-note" style="margin-top:8px">Saving sets your last dose to today. {{ $noAdvice }}</p>
</div>