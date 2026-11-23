<div wire:poll.15s>
    <div class="zc-top">
        @include('coach.partials.back', ['fallback' => route('coach.today')])
        <div style="flex:1"><h2>{{ $coachName }}</h2><div class="zc-sub">{{ $online ? config('coach.messaging.online_text') : config('coach.messaging.offline_text') }}</div></div>
    </div>

    <div class="zc-card" style="background:var(--zc-sage-l)" data-urgent><span class="zc-sub" style="color:var(--zc-forest)">{{ $hasCoach ? $urgent : $unassigned }}</span></div>

    <div data-thread style="margin:12px 0">
        @forelse ($messages as $m)
            <div style="display:flex;justify-content:{{ $m->from === 'patient' ? 'flex-end' : 'flex-start' }};margin-bottom:8px" data-message="{{ $m->from }}">
                <div style="max-width:85%;padding:10px 12px;border-radius:16px;background:{{ $m->from === 'patient' ? 'var(--zc-forest)' : 'var(--zc-white)' }};color:{{ $m->from === 'patient' ? '#fff' : 'inherit' }};white-space:pre-wrap;word-break:break-word">{{ $m->body }}<div style="font-size:11px;opacity:.7;margin-top:4px">{{ $m->created_at->setTimezone($tz)->format('D H:i') }}</div></div>
            </div>
        @empty
            @if ($hasCoach)<p class="zc-sub" data-empty="messages">No messages yet. Say hello to {{ $coachName }}.</p>@endif
        @endforelse
    </div>

    @if ($hasCoach)
        @if ($error)
            <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)">{{ $error }}</div>
        @endif
        <textarea wire:model="draft" rows="3" maxlength="{{ $max }}" placeholder="Type your message" aria-label="Your message"
                  style="width:100%;border-radius:16px;border:1.5px solid var(--zc-line);padding:12px;font-family:inherit;font-size:15px;margin-bottom:10px"></textarea>
        <button type="button" class="zc-btn" wire:click="send" wire:loading.attr="disabled">Send</button>
    @endif
</div>