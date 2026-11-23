<div wire:poll.15s>
    <div class="zc-top">
        <a href="{{ route('coach.inbox') }}" class="zc-back" aria-label="Back to inbox">&#8592;</a>
        <div style="flex:1"><h2><a href="{{ route('coach.board.patient', $patient) }}" data-link="member">{{ $patient->first_name }} {{ $patient->last_name }}</a></h2><div class="zc-sub">{{ $patient->member_number }}</div></div>
    </div>

    <div data-thread style="margin:12px 0">
        @foreach ($messages as $m)
            <div style="display:flex;justify-content:{{ $m->from === 'coach' ? 'flex-end' : 'flex-start' }};margin-bottom:8px" data-message="{{ $m->from }}">
                <div style="max-width:85%;padding:10px 12px;border-radius:16px;background:{{ $m->from === 'coach' ? 'var(--zc-forest)' : 'var(--zc-white)' }};color:{{ $m->from === 'coach' ? '#fff' : 'inherit' }};white-space:pre-wrap;word-break:break-word">{{ $m->body }}<div style="font-size:11px;opacity:.7;margin-top:4px">{{ $m->created_at->setTimezone($tz)->format('D H:i') }}</div></div>
            </div>
        @endforeach
    </div>

    @if ($error)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)">{{ $error }}</div>
    @endif
    <textarea wire:model="draft" rows="3" maxlength="{{ $max }}" placeholder="Write a reply" aria-label="Your reply"
              style="width:100%;border-radius:16px;border:1.5px solid var(--zc-line);padding:12px;font-family:inherit;font-size:15px;margin-bottom:10px"></textarea>
    <button type="button" class="zc-btn" wire:click="reply" wire:loading.attr="disabled">Send reply</button>
    <p class="zc-note" style="margin-top:8px">If a member describes something urgent, tell a doctor straight away. Do not wait for the next message.</p>
</div>