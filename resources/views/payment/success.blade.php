<x-app-layout>
    <x-slot name="header">Payment Successful</x-slot>

    <div class="max-w-lg mx-auto text-center">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
            <div class="w-16 h-16 bg-zapmed-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-zapmed-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Payment Successful!</h2>
            <p class="text-gray-500 mt-2">Your appointment has been confirmed.</p>

            @if($payment)
            <div class="mt-6 p-4 bg-gray-50 rounded-lg text-left">
                <div class="flex items-center justify-between text-sm mb-2">
                    <span class="text-gray-500">Payment Reference</span>
                    <span class="font-mono font-semibold text-gray-900">{{ $payment->reference }}</span>
                </div>
                <div class="flex items-center justify-between text-sm mb-2">
                    <span class="text-gray-500">Amount Paid</span>
                    <span class="font-medium text-gray-900">{{ $payment->formatted_amount }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Status</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Confirmed</span>
                </div>
            </div>
            @endif

            <div class="mt-6 flex flex-col sm:flex-row gap-3">
                <a href="{{ route('patient.appointments') }}" class="flex-1 bg-zapmed-600 hover:bg-zapmed-700 text-white py-2.5 rounded-lg text-sm font-semibold transition-colors text-center">
                    View My Appointments
                </a>
                <a href="{{ route('dashboard') }}" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 py-2.5 rounded-lg text-sm font-medium transition-colors text-center">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
