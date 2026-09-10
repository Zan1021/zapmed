<div>
    <x-slot name="header">Staff & Admins</x-slot>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Staff accounts</h2>
        <button wire:click="create" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
            + Add Staff
        </button>
    </div>

    @if($showForm)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="resetForm">
            <div class="bg-white rounded-xl shadow-xl max-w-xl w-full max-h-[80vh] overflow-y-auto">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit' : 'Add' }} Staff</h3>
                </div>
                <form wire:submit="save" class="p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input type="text" wire:model="name" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
                            @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" wire:model="email" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
                            @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $editingId ? 'New password (blank = keep)' : 'Password *' }}</label>
                            <input type="password" wire:model="password" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" />
                            @error('password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                            <select wire:model="role" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                @foreach($roles as $r)
                                    <option value="{{ $r }}">{{ ucwords(str_replace('_', ' ', $r)) }}</option>
                                @endforeach
                            </select>
                            @error('role') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Group</label>
                            <select wire:model="group_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                <option value="">—</option>
                                @foreach($groups as $g)
                                    <option value="{{ $g->id }}">{{ $g->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pharmacy</label>
                            <select wire:model="spar_pharmacy_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                <option value="">—</option>
                                @foreach($pharmacies as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="pt-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-green-600" />
                            <span>Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" wire:click="resetForm" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" class="px-6 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">Save</button>
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
                        <th class="text-left p-3 font-medium text-gray-600">Name</th>
                        <th class="text-left p-3 font-medium text-gray-600">Email</th>
                        <th class="text-left p-3 font-medium text-gray-600">Role</th>
                        <th class="text-center p-3 font-medium text-gray-600">Status</th>
                        <th class="text-center p-3 font-medium text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($staff as $member)
                        <tr class="hover:bg-gray-50">
                            <td class="p-3 font-medium text-gray-900">{{ $member->name }}</td>
                            <td class="p-3 text-gray-600">{{ $member->email }}</td>
                            <td class="p-3 text-gray-600">{{ ucwords(str_replace('_', ' ', $member->role)) }}</td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $member->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $member->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <button wire:click="edit({{ $member->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-gray-500">No staff yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $staff->links() }}</div>
    </div>
</div>
