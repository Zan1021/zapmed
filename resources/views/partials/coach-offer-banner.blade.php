@php($__coachOffer = auth()->check() && auth()->user()->role === \App\Enums\UserRole::Patient && app(\App\Services\Coach\CoachAddon::class)->offeredByDoctor(auth()->user()))
@if($__coachOffer)
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-teal-200 bg-teal-50 p-4" data-coach-offer-banner>
        <p class="text-sm text-teal-900">Your doctor recommended a health coach for you. A registered dietitian will support you between consultations.</p>
        <a href="{{ route('coach.join') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800" data-coach-offer-link>See the coach plan</a>
    </div>
@endif