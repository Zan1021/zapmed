@php
    $enabledLanguages = \App\Models\Setting::enabledLanguages();
    $availableLanguages = config('languages.available', []);
    $currentLocale = app()->getLocale();
@endphp

@if(count($enabledLanguages) > 1)
<div x-data="{ open: false }" class="relative">
    <button @click="open = !open" @click.away="open = false"
        class="flex items-center gap-1.5 text-sm text-gray-600 hover:text-gray-900 transition-colors px-2 py-1 rounded-lg hover:bg-gray-100">
        <span>{{ $availableLanguages[$currentLocale]['flag'] ?? '' }}</span>
        <span class="hidden sm:inline text-xs font-medium">{{ $availableLanguages[$currentLocale]['native'] ?? 'English' }}</span>
        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>

    <div x-show="open" x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50"
        x-cloak>
        @foreach($enabledLanguages as $code)
            @if(isset($availableLanguages[$code]))
                <a href="?lang={{ $code }}"
                    class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 {{ $currentLocale === $code ? 'font-medium text-zapmed-600 bg-zapmed-50' : 'text-gray-700' }}">
                    <span>{{ $availableLanguages[$code]['flag'] }}</span>
                    <span>{{ $availableLanguages[$code]['native'] }}</span>
                    @if($currentLocale === $code)
                        <svg class="w-3.5 h-3.5 ml-auto text-zapmed-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    @endif
                </a>
            @endif
        @endforeach
    </div>
</div>
@endif
