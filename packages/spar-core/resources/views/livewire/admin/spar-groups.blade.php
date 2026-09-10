<div>
    <x-slot name="header">Pharmacy Groups</x-slot>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div class="relative w-64">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search groups..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-green-500 focus:border-green-500" />
            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <button wire:click="create" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
            + Add Group
        </button>
    </div>

    @if($showForm)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="resetForm">
            <div class="bg-white rounded-xl shadow-xl max-w-xl w-full max-h-[80vh] overflow-y-auto">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit' : 'Add' }} Group</h3>
                </div>
                <form wire:submit="save" class="p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Group Name *</label>
                            <input type="text" wire:model="name" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                            @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                            <input type="text" wire:model="region" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Name</label>
                            <input type="text" wire:model="contact_name" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                            <input type="email" wire:model="contact_email" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                            @error('contact_email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Phone</label>
                            <input type="text" wire:model="contact_phone" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500" />
                        </div>
                    </div>

                    <div class="pt-2">
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

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-3 font-medium text-gray-600">Group</th>
                        <th class="text-left p-3 font-medium text-gray-600">Region</th>
                        <th class="text-left p-3 font-medium text-gray-600">Contact</th>
                        <th class="text-center p-3 font-medium text-gray-600">Pharmacies</th>
                        <th class="text-center p-3 font-medium text-gray-600">Status</th>
                        <th class="text-center p-3 font-medium text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($groups as $group)
                        <tr class="hover:bg-gray-50">
                            <td class="p-3">
                                <p class="font-medium text-gray-900">{{ $group->name }}</p>
                                <p class="text-xs text-gray-500">{{ $group->slug }}</p>
                            </td>
                            <td class="p-3 text-gray-600">{{ $group->region ?? '-' }}</td>
                            <td class="p-3 text-gray-600">
                                {{ $group->contact_name ?? '-' }}
                                @if($group->contact_email)<p class="text-xs text-gray-400">{{ $group->contact_email }}</p>@endif
                            </td>
                            <td class="p-3 text-center">{{ $group->pharmacies_count }}</td>
                            <td class="p-3 text-center">
                                <button wire:click="toggleActive({{ $group->id }})" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium cursor-pointer
                                    {{ $group->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $group->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="p-3 text-center">
                                <button wire:click="edit({{ $group->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500">No groups yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">
            {{ $groups->links() }}
        </div>
    </div>
</div>
