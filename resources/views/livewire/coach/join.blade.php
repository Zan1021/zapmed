<div>
    <x-slot name="header">Your Zapmed health coach</x-slot>

    @if(session('message'))
        <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg" data-join-message>
            <p class="text-sm text-amber-700">{{ session('message') }}</p>
        </div>
    @endif

    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" data-coach-join>
            @if($offeredByDoctor)
                <div class="mb-4 p-3 bg-zapmed-50 rounded-lg text-sm text-zapmed-800" data-doctor-offer>
                    Your doctor recommended the health coach for you. It starts once your first payment goes through.
                </div>
            @endif

            @if($plan)
                <h3 class="text-lg font-semibold text-gray-900">{{ $plan->name }}</h3>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $plan->formatted_price }} <span class="text-sm font-normal text-gray-500">{{ $plan->billing_label }}</span></p>

                <ul class="mt-4 space-y-2 text-sm text-gray-700">
                    <li>A registered dietitian as your own health coach</li>
                    <li>Weight, dose, water and side-effect tracking in the coach app</li>
                    <li>Message your coach and book check-in calls</li>
                    <li>Your doctor can see your progress at your consultations</li>
                </ul>

                <button type="button" wire:click="join" wire:loading.attr="disabled" data-join-button
                    class="mt-6 w-full py-3 bg-zapmed-600 hover:bg-zapmed-700 text-white font-semibold rounded-lg transition-colors shadow-sm">
                    Pay with PayFast
                </button>
                <p class="mt-3 text-xs text-gray-500 text-center">Billed every month. You can cancel any time. Your repeat prescriptions do not stop if you cancel the coach.</p>
            @else
                <p class="text-sm text-gray-600">The health coach is not available right now. Please contact support.</p>
            @endif
        </div>
    </div>
</div>
