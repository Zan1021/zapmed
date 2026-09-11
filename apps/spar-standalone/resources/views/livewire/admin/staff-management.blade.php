<div>
    <x-slot name="header">Staff & Admins</x-slot>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-lg font-semibold text-gray-900">Staff accounts</h2>
        <button wire:click="create" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition self-start sm:self-auto">
            + Add Staff
        </button>
    </div>

    {{-- Filters: open search + role/type filter --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <input type="search" wire:model.live.debounce.300ms="search"
                   placeholder="Search name or email…"
                   class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-green-500 focus:ring-green-500" />
        </div>
        <select wire:model.live="roleFilter"
                class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500">
            <option value="">All roles</option>
            @foreach($filterRoles as $r)
                <option value="{{ $r }}">{{ ucwords(str_replace('_', ' ', $r)) }}</option>
            @endforeach
        </select>
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
                        <tr><td colspan="5" class="p-8 text-center text-gray-500">
                            @if($search !== '' || $roleFilter !== '')
                                No staff match your filters.
                            @else
                                No staff yet.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $staff->links() }}</div>
    </div>
</div>
