<div>
    <x-slot name="header">Subscription Plans</x-slot>

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-sm text-green-700">{{ session('message') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Active Plans</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->stats['active_plans'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Active Subscribers</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->stats['active_subscriptions'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">MRR</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">R{{ number_format($this->stats['mrr'] / 100, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center justify-center">
            <button wire:click="createPlan" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">
                + New Plan
            </button>
        </div>
    </div>

    <!-- Plan Form Modal -->
    @if($showForm)
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            {{ $editingPlanId ? 'Edit Plan' : 'Create New Plan' }}
        </h3>

        <form wire:submit="savePlan" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plan Name *</label>
                    <input wire:model="name" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="e.g. Essential">
                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Price (Rands) *</label>
                    <input wire:model="price" type="number" step="0.01" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="299.00">
                    @error('price') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <input wire:model="description" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="Brief plan description">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle</label>
                    <select wire:model="billing_cycle" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        <option value="monthly">Monthly</option>
                        <option value="annually">Annually</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cycle Frequency (months)</label>
                    <input wire:model="cycle_frequency" type="number" min="1" max="12" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Consultations/Month (0=unlimited)</label>
                    <input wire:model="consultations_per_month" type="number" min="0" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                </div>
            </div>

            <!-- Toggles -->
            <div class="flex flex-wrap gap-6">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input wire:model="includes_chronic_renewals" type="checkbox" class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                    <span class="text-sm text-gray-700">Chronic Renewals</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input wire:model="includes_priority_booking" type="checkbox" class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                    <span class="text-sm text-gray-700">Priority Booking</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input wire:model="includes_messaging" type="checkbox" class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                    <span class="text-sm text-gray-700">Messaging</span>
                </label>
            </div>

            <!-- Features list -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Features (one per line)</label>
                <textarea wire:model="features_text" rows="3" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="Free follow-ups&#10;24/7 messaging&#10;Priority support"></textarea>
            </div>

            <div class="flex space-x-3">
                <button type="submit" class="px-5 py-2.5 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">
                    {{ $editingPlanId ? 'Update Plan' : 'Create Plan' }}
                </button>
                <button type="button" wire:click="cancelForm" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
    @endif

    <!-- Plans List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cycle</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Consults</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subscribers</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($plans as $plan)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900">{{ $plan->name }}</p>
                            <p class="text-xs text-gray-500">{{ $plan->slug }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-semibold text-gray-900">{{ $plan->formatted_price }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $plan->billing_label }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $plan->consultations_per_month === 0 ? 'Unlimited' : $plan->consultations_per_month . '/mo' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $plan->subscriptions()->active()->count() }}
                        </td>
                        <td class="px-6 py-4">
                            @if($plan->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button wire:click="editPlan({{ $plan->id }})" class="text-xs font-medium text-zapmed-600 hover:text-zapmed-800">Edit</button>
                                <button wire:click="togglePlanStatus({{ $plan->id }})" class="text-xs font-medium {{ $plan->is_active ? 'text-amber-600 hover:text-amber-800' : 'text-green-600 hover:text-green-800' }}">
                                    {{ $plan->is_active ? 'Disable' : 'Enable' }}
                                </button>
                                <button wire:click="deletePlan({{ $plan->id }})" wire:confirm="Delete this plan?" class="text-xs font-medium text-red-600 hover:text-red-800">Delete</button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-400">
                            No plans created yet. Click "New Plan" to get started.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
