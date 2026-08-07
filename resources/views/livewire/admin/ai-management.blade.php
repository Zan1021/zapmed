<div>
    <x-slot name="header">AI Assistant Management</x-slot>

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-sm text-green-700">{{ session('message') }}</p>
        </div>
    @endif

    <!-- Stats Row -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Total Queries</p>
            <p class="text-xl font-bold text-gray-900">{{ number_format($this->stats['total_queries']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Today</p>
            <p class="text-xl font-bold text-gray-900">{{ $this->stats['today'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">This Week</p>
            <p class="text-xl font-bold text-gray-900">{{ $this->stats['this_week'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Match Rate</p>
            <p class="text-xl font-bold text-green-600">{{ $this->stats['matched_rate'] }}%</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Unmatched</p>
            <p class="text-xl font-bold text-amber-600">{{ $this->stats['unmatched_count'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Knowledge Base</p>
            <p class="text-xl font-bold text-gray-900">{{ $this->stats['knowledge_entries'] }}</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex space-x-1 bg-gray-100 rounded-lg p-1 w-fit mb-6">
        <button wire:click="$set('tab', 'stats')" class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $tab === 'stats' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">Insights</button>
        <button wire:click="$set('tab', 'conversations')" class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $tab === 'conversations' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">Conversations</button>
        <button wire:click="$set('tab', 'knowledge')" class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $tab === 'knowledge' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">Teach AI</button>
    </div>

    <!-- Stats Tab -->
    @if($tab === 'stats')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Questions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Most Asked Questions</h3>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($this->topQuestions as $q)
                <div class="p-4 flex items-center justify-between">
                    <p class="text-sm text-gray-700 truncate flex-1 mr-3">{{ $q->question }}</p>
                    <span class="text-xs font-medium bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $q->ask_count }}x</span>
                </div>
                @empty
                <div class="p-6 text-center text-sm text-gray-400">No conversations yet.</div>
                @endforelse
            </div>
        </div>

        <!-- Top Treatments -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Most Requested Treatments</h3>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($this->topTreatments as $t)
                <div class="p-4 flex items-center justify-between">
                    <p class="text-sm text-gray-700">{{ $t->matched_treatment_name }}</p>
                    <span class="text-xs font-medium bg-zapmed-100 text-zapmed-700 px-2 py-0.5 rounded-full">{{ $t->count }}x</span>
                </div>
                @empty
                <div class="p-6 text-center text-sm text-gray-400">No matched treatments yet.</div>
                @endforelse
            </div>
        </div>

        <!-- Unanswered / Unmatched -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">Questions Without a Match</h3>
                <span class="text-xs text-gray-400">These need knowledge base entries</span>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($this->unmatchedQuestions as $q)
                <div class="p-4 flex items-center justify-between">
                    <p class="text-sm text-gray-700">{{ $q->question }}</p>
                    <span class="text-xs font-medium bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">{{ $q->ask_count }}x</span>
                </div>
                @empty
                <div class="p-6 text-center text-sm text-gray-400">All questions are being matched. Nice.</div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    <!-- Conversations Tab -->
    @if($tab === 'conversations')
    <div class="mb-4">
        <select wire:model.live="filterMatch" class="rounded-lg border-gray-300 text-sm">
            <option value="">All Conversations</option>
            <option value="matched">Matched (found treatment)</option>
            <option value="unmatched">Unmatched (no treatment)</option>
        </select>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="divide-y divide-gray-50">
            @forelse($conversations as $conv)
            <div class="p-4">
                <div class="flex items-start justify-between mb-2">
                    <p class="text-sm font-medium text-gray-900">{{ $conv->question }}</p>
                    <div class="flex items-center space-x-2 flex-shrink-0 ml-3">
                        @if($conv->had_match)
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">{{ $conv->matched_treatment_name }}</span>
                        @else
                            <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">No match</span>
                        @endif
                    </div>
                </div>
                <p class="text-xs text-gray-500 line-clamp-2">{{ $conv->response }}</p>
                <p class="text-xs text-gray-400 mt-2">{{ $conv->created_at->diffForHumans() }} &middot; {{ $conv->ip_address }}</p>
            </div>
            @empty
            <div class="p-8 text-center text-sm text-gray-400">No conversations logged yet.</div>
            @endforelse
        </div>

        @if($conversations->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $conversations->links() }}</div>
        @endif
    </div>
    @endif

    <!-- Knowledge Base Tab -->
    @if($tab === 'knowledge')
    <div class="mb-4">
        <button wire:click="createEntry" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">
            + Add Knowledge Entry
        </button>
    </div>

    @if($showForm)
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">{{ $editingId ? 'Edit Entry' : 'Teach the AI Something New' }}</h3>
        <form wire:submit="saveEntry" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                    <input wire:model="title" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. Operating hours">
                    @error('title') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select wire:model="category" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="general">General</option>
                        <option value="treatment">Treatment Info</option>
                        <option value="faq">FAQ</option>
                        <option value="policy">Policy</option>
                        <option value="pricing">Pricing</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Content * <span class="text-xs text-gray-400">(what the AI should know)</span></label>
                <textarea wire:model="content" rows="4" class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. Zapmed operates Monday to Friday 8am-6pm and Saturdays 8am-1pm. We do not offer consultations on Sundays or public holidays."></textarea>
                @error('content') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex space-x-3">
                <button type="submit" class="px-5 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg">{{ $editingId ? 'Update' : 'Save Entry' }}</button>
                <button type="button" wire:click="cancelForm" class="px-5 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg">Cancel</button>
            </div>
        </form>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="divide-y divide-gray-100">
            @forelse($knowledgeEntries as $entry)
            <div class="p-4 flex items-center justify-between {{ !$entry->is_active ? 'opacity-50' : '' }}">
                <div class="flex-1 min-w-0 mr-4">
                    <div class="flex items-center space-x-2">
                        <p class="text-sm font-medium text-gray-900">{{ $entry->title }}</p>
                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ $entry->category }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 truncate">{{ $entry->content }}</p>
                </div>
                <div class="flex items-center space-x-2">
                    <button wire:click="editEntry({{ $entry->id }})" class="text-xs text-zapmed-600 hover:text-zapmed-800 font-medium">Edit</button>
                    <button wire:click="toggleEntry({{ $entry->id }})" class="text-xs {{ $entry->is_active ? 'text-amber-600' : 'text-green-600' }} font-medium">{{ $entry->is_active ? 'Disable' : 'Enable' }}</button>
                    <button wire:click="deleteEntry({{ $entry->id }})" wire:confirm="Delete this knowledge entry?" class="text-xs text-red-600 font-medium">Delete</button>
                </div>
            </div>
            @empty
            <div class="p-8 text-center text-sm text-gray-400">
                No knowledge entries yet. Add entries to teach the AI about your business — operating hours, policies, pricing details, FAQs, etc.
            </div>
            @endforelse
        </div>
    </div>
    @endif
</div>
