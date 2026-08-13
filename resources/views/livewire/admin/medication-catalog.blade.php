<div>
    <x-slot name="header">Medication Catalog</x-slot>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <p class="text-sm text-gray-500">Manage your NAPPI-coded medication database.</p>
        </div>
        <button wire:click="create" class="inline-flex items-center bg-zapmed-600 hover:bg-zapmed-700 text-white px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Add Medication
        </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name, generic, brand, or NAPPI..."
            class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
        <select wire:model.live="categoryFilter" class="rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
            <option value="">All Categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat }}">{{ ucfirst(str_replace('-', ' ', $cat)) }}</option>
            @endforeach
        </select>
        <select wire:model.live="formFilter" class="rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
            <option value="">All Forms</option>
            @foreach($forms as $f)
                <option value="{{ $f }}">{{ ucfirst($f) }}</option>
            @endforeach
        </select>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Medication</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">NAPPI</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Form / Strength</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Repeat</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($medications as $med)
                    <tr class="hover:bg-gray-50 transition-colors {{ !$med->is_active ? 'opacity-50' : '' }}">
                        <td class="px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $med->name }}</p>
                                @if($med->brand_name)
                                    <p class="text-xs text-gray-500">{{ $med->brand_name }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ $med->nappi_code ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ ucfirst($med->form) }} · {{ $med->strength }}</td>
                        <td class="px-4 py-3">
                            @if($med->category)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-zapmed-50 text-zapmed-700">
                                    {{ ucfirst(str_replace('-', ' ', $med->category)) }}
                                </span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $med->formatted_price }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            {{ $med->repeat_cycle_text }}
                            @if($med->is_subscription)
                                <span class="ml-1 text-zapmed-600">●</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <button wire:click="toggleActive({{ $med->id }})"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-colors
                                {{ $med->is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200' }}">
                                {{ $med->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="edit({{ $med->id }})" class="text-xs font-medium text-zapmed-600 hover:text-zapmed-800 mr-3">Edit</button>
                            <button wire:click="delete({{ $med->id }})" wire:confirm="Delete {{ $med->name }}? This cannot be undone."
                                class="text-xs font-medium text-red-600 hover:text-red-800">Delete</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">No medications found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-gray-100">
            {{ $medications->links() }}
        </div>
    </div>

    <!-- Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data x-init="document.body.classList.add('overflow-hidden')" x-on:remove="document.body.classList.remove('overflow-hidden')">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity" wire:click="$set('showModal', false)"></div>

            <div class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 z-10">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $editingId ? 'Edit Medication' : 'Add New Medication' }}</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Medication Name *</label>
                        <input wire:model="name" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="e.g. Semaglutide">
                        @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Generic Name</label>
                        <input wire:model="generic_name" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Brand Name</label>
                        <input wire:model="brand_name" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Form *</label>
                        <select wire:model="form" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                            <option value="tablet">Tablet</option>
                            <option value="capsule">Capsule</option>
                            <option value="injection">Injection</option>
                            <option value="cream">Cream</option>
                            <option value="gel">Gel</option>
                            <option value="liquid">Liquid</option>
                            <option value="drops">Drops</option>
                            <option value="inhaler">Inhaler</option>
                            <option value="patch">Patch</option>
                            <option value="suppository">Suppository</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Strength *</label>
                        <input wire:model="strength" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="e.g. 50mg">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Schedule</label>
                        <select wire:model="schedule" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                            <option value="">None</option>
                            <option value="S0">S0 (OTC)</option>
                            <option value="S1">S1</option>
                            <option value="S2">S2</option>
                            <option value="S3">S3</option>
                            <option value="S4">S4 (Prescription)</option>
                            <option value="S5">S5</option>
                            <option value="S6">S6</option>
                            <option value="S7">S7</option>
                            <option value="S8">S8</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">NAPPI Code</label>
                        <input wire:model="nappi_code" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="e.g. 720631001">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Category</label>
                        <select wire:model="category" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                            <option value="">None</option>
                            <option value="weight-loss">Weight Loss</option>
                            <option value="skincare">Skincare</option>
                            <option value="womens-health">Women's Health</option>
                            <option value="mens-health">Men's Health</option>
                            <option value="sexual-health">Sexual Health</option>
                            <option value="general-health">General Health</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Manufacturer</label>
                        <input wire:model="manufacturer" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Price (cents)</label>
                        <input wire:model="price_cents" type="number" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="e.g. 12100 = R121.00">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Repeat Cycle (days)</label>
                        <input wire:model="repeat_cycle_days" type="number" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="30 = monthly">
                    </div>

                    <div class="md:col-span-2 flex items-center gap-6">
                        <label class="inline-flex items-center cursor-pointer">
                            <input wire:model="is_subscription" type="checkbox" class="rounded text-zapmed-600 focus:ring-zapmed-500 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">Subscription medication (ongoing repeat)</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer">
                            <input wire:model="is_active" type="checkbox" class="rounded text-zapmed-600 focus:ring-zapmed-500 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                        <textarea wire:model="description" rows="2" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="Patient-facing description"></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Dosage Instructions</label>
                        <textarea wire:model="dosage_instructions" rows="2" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="e.g. Take 1 tablet daily with food"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button wire:click="save" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white rounded-lg text-sm font-semibold transition-colors">
                        {{ $editingId ? 'Update' : 'Create' }} Medication
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
