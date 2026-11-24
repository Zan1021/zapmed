{{-- Bottom tab bar. Tabs whose screens are not built yet are marked "soon" (greyed, not clickable).
     The Coach tab opens the coach menu (a bottom sheet) instead of a page. --}}
@php
    $unread = app(\App\Services\Coach\CoachMessaging::class)->unreadForPatient(auth()->user());
    $tabs = [
        ['Track', 'coach.today', '<path d="M3 12h4l3-8 4 16 3-8h4"/>'],
        ['Plans', 'coach.plan', '<rect x="4" y="5" width="16" height="15" rx="3"/><path d="M4 10h16M9 3v4M15 3v4"/>'],
        ['Insight', null, '<path d="M12 3a6 6 0 0 0-4 10.5V17h8v-3.5A6 6 0 0 0 12 3zM9 20h6"/>'],
        ['Coach', 'sheet', '<path d="M4 5h16v11H9l-5 4z"/>'],
        ['Profile', 'coach.profile', '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>'],
    ];
@endphp
<nav class="zc-tabs" aria-label="Coach app">
    @foreach ($tabs as [$label, $route, $icon])
        @if ($route === 'sheet')
            <button type="button" class="zc-tab" style="position:relative" @click="sheet = 'coach'" data-tab="coach" aria-haspopup="dialog">
                <svg viewBox="0 0 24 24">{!! $icon !!}</svg>{{ $label }}
                @if ($unread)<span data-badge style="position:absolute;top:2px;right:calc(50% - 20px);min-width:16px;height:16px;border-radius:99px;background:var(--zc-alert);color:#fff;font-size:10px;line-height:16px;text-align:center">{{ $unread }}</span>@endif
            </button>
        @elseif ($route)
            <a href="{{ route($route) }}" class="zc-tab {{ ($route === 'coach.today' ? request()->routeIs('coach.today', 'coach.weight*', 'coach.medication*', 'coach.side-effects*', 'coach.water*') : ($route === 'coach.plan' ? request()->routeIs('coach.plan*', 'coach.recipe*') : request()->routeIs($route))) ? 'on' : '' }}">
                <svg viewBox="0 0 24 24">{!! $icon !!}</svg>{{ $label }}
            </a>
        @else
            <span class="zc-tab soon" aria-disabled="true"><svg viewBox="0 0 24 24">{!! $icon !!}</svg>{{ $label }}</span>
        @endif
    @endforeach
</nav>

{{-- Coach menu --}}
<div x-show="sheet === 'coach'" x-cloak @keydown.escape.window="sheet = null">
    <div class="zc-sheetbg" @click="sheet = null"></div>
    <div class="zc-sheet" role="dialog" aria-label="Coach menu">
        @livewire('coach.coach-sheet')
    </div>
</div>