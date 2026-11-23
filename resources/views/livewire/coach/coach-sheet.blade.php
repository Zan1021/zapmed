<div data-coach-sheet>
    <h2 style="font-size:22px;margin-bottom:4px">How can we help?</h2>
    <p class="zc-sub" style="margin:0 0 12px">
        {{ $coachName }} is <b style="color: {{ $online ? 'var(--zc-ok)' : 'var(--zc-muted)' }}" data-online>{{ $online ? $onlineText : $offlineText }}</b>
    </p>

    <span class="zc-opt" style="opacity:.55" aria-disabled="true" data-option="quick">&#10024; Quick question<small>Coming soon</small></span>

    @if ($hasCoach)
        <a href="{{ route('coach.messages') }}" class="zc-opt" data-option="message">
            &#128172; Message {{ $coachName }}@if ($unread) <span class="zc-tag red" data-unread>{{ $unread }} new</span>@endif
            <small>Replies within one working day</small>
        </a>
    @else
        <span class="zc-opt" style="opacity:.55" aria-disabled="true" data-option="message">&#128172; Message your coach<small>Your coach will be assigned soon</small></span>
    @endif

    @if ($hasCoach)
        <a href="{{ route('coach.book') }}" class="zc-opt" data-option="call">&#128222; Book a call<small>15 or 30 minutes</small></a>
    @else
        <span class="zc-opt" style="opacity:.55" aria-disabled="true" data-option="call">&#128222; Book a call<small>Your coach will be assigned soon</small></span>
    @endif

    @if ($doctorReached)
        <div class="zc-card" data-state="reached" style="background:var(--zc-ok-l)">
            {{ config('coach.side_effect_copy.flag_confirmed') }}
            <div style="margin-top:8px"><b>{{ $emergency }}</b></div>
        </div>
    @else
        <button type="button" class="zc-opt" style="background:var(--zc-alert-l);box-shadow:inset 0 0 0 1.5px var(--zc-alert);color:var(--zc-alert)"
                wire:click="feelsWrong" wire:loading.attr="disabled" data-option="wrong">
            &#9888; Something feels wrong<small style="color:var(--zc-alert)">Straight to a doctor, no queue</small>
        </button>
    @endif
</div>