{{-- Bottom tab bar. Tabs whose screens are not built yet are marked "soon" (greyed, not clickable). --}}
@php
    $tabs = [
        ['Track', 'coach.today', '<path d="M3 12h4l3-8 4 16 3-8h4"/>'],
        ['Plans', null, '<rect x="4" y="5" width="16" height="15" rx="3"/><path d="M4 10h16M9 3v4M15 3v4"/>'],
        ['Insight', null, '<path d="M12 3a6 6 0 0 0-4 10.5V17h8v-3.5A6 6 0 0 0 12 3zM9 20h6"/>'],
        ['Coach', null, '<path d="M4 5h16v11H9l-5 4z"/>'],
        ['Profile', null, '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>'],
    ];
@endphp
<nav class="zc-tabs" aria-label="Coach app">
    @foreach ($tabs as [$label, $route, $icon])
        @if ($route)
            <a href="{{ route($route) }}" class="zc-tab {{ ($route === 'coach.today' ? request()->routeIs('coach.today', 'coach.weight*', 'coach.medication*', 'coach.side-effects*') : request()->routeIs($route)) ? 'on' : '' }}">
                <svg viewBox="0 0 24 24">{!! $icon !!}</svg>{{ $label }}
            </a>
        @else
            <span class="zc-tab soon" aria-disabled="true"><svg viewBox="0 0 24 24">{!! $icon !!}</svg>{{ $label }}</span>
        @endif
    @endforeach
</nav>