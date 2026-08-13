<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Review Moderation</h1>
            <p class="text-sm text-gray-500 mt-1">Approve, reject, or feature patient reviews.</p>
        </div>
        @if($this->pendingCount > 0)
            <span class="inline-flex items-center bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-medium">
                {{ $this->pendingCount }} pending
            </span>
        @endif
    </div>

    @if(session('message'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ session('message') }}
        </div>
    @endif

    <!-- Filter Tabs -->
    <div class="flex items-center gap-1 mb-6 bg-gray-100 rounded-lg p-1 w-fit">
        <button wire:click="$set('filter', 'pending')"
            class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $filter === 'pending' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
            Pending
        </button>
        <button wire:click="$set('filter', 'approved')"
            class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $filter === 'approved' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
            Approved
        </button>
        <button wire:click="$set('filter', 'all')"
            class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $filter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
            All
        </button>
    </div>

    <!-- Reviews List -->
    <div class="space-y-4">
        @forelse($this->reviews as $review)
            <div class="bg-white border border-gray-200 rounded-xl p-5 {{ !$review->is_approved ? 'border-l-4 border-l-yellow-400' : '' }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <!-- Header -->
                        <div class="flex items-center gap-3 mb-2">
                            <div class="flex items-center">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-4 h-4 {{ $review->rating >= $i ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                @endfor
                            </div>
                            <span class="text-xs text-gray-400">{{ $review->created_at->format('d M Y') }}</span>
                            @if($review->is_featured)
                                <span class="text-xs bg-zapmed-100 text-zapmed-700 px-2 py-0.5 rounded-full font-medium">Featured</span>
                            @endif
                        </div>

                        <!-- Comment -->
                        <p class="text-sm text-gray-700 mb-2">{{ $review->comment }}</p>

                        <!-- Meta -->
                        <div class="flex items-center gap-4 text-xs text-gray-400">
                            <span>Patient: {{ $review->patient->name }}</span>
                            <span>Doctor: Dr. {{ $review->doctor?->last_name ?? 'N/A' }}</span>
                            <span>Category: {{ $review->treatment_category }}</span>
                            @if($review->would_recommend)
                                <span class="text-green-600">Would recommend</span>
                            @endif
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 shrink-0">
                        @if(!$review->is_approved)
                            <button wire:click="approve({{ $review->id }})" class="text-xs bg-green-50 text-green-700 hover:bg-green-100 px-3 py-1.5 rounded-lg font-medium transition-colors">
                                Approve
                            </button>
                        @else
                            <button wire:click="reject({{ $review->id }})" class="text-xs bg-gray-50 text-gray-600 hover:bg-gray-100 px-3 py-1.5 rounded-lg font-medium transition-colors">
                                Unapprove
                            </button>
                        @endif
                        <button wire:click="feature({{ $review->id }})" class="text-xs bg-yellow-50 text-yellow-700 hover:bg-yellow-100 px-3 py-1.5 rounded-lg font-medium transition-colors">
                            {{ $review->is_featured ? 'Unfeature' : 'Feature' }}
                        </button>
                        <button wire:click="delete({{ $review->id }})" wire:confirm="Delete this review permanently?"
                            class="text-xs bg-red-50 text-red-600 hover:bg-red-100 px-3 py-1.5 rounded-lg font-medium transition-colors">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-12 bg-gray-50 rounded-xl">
                <p class="text-sm text-gray-500">No reviews {{ $filter === 'pending' ? 'pending moderation' : 'found' }}.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $this->reviews->links() }}
    </div>
</div>
