<div>
    @if (! $saved)
        <div class="zc-top">
            @include('coach.partials.back', ['fallback' => route('coach.today')])
            <h2>How are you feeling?</h2>
        </div>
        <p class="zc-sub">Score each symptom from 0 (none) to 10 (severe). Tap &times; to hide one you don't want to track.</p>

        <div x-data="{
                scores: {},
                abd: @js($abdominal), abdLevel: {{ $abdominalLevel }}, anyLevel: {{ $anyLevel }},
                v(n) { return Number(this.scores[n] ?? 0) },
                red(n) { return this.v(n) >= this.anyLevel || (n === this.abd && this.v(n) >= this.abdLevel) },
                any() { return Object.values(this.scores).some(s => Number(s) > 0) }
             }">
            <div style="margin-top:8px">
                @foreach ($symptoms as $name)
                    <div class="zc-card" style="padding:12px" data-symptom="{{ $name }}" wire:key="sx-{{ $name }}">
                        <div class="zc-row zc-between">
                            <b>{{ $name }}</b>
                            <span class="zc-row">
                                <b data-score :style="red(@js($name)) ? 'color:var(--zc-alert)' : ''" x-text="v(@js($name))">0</b>
                                <button type="button" class="zc-chip" wire:click="hide(@js($name))" aria-label="Hide {{ $name }}">&times;</button>
                            </span>
                        </div>
                        <input type="range" min="0" max="10" step="1" value="0" aria-label="{{ $name }} score"
                               x-model.number="scores[@js($name)]"
                               :style="red(@js($name)) ? 'accent-color:var(--zc-alert)' : ''">
                    </div>
                @endforeach
            </div>

            <div class="zc-row" style="margin:6px 0 14px">
                <input type="text" wire:model="newName" wire:keydown.enter="add" placeholder="Add your own symptom" maxlength="60" aria-label="Add your own symptom">
                <button type="button" class="zc-btn sm" wire:click="add">Add</button>
            </div>

            @if ($hiddenCount)
                <button type="button" class="zc-chip" style="margin-bottom:12px" wire:click="restore">Restore hidden symptoms</button>
            @endif

            @if ($error)
                <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)">{{ $error }}</div>
            @endif

            <button type="button" class="zc-btn" :disabled="!any()" @click="$wire.save(scores)" wire:loading.attr="disabled" data-save>Save</button>
        </div>
    @elseif ($flagged)
        <div class="zc-top"><h2>Saved</h2></div>
        <div class="zc-card" role="alert" data-state="flagged" style="background:var(--zc-alert-l);box-shadow:inset 0 0 0 2px var(--zc-alert)">
            <div style="font-size:34px" aria-hidden="true">&#9888;</div>
            <h2 style="color:var(--zc-alert);margin:6px 0">{{ $copy['flag_title'] }}</h2>
            <p class="zc-sub" style="color:var(--zc-forest)">{{ $copy['flag_body'] }}</p>
            <div style="margin-top:6px">
                @foreach ($flagged as $name => $score)<span class="zc-tag red">{{ $name }} {{ $score }}/10</span> @endforeach
            </div>
        </div>

        @if ($doctorReached)
            <div class="zc-card" data-state="reached" style="background:var(--zc-ok-l)">{{ $copy['flag_confirmed'] }}</div>
        @else
            <button type="button" class="zc-btn red" wire:click="reachDoctor" wire:loading.attr="disabled">Reach Dr Anke now</button>
        @endif

        <div class="zc-card" style="margin-top:12px"><b>{{ $copy['emergency'] }}</b></div>
        <p class="zc-note" style="margin-top:10px">{{ $copy['follow_up'] }}</p>
        <a href="{{ route('coach.today') }}" class="zc-btn ghost" style="margin-top:10px">Back to tracker</a>
    @else
        <div class="zc-top"><h2>Saved</h2></div>
        <div class="zc-card" data-state="normal" style="text-align:center">
            <div style="font-size:40px;color:var(--zc-ok)" aria-hidden="true">&#10003;</div>
            <h2 style="margin:6px 0">{{ $copy['normal_title'] }}</h2>
        </div>
        <div class="zc-card"><h3>{{ $copy['common_title'] }}</h3><p class="zc-sub">{{ $copy['common_body'] }}</p></div>
        <div class="zc-card">
            <h3>{{ $copy['manage_title'] }}</h3>
            <p class="zc-sub">{{ $copy['manage_body'] }}</p>
            @if ($luteal)<p class="zc-sub" data-luteal>{{ $copy['luteal_line'] }}</p>@endif
        </div>
        <p class="zc-note">{{ $copy['follow_up'] }}</p>
        <a href="{{ route('coach.today') }}" class="zc-btn">Back to tracker</a>
    @endif
</div>