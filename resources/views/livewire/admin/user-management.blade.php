<div>
    <x-slot name="header">User Management</x-slot>

    <!-- Flash Messages -->
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

    <!-- Filters Bar -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-3">
            <!-- Search -->
            <div class="flex-1">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by name, email, or phone..."
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            </div>
            <!-- Role Filter -->
            <select wire:model.live="roleFilter" class="rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                <option value="">All Roles</option>
                <option value="admin">Administrators</option>
                <option value="doctor">Doctors</option>
                <option value="patient">Patients</option>
            </select>
            <!-- Status Filter -->
            <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" wire:click="sortBy('first_name')">
                            <div class="flex items-center gap-1">
                                User
                                @if($sortBy === 'first_name')
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="{{ $sortDirection === 'asc' ? 'M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z' : 'M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 15.586l3.293-3.293a1 1 0 011.414 0z' }}"/></svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" wire:click="sortBy('created_at')">
                            <div class="flex items-center gap-1">
                                Joined
                                @if($sortBy === 'created_at')
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="{{ $sortDirection === 'asc' ? 'M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z' : 'M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 15.586l3.293-3.293a1 1 0 011.414 0z' }}"/></svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-9 h-9 rounded-full {{ $user->is_active ? 'bg-zapmed-100' : 'bg-gray-200' }} flex items-center justify-center">
                                    <span class="text-xs font-medium {{ $user->is_active ? 'text-zapmed-700' : 'text-gray-500' }}">
                                        {{ substr($user->first_name, 0, 1) }}{{ substr($user->last_name, 0, 1) }}
                                    </span>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <select wire:change="changeRole({{ $user->id }}, $event.target.value)"
                                class="text-xs rounded-md border-gray-200 py-1 focus:border-zapmed-500 focus:ring-zapmed-500 {{ $user->id === auth()->id() ? 'opacity-50 cursor-not-allowed' : '' }}"
                                {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                                @foreach(App\Enums\UserRole::cases() as $role)
                                    <option value="{{ $role->value }}" {{ $user->role === $role ? 'selected' : '' }}>{{ $role->label() }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($user->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $user->created_at->format('j M Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            @if($user->id !== auth()->id())
                            <div class="flex items-center justify-end gap-2">
                                <button wire:click="toggleActive({{ $user->id }})"
                                    class="text-xs font-medium {{ $user->is_active ? 'text-amber-600 hover:text-amber-800' : 'text-green-600 hover:text-green-800' }} transition-colors">
                                    {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                                <button wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="Are you sure you want to delete this user? This is a soft delete and can be reversed."
                                    class="text-xs font-medium text-red-600 hover:text-red-800 transition-colors">
                                    Delete
                                </button>
                            </div>
                            @else
                                <span class="text-xs text-gray-400">You</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-400">
                            No users found matching your criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</div>
