<div>
    <div class="zc-top">
        <a href="{{ $canWrite ? route('coach.board') : route('doctor.patients') }}" class="zc-back" aria-label="Back">&#8592;</a>
        <div style="flex:1">
            <div class="zc-sub">{{ $patient->member_number }}</div>
            <h2>{{ $patient->first_name }} {{ $patient->last_name }}</h2>
        </div>
    </div>

    {{-- Safety flags --}}
    @foreach ($flags->where('status', 'open') as $f)
        <div class="zc-card" role="alert" style="background:var(--zc-alert-l);color:var(--zc-alert)" data-flag>
            <b>Safety flag</b> &middot; {{ $f->symptom ?: 'Self-report' }}@if ($f->score !== null) {{ $f->score }}/10 @endif
            <div class="zc-sub" style="color:inherit">{{ $f->created_at->setTimezone($tz)->format('D j M, H:i') }} &middot; {{ $f->source }}</div>
        </div>
    @endforeach

    {{-- Progress --}}
    <div class="zc-card" data-section="progress">
        <h3>Progress</h3>
        <div class="zc-grid2" style="margin-top:8px">
            <div><div class="zc-sub">Start</div><b>{{ \App\Services\Coach\CoachUnits::format($weight->start, $units) ?? '-' }}</b></div>
            <div><div class="zc-sub">Now</div><b>{{ \App\Services\Coach\CoachUnits::format($weight->current, $units) ?? '-' }}</b></div>
            <div><div class="zc-sub">Goal</div><b>{{ \App\Services\Coach\CoachUnits::format($weight->goal, $units) ?? 'not set' }}</b></div>
            <div><div class="zc-sub">To goal</div><b>{{ $weight->percentToGoal !== null ? $weight->percentToGoal.'%' : '-' }}</b></div>
            <div><div class="zc-sub">Days logged</div><b>{{ $milestone->daysLogged }}</b></div>
            <div><div class="zc-sub">Check-ins</div><b>{{ $milestone->checkIns }}</b></div>
        </div>
        @if ($weight->bmi !== null)<div class="zc-sub" style="margin-top:8px">BMI {{ $weight->bmi }}. {{ config('coach.disclaimers.bmi') }}</div>@endif
    </div>

    {{-- Goals and profile --}}
    @if ($profile)
        <div class="zc-card" data-section="profile">
            <h3>Goals and lifestyle</h3>
            <div class="zc-sub" style="margin-top:6px">
                Target {{ $profile->target_months ? $profile->target_months.' months' : 'not set' }}
                &middot; Diet {{ $profileOptions['diets'][$profile->diet] ?? 'not set' }}
                &middot; Activity {{ $profileOptions['activity_levels'][$profile->activity_level] ?? 'not set' }}<br>
                Allergies {{ collect((array) $profile->allergies)->map(fn ($k) => $profileOptions['allergies'][$k] ?? $k)->implode(', ') ?: 'none listed' }}<br>
                Avoids {{ collect((array) $profile->avoids)->map(fn ($k) => $profileOptions['avoids'][$k] ?? $k)->implode(', ') ?: 'none listed' }}<br>
                Health concerns {{ collect((array) $profile->health_concerns)->map(fn ($k) => $profileOptions['health_concerns'][$k] ?? $k)->implode(', ') ?: 'none listed' }}
            </div>
        </div>
    @endif

    {{-- Weight history --}}
    <div class="zc-card" data-section="weight">
        <h3>Weight</h3>
        @forelse ($weightHistory as $w)
            <div class="zc-row zc-between" style="padding:6px 0;border-bottom:1px solid var(--zc-line)">
                <span>{{ $w->logged_on->format('D j M') }}</span><b>{{ \App\Services\Coach\CoachUnits::format((float) $w->weight_kg, $units) }}</b>
            </div>
        @empty
            <p class="zc-sub">No weigh-ins yet.</p>
        @endforelse
    </div>

    {{-- Dose history --}}
    <div class="zc-card" data-section="doses">
        <h3>Dose log</h3>
        @forelse ($doses as $d)
            <div class="zc-row zc-between" style="padding:6px 0;border-bottom:1px solid var(--zc-line)">
                <span>{{ $d->taken_at->setTimezone($tz)->format('D j M') }} &middot; {{ $d->site }}</span><span class="zc-sub">pain {{ $d->pain }}/10</span>
            </div>
        @empty
            <p class="zc-sub">No doses logged yet.</p>
        @endforelse
    </div>

    {{-- Side effects --}}
    <div class="zc-card" data-section="side-effects">
        <h3>Side effects (last {{ config('coach.board.side_effect_days') }} days)</h3>
        @forelse ($sideEffects as $s)
            <div class="zc-row zc-between" style="padding:6px 0;border-bottom:1px solid var(--zc-line)">
                <span>{{ $s->logged_on->format('D j M') }} &middot; {{ $s->symptom }}</span>
                <span class="zc-tag {{ $s->flagged ? 'red' : '' }}">{{ $s->score }}/10</span>
            </div>
        @empty
            <p class="zc-sub">Nothing logged.</p>
        @endforelse
    </div>

    {{-- Check-ins --}}
    <div class="zc-card" data-section="checkins">
        <h3>Check-ins</h3>
        @forelse ($calls as $c)
            <div class="zc-row zc-between" style="padding:6px 0;border-bottom:1px solid var(--zc-line)">
                <span>{{ $calls_svc->when($c) }}@if ($c->purpose) <span class="zc-tag">{{ str_replace('_', ' ', $c->purpose) }}</span>@endif</span>
                <span class="zc-sub">{{ $c->length_minutes }} min &middot; {{ $c->starts_at->isPast() ? 'done' : 'upcoming' }}</span>
            </div>
        @empty
            <p class="zc-sub">No calls yet.</p>
        @endforelse
        @if ($canWrite)<a href="{{ route('coach.inbox.thread', $patient) }}" class="zc-chip" style="margin-top:8px" data-link="thread">Open messages</a>@endif
    </div>

    {{-- Notes --}}
    <div class="zc-card" data-section="notes">
        <h3>Notes</h3>
        <p class="zc-sub">Private notes are visible to you only. Shared notes are visible to {{ $canWrite ? 'you and' : 'the coach and' }} the treating doctor.</p>

        @if ($canWrite)
            @if ($error)<div role="alert" data-error style="background:var(--zc-alert-l);color:var(--zc-alert);border-radius:14px;padding:10px 12px;margin:8px 0">{{ $error }}</div>@endif
            <textarea wire:model="draft" rows="3" maxlength="{{ $max }}" aria-label="Note" placeholder="Write a note"
                      style="width:100%;border-radius:14px;border:1.5px solid var(--zc-line);padding:10px;font:inherit;margin:8px 0"></textarea>
            <div class="zc-row" style="gap:8px;margin-bottom:8px">
                <button type="button" class="zc-chip {{ $visibility === 'private' ? 'on' : '' }}" wire:click="$set('visibility','private')" data-visibility="private">Private</button>
                <button type="button" class="zc-chip {{ $visibility === 'shared' ? 'on' : '' }}" wire:click="$set('visibility','shared')" data-visibility="shared">Shared with doctor</button>
                <button type="button" class="zc-btn sm" wire:click="addNote" data-action="add-note">Save note</button>
            </div>
        @endif

        @forelse ($notes as $n)
            <div style="padding:8px 0;border-top:1px solid var(--zc-line)" data-note="{{ $n->visibility }}">
                <div class="zc-row zc-between">
                    <span class="zc-tag {{ $n->visibility === 'shared' ? 'ok' : '' }}">{{ $n->visibility === 'shared' ? 'Shared' : 'Private' }}</span>
                    <span class="zc-sub">{{ $n->author?->first_name }} &middot; {{ $n->created_at->setTimezone($tz)->format('j M, H:i') }}</span>
                </div>
                <div style="white-space:pre-wrap;margin-top:4px">{{ $n->body }}</div>
                @if ($n->author_id === $me->id)
                    <button type="button" class="zc-chip" style="margin-top:6px" wire:click="deleteNote({{ $n->id }})" wire:confirm="Delete this note?" data-action="delete-note">Delete</button>
                @endif
            </div>
        @empty
            <p class="zc-sub" data-empty="notes">No notes yet.</p>
        @endforelse
    </div>

    <p class="zc-disc">Health information. Do not share. Never advise a member to change their dose.</p>
</div>
