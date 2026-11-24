<x-app-layout>
    <x-slot name="header">Booking link</x-slot>

    <div class="mx-auto max-w-lg rounded-xl border border-gray-200 bg-white p-8 text-center" data-section="booking-link-problem">
        <p class="text-base text-gray-900">{{ $message }}</p>
        <p class="mt-2 text-sm text-gray-500">You can still book from your account.</p>
        <a href="{{ route('patient.follow-up') }}" class="mt-4 inline-block rounded-lg bg-zapmed-600 px-5 py-2 text-sm text-white">Go to my follow-ups</a>
    </div>
</x-app-layout>
