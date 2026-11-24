@props(['active' => 'orders'])

@php
    $tabs = [
        'orders' => ['label' => 'My Orders', 'route' => 'patient.orders'],
        'health' => ['label' => 'Health Stats', 'route' => 'patient.health'],
        'follow-up' => ['label' => 'Follow-Up', 'route' => 'patient.follow-up'],
        'notifications' => ['label' => 'Notifications', 'route' => 'patient.notifications'],
        'rewards' => ['label' => 'Rewards', 'route' => 'patient.rewards'],
        'community' => ['label' => 'Community', 'route' => 'patient.community'],
    ];
@endphp

<nav class="mb-6 flex flex-wrap gap-2 border-b border-gray-200 pb-3 text-sm" aria-label="My Zapmed" data-patient-tabs>
    @foreach($tabs as $key => $tab)
        @if(\Illuminate\Support\Facades\Route::has($tab['route']))
            <a href="{{ route($tab['route']) }}" data-tab="{{ $key }}"
               class="px-3 py-1.5 rounded-lg {{ $active === $key ? 'bg-zapmed-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">{{ $tab['label'] }}</a>
        @endif
    @endforeach
</nav>
