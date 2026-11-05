<div>
    <x-slot name="header">Question Library</x-slot>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <p class="text-sm text-gray-500">One bank of questions, reused across treatments. Edit a question once — it changes everywhere it's used.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.question-bank.categories') }}" class="inline-flex items-center bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors">
                Category Builder
            </a>
            <button wire:click="create" class="inline-flex items-center bg-zapmed-600 hover:bg-zapmed-700 text-white px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                New Question
            </button>
        </div>
    </div>

    @if(session('qb_status'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">{{ session('qb_status') }}</div>
    @endif

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search question text or key..."
            class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
        <select wire:model.live="scopeFilter" class="rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
            <option value="">All scopes</option>
            <option value="core">Core (every treatment)</option>
            <option value="category">Treatment-specific</option>
        </select>
        <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
            <option value="">All statuses</option>
            <option value="draft">Draft</option>
            <option value="in_review">In review</option>
            <option value="live">Live</option>
            <option value="retired">Retired</option>
        </select>
        <select wire:model.live="clinicalFilter" class="rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
            <option value="">Clinical &amp; non-clinical</option>
            <option value="1">Clinical only</option>
            <option value="0">Non-clinical only</option>
        </select>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Question</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Key</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Scope</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Used in</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($questions as $q)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900 max-w-md">
                                {{ $q->currentVersion?->text ?? '—' }}
                                @if($q->clinical)
                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">clinical</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs font-mono text-gray-500">{{ $q->question_key }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $q->currentVersion?->type ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $q->scope === 'core' ? 'Core' : 'Treatment' }}</td>
                            <td class="px-4 py-3">
                                @php $colors = ['draft' => 'bg-gray-100 text-gray-700', 'in_review' => 'bg-blue-100 text-blue-800', 'live' => 'bg-green-100 text-green-800', 'retired' => 'bg-red-50 text-red-600']; @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $colors[$q->status] ?? 'bg-gray-100 text-gray-700' }}">{{ ucfirst(str_replace('_', ' ', $q->status)) }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $q->used_in_count }}</td>
                            <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                <button wire:click="edit({{ $q->id }})" class="text-zapmed-600 hover:text-zapmed-800 font-medium">Edit</button>
                                <button wire:click="duplicate({{ $q->id }})" class="ml-3 text-gray-500 hover:text-gray-700">Duplicate</button>
                                @if($q->status !== 'retired')
                                    <button wire:click="retire({{ $q->id }})" wire:confirm="Retire this question? It stays readable for past answers but won't be added to new flows." class="ml-3 text-red-500 hover:text-red-700">Retire</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">No questions match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $questions->links() }}</div>

    <!-- Edit / Create modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit question' : 'New question' }}</h3>
                    <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>
                <div class="p-6 space-y-4">
                    @if($usageWarning)
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">{{ $usageWarning }}</div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Question text <span class="text-red-500">*</span></label>
                        <input wire:model="text" type="text" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                        @error('text') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Answer type <span class="text-red-500">*</span></label>
                            <select wire:model="type" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                                <option value="single">Single choice (radio)</option>
                                <option value="multi">Multiple choice (checkbox)</option>
                                <option value="select">Dropdown</option>
                                <option value="text">Short text</option>
                                <option value="textarea">Long text</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="upload">File upload</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Scope</label>
                            <select wire:model="scope" @if($editingId) disabled @endif class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm disabled:bg-gray-100">
                                <option value="category">Treatment-specific</option>
                                <option value="core">Core (every treatment)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Options (one per line — for choice/dropdown types)</label>
                        <textarea wire:model="optionsRaw" rows="4" placeholder="Yes&#10;No&#10;Not sure" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm font-mono"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Helper text</label>
                            <input wire:model="helper_text" type="text" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Placeholder</label>
                            <input wire:model="placeholder" type="text" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input wire:model="required" type="checkbox" class="rounded text-zapmed-600 focus:ring-zapmed-500 border-gray-300 mr-2"> Required
                        </label>
                        <label class="inline-flex items-center text-sm text-gray-700">
                            <input wire:model="clinical" type="checkbox" @if($editingId) disabled @endif class="rounded text-zapmed-600 focus:ring-zapmed-500 border-gray-300 mr-2 disabled:opacity-50"> Clinical question
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Change reason (for the changelog)</label>
                        <input wire:model="publishReason" type="text" placeholder="e.g. reworded for clarity" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    </div>
                </div>
                <div class="p-6 border-t border-gray-100 flex justify-end gap-3">
                    <button wire:click="$set('showModal', false)" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">Cancel</button>
                    <button wire:click="save" class="px-4 py-2 rounded-lg text-sm font-semibold bg-white border border-zapmed-600 text-zapmed-700 hover:bg-zapmed-50">Save as draft</button>
                    @if($editingId)
                        <button wire:click="publishCurrent" class="px-4 py-2 rounded-lg text-sm font-semibold bg-zapmed-600 hover:bg-zapmed-700 text-white">Save &amp; publish live</button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
