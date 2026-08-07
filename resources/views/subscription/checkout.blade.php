<x-app-layout>
    <x-slot name="header">Subscribe to {{ $planName }}</x-slot>

    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center">
            <div class="w-14 h-14 bg-zapmed-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-zapmed-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>

            <h3 class="text-lg font-semibold text-gray-900 mb-2">Complete Your Subscription</h3>
            <p class="text-sm text-gray-500 mb-6">You'll be redirected to PayFast to set up your recurring payment.</p>

            <form action="{{ $processUrl }}" method="POST">
                @foreach($paymentData as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach

                <button type="submit"
                    class="w-full py-3 bg-zapmed-600 hover:bg-zapmed-700 text-white font-semibold rounded-lg transition-colors shadow-sm">
                    Pay with PayFast
                </button>
            </form>

            <a href="{{ route('patient.subscription') }}" class="inline-block mt-4 text-sm text-gray-500 hover:text-gray-700">
                Cancel
            </a>
        </div>

        <p class="mt-4 text-xs text-gray-400 text-center">
            Your card will be charged automatically each billing cycle. You can cancel at any time from your subscription page.
        </p>
    </div>
</x-app-layout>
