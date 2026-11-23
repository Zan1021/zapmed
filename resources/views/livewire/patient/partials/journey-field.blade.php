{{--
    One question's input, in Craig's final journey markup (26 Nov handoff).
    Vars: $q (flow question), $answers (live answers), $child (bool: sits under a parent on the same screen)
--}}
@php
    $id = $q['id'];
    $type = $q['type'] ?? 'text';
    $value = $answers[$id] ?? null;
    $opts = array_map(fn ($o) => is_array($o) ? ($o['label'] ?? $o['code'] ?? '') : $o, $q['options'] ?? []);
@endphp

@if($type === 'radio')
    @if($child)
        <fieldset class="zconsult">
            <legend>{{ $q['label'] }}</legend>
            @foreach($opts as $opt)
                <label wire:key="r-{{ $id }}-{{ $loop->index }}"><input type="radio" wire:model.live="answers.{{ $id }}" value="{{ $opt }}"> {{ $opt }}</label>
            @endforeach
        </fieldset>
    @else
        <div class="answers">
            @foreach($opts as $opt)
                <button type="button" wire:key="a-{{ $id }}-{{ $loop->index }}" class="answer {{ $value === $opt ? 'active' : '' }}"
                        wire:click="selectAnswer(@js($id), @js($opt))">{{ $opt }}</button>
            @endforeach
        </div>
    @endif

@elseif($type === 'checkbox')
    @if($child || !empty($q['_sub']))
        <p><b>{{ $q['label'] }}</b></p>
    @endif
    <div class="zchoices">
        @foreach($opts as $opt)
            <label class="zchoice" wire:key="c-{{ $id }}-{{ $loop->index }}">
                <input type="checkbox" wire:model.live="answers.{{ $id }}" value="{{ $opt }}"><span>{{ $opt }}</span>
            </label>
        @endforeach
    </div>

@elseif($type === 'textarea')
    @if($child && ($q['placeholder'] ?? null) !== ($q['label'] ?? null))
        <label>{{ $q['label'] }}</label>
    @endif
    <textarea wire:model="answers.{{ $id }}" placeholder="{{ $q['placeholder'] ?? 'Type your answer' }}"></textarea>

@elseif($type === 'text')
    @if($child)
        <label>{{ $q['label'] }}</label>
    @endif
    <input type="text" wire:model="answers.{{ $id }}" placeholder="{{ $q['placeholder'] ?? 'Type your answer' }}">

@elseif($type === 'medicines')
    @php $items = is_array($value) ? $value : ['']; @endphp
    @if(!empty($q['helper']))<p>{{ $q['helper'] }}</p>@endif
    @foreach($items as $i => $m)
        <div class="field" wire:key="m-{{ $id }}-{{ $i }}">
            <label>Medicine or supplement {{ $i + 1 }}</label>
            <input class="nzmed" type="text" wire:model="answers.{{ $id }}.{{ $i }}" placeholder="Name and dose, if known">
        </div>
    @endforeach
    <button type="button" class="smallbtn" wire:click="addMedicine(@js($id))">+ Add another</button>

@elseif($type === 'measurements')
    @php
        $w = (float) ($answers[$id.'_weight'] ?? 85); $h = (float) ($answers[$id.'_height'] ?? 170);
        $wFill = max(0, min(100, 100 * ($w - 30) / 220)); $hFill = max(0, min(100, 100 * ($h - 120) / 100));
    @endphp
    <div class="zmeasurement">
        <div class="field">
            <label for="w-{{ $id }}">Current weight</label>
            <div class="zweight-value"><output>{{ rtrim(rtrim(number_format($w, 1), '0'), '.') }} kg</output></div>
            <input id="w-{{ $id }}" class="zweight-slider" type="range" min="30" max="250" step="1" style="--fill:{{ $wFill }}%"
                   wire:model.live.debounce.150ms="answers.{{ $id }}_weight" aria-label="Current weight in kilograms">
            <div class="zrange-labels"><span>30 kg</span><span>250 kg</span></div>
            <label for="wn-{{ $id }}">Enter weight</label>
            <div class="zunit zcompact"><input id="wn-{{ $id }}" type="number" min="30" max="250" inputmode="decimal" wire:model.blur="answers.{{ $id }}_weight"><span>kg</span></div>
        </div>
        <div class="field">
            <label for="h-{{ $id }}">Current height</label>
            <div class="zweight-value"><output>{{ rtrim(rtrim(number_format($h, 1), '0'), '.') }} cm</output></div>
            <input id="h-{{ $id }}" class="zweight-slider" type="range" min="120" max="220" step="1" style="--fill:{{ $hFill }}%"
                   wire:model.live.debounce.150ms="answers.{{ $id }}_height" aria-label="Current height in centimetres">
            <div class="zrange-labels"><span>120 cm</span><span>220 cm</span></div>
            <label for="hn-{{ $id }}">Enter height</label>
            <div class="zunit zcompact"><input id="hn-{{ $id }}" type="number" min="120" max="220" inputmode="decimal" wire:model.blur="answers.{{ $id }}_height"><span>cm</span></div>
        </div>
    </div>

@elseif($type === 'number')
    <div class="znumber"><input type="number" wire:model="answers.{{ $id }}" placeholder="{{ $q['placeholder'] ?? '' }}" inputmode="numeric">@if(!empty($q['unit']))<span>{{ $q['unit'] }}</span>@endif</div>

@elseif($type === 'select')
    <select wire:model="answers.{{ $id }}">
        <option value="">{{ $q['placeholder'] ?? 'Select an option' }}</option>
        @foreach($opts as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
    </select>

@elseif($type === 'date')
    @php $months = ['January','February','March','April','May','June','July','August','September','October','November','December']; $years = range((int) date('Y'), (int) date('Y') - 80); @endphp
    <div class="zoption-grid">
        <select wire:model="answers.{{ $id }}_month"><option value="">Month</option>@foreach($months as $m)<option>{{ $m }}</option>@endforeach</select>
        <select wire:model="answers.{{ $id }}_year"><option value="">Year</option>@foreach($years as $y)<option>{{ $y }}</option>@endforeach</select>
    </div>

@elseif($type === 'stop')
    <div class="zhealth-note" role="alert"><p>{{ $q['label'] ?? '' }}</p></div>
@endif
