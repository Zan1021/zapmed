@props([
    'title',
    'eyebrow' => null,   // small context line above the title (e.g. "SPAR Group")
    'subtitle' => null,  // optional supporting line under the title
])

{{-- Shared "you are here" page header so every SPAR screen announces its
     location consistently (Craig demo feedback — Theme 7). Title is required;
     eyebrow/subtitle optional; the default slot is an actions area (buttons)
     rendered on the right. --}}
@php $sparPrimary = config('spar.branding.primary_color', '#006B3F'); @endphp
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
    <div>
        @if($eyebrow)
            <p class="text-xs font-semibold uppercase tracking-wide" style="color: {{ $sparPrimary }};">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>

    @if(! $slot->isEmpty())
        <div class="flex flex-wrap items-center gap-3">
            {{ $slot }}
        </div>
    @endif
</div>
