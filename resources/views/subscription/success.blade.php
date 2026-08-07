<x-app-layout>
    <x-slot name="header">Subscription Activated</x-slot>

    <div class="max-w-md mx-auto text-center">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>

            <h3 class="text-xl font-bold text-gray-900 mb-2">You're All Set!</h3>

            @if($subscription)
                <p class="text-sm text-gray-500 mb-6">
                    Your <span class="font-medium text-gray-700">{{ $subscription->plan->name }}</span> subscription is being activated. You'll receive full access shortly.
                </p>
            @else
                <p class="text-sm text-gray-500 mb-6">Your subscription payment has been received.</p>
            @endif

            <a href="{{ route('patient.subscription') }}"
                class="inline-flex items-center px-5 py-2.5 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">
                View My Subscription
            </a>
        </div>
    </div>
</x-app-layout>
