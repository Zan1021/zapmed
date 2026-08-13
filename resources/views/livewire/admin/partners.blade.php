<div>
    <x-slot name="header">Partner Management</x-slot>

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Affiliate Partners</h2>
        <button wire:click="create" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">
            + Add Partner
        </button>
    </div>

    <!-- Create/Edit Form Modal -->
    @if($showForm)
    <div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ $editingId ? 'Edit Partner' : 'New Partner' }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="text-xs font-medium text-gray-600">Business Name *</label>
                <input wire:model.live="name" type="text" class="w-full mt-1 rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Slug (ref ID) *</label>
                <input wire:model="slug" type="text" class="w-full mt-1 rounded-lg border-gray-300 text-sm font-mono focus:border-zapmed-500 focus:ring-zapmed-500">
                @error('slug') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Website URL</label>
                <input wire:model="website_url" type="url" class="w-full mt-1 rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Contact Name *</label>
                <input wire:model="contact_name" type="text" class="w-full mt-1 rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Contact Email *</label>
                <input wire:model="contact_email" type="email" class="w-full mt-1 rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Contact Phone</label>
                <input wire:model="contact_phone" type="text" class="w-full mt-1 rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Consultation Commission %</label>
                <input wire:model="commission_consultation" type="number" min="0" max="50" class="w-full mt-1 rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Medication Commission %</label>
                <input wire:model="commission_medication" type="number" min="0" max="50" class="w-full mt-1 rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Cookie Days</label>
                <input wire:model="cookie_days" type="number" min="1" max="365" class="w-full mt-1 rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Status</label>
                <select wire:model="status" class="w-full mt-1 rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
        </div>
        <div class="flex gap-3 mt-4">
            <button wire:click="save" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg">Save</button>
            <button wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
        </div>
    </div>
    @endif

    <!-- Partners Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Partner</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Slug</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Referrals</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Commission %</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500">Earned</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Status</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($partners as $partner)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-900">{{ $partner->name }}</p>
                        <p class="text-xs text-gray-500">{{ $partner->contact_email }}</p>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $partner->slug }}</td>
                    <td class="px-4 py-3 text-center">{{ $partner->referrals_count }}</td>
                    <td class="px-4 py-3 text-center text-xs">{{ $partner->commission_consultation }}% / {{ $partner->commission_medication }}%</td>
                    <td class="px-4 py-3 text-right font-medium">R{{ number_format(($partner->commissions_sum_commission_amount ?? 0) / 100, 2) }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $partner->status === 'active' ? 'bg-green-100 text-green-700' : ($partner->status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                            {{ ucfirst($partner->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button wire:click="edit({{ $partner->id }})" class="text-xs text-zapmed-600 hover:text-zapmed-700 font-medium mr-2">Edit</button>
                        <button wire:click="toggleStatus({{ $partner->id }})" class="text-xs text-gray-500 hover:text-gray-700">
                            {{ $partner->status === 'active' ? 'Suspend' : 'Activate' }}
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">No partners yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3 border-t border-gray-100">
            {{ $partners->links() }}
        </div>
    </div>
</div>
