<div>
    <x-slot name="header">Doctor Applications</x-slot>

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            {{ session('message') }}
        </div>
    @endif

    <!-- Status Tabs -->
    <div class="flex items-center gap-2 mb-6">
        <button wire:click="filterByStatus('pending')" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $statusFilter === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
            Pending <span class="ml-1 bg-yellow-200 text-yellow-800 px-1.5 py-0.5 rounded-full text-xs">{{ $counts['pending'] }}</span>
        </button>
        <button wire:click="filterByStatus('approved')" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $statusFilter === 'approved' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
            Approved <span class="ml-1 text-xs">({{ $counts['approved'] }})</span>
        </button>
        <button wire:click="filterByStatus('rejected')" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $statusFilter === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
            Rejected <span class="ml-1 text-xs">({{ $counts['rejected'] }})</span>
        </button>
        <button wire:click="filterByStatus('all')" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $statusFilter === 'all' ? 'bg-zapmed-100 text-zapmed-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
            All
        </button>
    </div>

    <!-- Applications Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Doctor</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">HPCSA</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Speciality</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Type</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Applied</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($applications as $app)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">Dr. {{ $app->first_name }} {{ $app->last_name }}</p>
                        <p class="text-xs text-gray-500">{{ $app->email }}</p>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $app->hpcsa_number }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $app->speciality }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $app->doctor_type === 'full_time' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                            {{ $app->doctor_type === 'full_time' ? 'Full-Time' : 'Locum' }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full
                            {{ $app->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                            {{ $app->status === 'approved' ? 'bg-green-100 text-green-700' : '' }}
                            {{ $app->status === 'rejected' ? 'bg-red-100 text-red-700' : '' }}">
                            {{ ucfirst($app->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $app->created_at->diffForHumans() }}</td>
                    <td class="px-6 py-4 text-right">
                        <button wire:click="viewApplication({{ $app->id }})" class="text-sm text-zapmed-600 hover:text-zapmed-700 font-medium">
                            Review
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        No {{ $statusFilter === 'all' ? '' : $statusFilter }} applications found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-6 py-3 border-t border-gray-100">
            {{ $applications->links() }}
        </div>
    </div>

    <!-- Application Detail Modal -->
    @if($viewingId)
    @php $viewing = \App\Models\DoctorApplication::find($viewingId); @endphp
    @if($viewing)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closeView">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">Dr. {{ $viewing->name }}</h3>
                <button wire:click="closeView" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">Email:</span> <span class="text-gray-900 font-medium">{{ $viewing->email }}</span></div>
                    <div><span class="text-gray-500">Phone:</span> <span class="text-gray-900 font-medium">{{ $viewing->phone }}</span></div>
                    <div><span class="text-gray-500">HPCSA:</span> <span class="text-gray-900 font-medium">{{ $viewing->hpcsa_number }}</span></div>
                    <div><span class="text-gray-500">Speciality:</span> <span class="text-gray-900 font-medium">{{ $viewing->speciality }}</span></div>
                    <div><span class="text-gray-500">Qualification:</span> <span class="text-gray-900 font-medium">{{ $viewing->qualification }}</span></div>
                    <div><span class="text-gray-500">Experience:</span> <span class="text-gray-900 font-medium">{{ $viewing->years_experience }} years</span></div>
                    <div><span class="text-gray-500">Type:</span> <span class="text-gray-900 font-medium">{{ $viewing->doctor_type === 'full_time' ? 'Full-Time' : 'Locum' }}</span></div>
                    <div><span class="text-gray-500">Applied:</span> <span class="text-gray-900 font-medium">{{ $viewing->created_at->format('d M Y') }}</span></div>
                </div>

                @if($viewing->motivation)
                <div>
                    <p class="text-sm text-gray-500 mb-1">Motivation:</p>
                    <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg">{{ $viewing->motivation }}</p>
                </div>
                @endif

                <!-- Documents -->
                <div class="flex gap-3">
                    @if($viewing->hpcsa_certificate_path)
                    <a href="{{ asset('storage/' . $viewing->hpcsa_certificate_path) }}" target="_blank" class="inline-flex items-center px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        HPCSA Certificate
                    </a>
                    @endif
                    @if($viewing->id_document_path)
                    <a href="{{ asset('storage/' . $viewing->id_document_path) }}" target="_blank" class="inline-flex items-center px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                        ID Document
                    </a>
                    @endif
                </div>

                <!-- Admin Notes -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Admin Notes</label>
                    <textarea wire:model="adminNotes" rows="2" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm" placeholder="Internal notes or reason for rejection..."></textarea>
                </div>
            </div>

            @if($viewing->isPending())
            <div class="p-6 border-t border-gray-100 flex justify-end gap-3">
                <button wire:click="reject({{ $viewing->id }})" class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                    Reject
                </button>
                <button wire:click="approve({{ $viewing->id }})" class="px-4 py-2 text-sm font-medium text-white bg-zapmed-600 hover:bg-zapmed-700 rounded-lg transition-colors">
                    Approve & Create Account
                </button>
            </div>
            @endif
        </div>
    </div>
    @endif
    @endif
</div>
