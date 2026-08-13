<div>
    <x-slot name="header">Failed Payments</x-slot>

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            {{ session('message') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <!-- Stats -->
    <div class="mb-6 flex items-center gap-4">
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3">
            <p class="text-2xl font-bold text-red-700">{{ $failedCount }}</p>
            <p class="text-xs text-red-600">Failed Payments</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 mb-6">
        <div class="flex gap-2">
            <button wire:click="$set('filter', 'failed')" class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $filter === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                Failed Only
            </button>
            <button wire:click="$set('filter', 'all')" class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $filter === 'all' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                Failed + Paused
            </button>
        </div>
        <div class="flex-1 max-w-sm">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email..." class="w-full rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Patient</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Plan</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Amount</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Failed At</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Contacted</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($subscriptions as $sub)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ $sub->user->name }}</p>
                        <p class="text-xs text-gray-500">{{ $sub->user->email }}</p>
                        <p class="text-xs text-gray-400">{{ $sub->user->phone }}</p>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $sub->plan->name ?? 'N/A' }}</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $sub->plan->formatted_price ?? 'N/A' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        @if(isset($sub->metadata['last_failure_at']))
                            {{ \Carbon\Carbon::parse($sub->metadata['last_failure_at'])->format('d M Y H:i') }}
                        @else
                            {{ $sub->updated_at->format('d M Y H:i') }}
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $sub->status === 'payment_failed' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700' }}">
                            {{ $sub->status === 'payment_failed' ? 'Failed' : ucfirst($sub->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-xs text-gray-500">
                        @if(isset($sub->metadata['contacted_at']))
                            <span class="text-green-600">Yes</span>
                            <br>{{ \Carbon\Carbon::parse($sub->metadata['contacted_at'])->diffForHumans() }}
                        @else
                            <span class="text-gray-400">No</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button wire:click="resendEmail({{ $sub->id }})" class="text-xs px-2 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded transition-colors" title="Send reminder email">
                                Email
                            </button>
                            <button wire:click="markContacted({{ $sub->id }})" class="text-xs px-2 py-1 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded transition-colors" title="Mark as contacted">
                                Contacted
                            </button>
                            @if($sub->status === 'payment_failed')
                            <button wire:click="markResolved({{ $sub->id }})" class="text-xs px-2 py-1 bg-green-50 text-green-700 hover:bg-green-100 rounded transition-colors" title="Mark as resolved and reactivate">
                                Resolve
                            </button>
                            <button wire:click="pauseSubscription({{ $sub->id }})" class="text-xs px-2 py-1 bg-gray-50 text-gray-700 hover:bg-gray-100 rounded transition-colors" title="Pause subscription">
                                Pause
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-green-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="font-medium">All clear!</p>
                            <p class="text-sm">No failed payments to follow up on.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-6 py-3 border-t border-gray-100">
            {{ $subscriptions->links() }}
        </div>
    </div>
</div>
