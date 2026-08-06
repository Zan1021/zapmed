<x-app-layout>
    <x-slot name="header">Payment Cancelled</x-slot>

    <div class="max-w-lg mx-auto text-center">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Payment Cancelled</h2>
            <p class="text-gray-500 mt-2">Your payment was not processed. No charges were made.</p>

            <div class="mt-6 flex flex-col sm:flex-row gap-3">
                <a href="{{ route('patient.book') }}" class="flex-1 bg-zapmed-600 hover:bg-zapmed-700 text-white py-2.5 rounded-lg text-sm font-semibold transition-colors text-center">
                    Try Again
                </a>
                <a href="{{ route('dashboard') }}" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 py-2.5 rounded-lg text-sm font-medium transition-colors text-center">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
