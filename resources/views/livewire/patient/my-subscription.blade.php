<div>
    <x-slot name="header">My Subscription</x-slot>

    @if(session('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-sm text-green-700">{{ session('message') }}</p>
        </div>
    @endif

    @if($subscription && $subscription->isUsable())
        <!-- Active Subscription -->
        <div class="max-w-2xl">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Plan Header -->
                <div class="bg-gradient-to-r from-zapmed-600 to-zapmed-700 p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-bold">{{ $subscription->plan->name }}</h3>
                            <p class="text-zapmed-100 text-sm mt-1">{{ $subscription->plan->description }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold">{{ $subscription->plan->formatted_price }}</p>
                            <p class="text-sm text-zapmed-200">{{ $subscription->plan->billing_label }}</p>
                        </div>
                    </div>
                </div>

                <!-- Subscription Details -->
                <div class="p-6 space-y-4">
                    <!-- Status -->
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Status</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $subscription->status_color }}-100 text-{{ $subscription->status_color }}-700">
                            {{ ucfirst($subscription->status) }}
                        </span>
                    </div>

                    @if($subscription->onGracePeriod())
                        <div class="p-3 bg-amber-50 rounded-lg border border-amber-200">
                            <p class="text-sm text-amber-700">
                                Your subscription is cancelled but active until <span class="font-medium">{{ $subscription->ends_at->format('j M Y') }}</span>.
                            </p>
                        </div>
                    @endif

                    <!-- Billing Period -->
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Current Period</span>
                        <span class="text-sm text-gray-700">
                            {{ $subscription->current_period_start?->format('j M') }} - {{ $subscription->current_period_end?->format('j M Y') }}
                        </span>
                    </div>

                    <!-- Next Billing -->
                    @if($subscription->isActive() && $subscription->next_billing_date)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Next Billing Date</span>
                        <span class="text-sm text-gray-700">{{ $subscription->next_billing_date->format('j M Y') }}</span>
                    </div>
                    @endif

                    <!-- Consultations Used -->
                    @if($subscription->plan->consultations_per_month > 0)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Consultations Used</span>
                        <span class="text-sm text-gray-700">
                            {{ $subscription->consultations_used_this_period }} / {{ $subscription->plan->consultations_per_month }}
                        </span>
                    </div>
                    @endif

                    <!-- Total Paid -->
                    <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                        <span class="text-sm text-gray-500">Total Paid</span>
                        <span class="text-sm font-semibold text-gray-700">R{{ number_format($subscription->total_paid / 100, 2) }}</span>
                    </div>

                    <!-- Features -->
                    @if($subscription->plan->features)
                    <div class="border-t border-gray-100 pt-4">
                        <p class="text-xs font-medium text-gray-500 uppercase mb-2">Plan Features</p>
                        <ul class="space-y-1.5">
                            @foreach($subscription->plan->features as $feature)
                            <li class="flex items-center text-sm text-gray-700">
                                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                {{ $feature }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>

                <!-- Cancel -->
                @if($subscription->isActive())
                <div class="px-6 pb-6">
                    @if($confirmingCancel)
                        <div class="p-4 bg-red-50 rounded-lg border border-red-200">
                            <p class="text-sm text-red-700 mb-3">Are you sure? You'll still have access until the end of your current billing period.</p>
                            <div class="mb-3">
                                <input wire:model="cancellationReason" type="text" placeholder="Reason (optional)"
                                    class="w-full text-sm rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500">
                            </div>
                            <div class="flex space-x-3">
                                <button wire:click="cancelSubscription"
                                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                                    Yes, Cancel Subscription
                                </button>
                                <button wire:click="$set('confirmingCancel', false)"
                                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                                    Keep Subscription
                                </button>
                            </div>
                        </div>
                    @else
                        <button wire:click="$set('confirmingCancel', true)"
                            class="text-sm text-red-600 hover:text-red-800 font-medium">
                            Cancel Subscription
                        </button>
                    @endif
                </div>
                @endif
            </div>
        </div>
    @else
        <!-- No Active Subscription — Show Plans -->
        <div class="mb-8">
            <h2 class="text-xl font-bold text-gray-900">Choose a Plan</h2>
            <p class="text-sm text-gray-500 mt-1">Subscribe for ongoing access to consultations and chronic medication renewals.</p>
        </div>

        @if($this->plans->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($this->plans as $plan)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900">{{ $plan->name }}</h3>
                    @if($plan->description)
                        <p class="text-sm text-gray-500 mt-1">{{ $plan->description }}</p>
                    @endif

                    <div class="mt-4">
                        <span class="text-3xl font-bold text-gray-900">{{ $plan->formatted_price }}</span>
                        <span class="text-sm text-gray-500">{{ $plan->billing_label }}</span>
                    </div>

                    <!-- Features -->
                    <ul class="mt-6 space-y-2">
                        @if($plan->consultations_per_month > 0)
                        <li class="flex items-center text-sm text-gray-700">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            {{ $plan->consultations_per_month }} consultations/month
                        </li>
                        @else
                        <li class="flex items-center text-sm text-gray-700">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Unlimited consultations
                        </li>
                        @endif

                        @if($plan->includes_chronic_renewals)
                        <li class="flex items-center text-sm text-gray-700">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Chronic medication renewals
                        </li>
                        @endif

                        @if($plan->includes_priority_booking)
                        <li class="flex items-center text-sm text-gray-700">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Priority booking
                        </li>
                        @endif

                        @if($plan->includes_messaging)
                        <li class="flex items-center text-sm text-gray-700">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Secure messaging
                        </li>
                        @endif

                        @if($plan->features)
                            @foreach($plan->features as $feature)
                            <li class="flex items-center text-sm text-gray-700">
                                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                {{ $feature }}
                            </li>
                            @endforeach
                        @endif
                    </ul>
                </div>

                <div class="px-6 pb-6">
                    <button wire:click="subscribe({{ $plan->id }})"
                        class="w-full py-2.5 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                        Subscribe
                    </button>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-12 bg-white rounded-xl border border-gray-100">
            <p class="text-gray-500">No subscription plans available at the moment.</p>
        </div>
        @endif
    @endif
</div>
