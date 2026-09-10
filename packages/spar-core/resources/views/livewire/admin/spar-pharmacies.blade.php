<div>
    <x-slot name="header">SPAR Pharmacies</x-slot>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Search + Add -->
    <div class="flex items-center justify-between mb-6">
        <div class="relative w-64">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search pharmacies..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-green-500 focus:border-green-500" />
            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <button wire:click="create" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
            + Add Pharmacy
        </button>
    </div>

    <!-- Pharmacy Form Modal -->
    @if($showForm)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="resetForm">
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit' : 'Add' }} Pharmacy</h3>
                </div>
                <form wire:submit="save" class="p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Group *</label>
                            <select wire:model="group_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500">
                                <option value="">— select group —</option>
                                @foreach($groups as $g)
                                    <option value="{{ $g->id }}">{{ $g->name }}</option>
                                @endforeach
                            </select>
                            @error('group_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pharmacy Name *</label>
                            <input type="text" wire:model="name" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                            @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SPAR Store ID *</label>
                            <input type="text" wire:model="spar_store_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                            @error('spar_store_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">BHF Code</label>
                            <input type="text" wire:model="bhf_code" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="text" wire:model="phone" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" wire:model="email" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <input type="text" wire:model="city" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Province</label>
                            <input type="text" wire:model="province" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <input type="text" wire:model="address" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                        </div>
                    </div>

                    <div class="flex items-center gap-6 pt-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="supports_delivery" class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
                            <span>Supports Delivery</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
                            <span>Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" wire:click="resetForm" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Pharmacies Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-3 font-medium text-gray-600">Pharmacy</th>
                        <th class="text-left p-3 font-medium text-gray-600">Group</th>
                        <th class="text-left p-3 font-medium text-gray-600">Location</th>
                        <th class="text-center p-3 font-medium text-gray-600">Patients</th>
                        <th class="text-center p-3 font-medium text-gray-600">Orders</th>
                        <th class="text-center p-3 font-medium text-gray-600">Delivery</th>
                        <th class="text-center p-3 font-medium text-gray-600">Status</th>
                        <th class="text-center p-3 font-medium text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pharmacies as $pharmacy)
                        <tr class="hover:bg-gray-50">
                            <td class="p-3">
                                <p class="font-medium text-gray-900">{{ $pharmacy->name }}</p>
                                <p class="text-xs text-gray-500">ID: {{ $pharmacy->spar_store_id }}</p>
                            </td>
                            <td class="p-3 text-gray-600">{{ $pharmacy->group?->name ?? '-' }}</td>
                            <td class="p-3 text-gray-600">{{ $pharmacy->city ?? '-' }}, {{ $pharmacy->province ?? '-' }}</td>
                            <td class="p-3 text-center">{{ $pharmacy->patients_count }}</td>
                            <td class="p-3 text-center">{{ $pharmacy->orders_count }}</td>
                            <td class="p-3 text-center">
                                @if($pharmacy->supports_delivery)
                                    <span class="text-green-600">Yes</span>
                                @else
                                    <span class="text-gray-400">No</span>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                <button wire:click="toggleActive({{ $pharmacy->id }})" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium cursor-pointer
                                    {{ $pharmacy->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $pharmacy->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="p-3 text-center">
                                <button wire:click="edit({{ $pharmacy->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-gray-500">No pharmacies yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">
            {{ $pharmacies->links() }}
        </div>
    </div>
</div>
