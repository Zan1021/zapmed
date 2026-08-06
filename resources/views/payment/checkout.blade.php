<x-app-layout>
    <x-slot name="header">Payment</x-slot>

    <div class="max-w-lg mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="text-center mb-6">
                <div class="w-14 h-14 bg-zapmed-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-zapmed-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-gray-900">Secure Payment</h2>
                <p class="text-sm text-gray-500 mt-1">You'll be redirected to PayFast to complete your payment.</p>
            </div>

            <!-- Order Summary -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-500">Appointment</span>
                    <span class="font-medium text-gray-900">{{ $appointment->reference }}</span>
                </div>
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-500">Doctor</span>
                    <span class="font-medium text-gray-900">Dr. {{ $appointment->doctor->last_name }}</span>
                </div>
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-500">Date</span>
                    <span class="font-medium text-gray-900">{{ $appointment->appointment_date->format('j M Y') }} at {{ substr($appointment->start_time, 0, 5) }}</span>
                </div>
                <div class="flex justify-between text-sm pt-2 border-t border-gray-200 mt-2">
                    <span class="font-semibold text-gray-900">Total</span>
                    <span class="font-bold text-zapmed-600 text-lg">{{ $payment->formatted_amount }}</span>
                </div>
            </div>

            <!-- PayFast Form (auto-submits) -->
            <form id="payfast-form" action="{{ $processUrl }}" method="POST">
                @foreach($paymentData as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach

                <button type="submit" class="w-full bg-zapmed-600 hover:bg-zapmed-700 text-white py-3.5 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                    Pay {{ $payment->formatted_amount }} with PayFast
                </button>
            </form>

            <p class="text-xs text-gray-400 text-center mt-4">
                Secured by PayFast. Your payment details are never stored on our servers.
            </p>

            <div class="text-center mt-4">
                <a href="{{ route('patient.book') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel and go back</a>
            </div>
        </div>
    </div>
</x-app-layout>
