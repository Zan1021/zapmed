<x-app-layout>
    <x-slot name="header">Subscription Cancelled</x-slot>

    <div class="max-w-md mx-auto text-center">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>

            <h3 class="text-xl font-bold text-gray-900 mb-2">Payment Cancelled</h3>
            <p class="text-sm text-gray-500 mb-6">No charges were made. You can try again whenever you're ready.</p>

            <a href="{{ route('patient.subscription') }}"
                class="inline-flex items-center px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                Back to Plans
            </a>
        </div>
    </div>
</x-app-layout>
