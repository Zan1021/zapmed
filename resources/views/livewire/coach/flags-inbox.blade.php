<div>
    <div class="zc-top"><h2>Coach safety flags</h2></div>
    <p class="zc-sub">Open flags from the coach app, oldest first. Review the member, then mark the flag as handled.</p>

    @forelse ($flags as $flag)
        <div class="zc-card" data-flag="{{ $flag->id }}">
            <div class="zc-row zc-between">
                <b>{{ $flag->patient->first_name }} {{ $flag->patient->last_name }}</b>
                <span class="zc-tag red">{{ $flag->source === 'self_report' ? 'Member asked for a doctor' : 'Symptom level reached' }}</span>
            </div>
            <div class="zc-sub">{{ $flag->patient->member_number }} &middot; {{ $flag->created_at->setTimezone($tz)->format('D j M, H:i') }}</div>
            @if ($flag->symptom)
                <div style="margin:6px 0"><b>{{ $flag->symptom }}</b> {{ $flag->score }}/10</div>
            @endif
            <div class="zc-sub">{{ $flag->patient->phone }}</div>
            <button type="button" class="zc-btn sm" style="margin-top:8px" wire:click="handled({{ $flag->id }})">Mark as handled</button>
        </div>
    @empty
        <div class="zc-card" data-empty="flags"><h3>No open flags</h3><p class="zc-sub">Nothing needs review right now.</p></div>
    @endforelse
</div>