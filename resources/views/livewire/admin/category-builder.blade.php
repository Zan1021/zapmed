<div>
    <x-slot name="header">Category Builder</x-slot>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <p class="text-sm text-gray-500">Build a treatment's assessment from the question bank. The core block is added automatically to every treatment.</p>
        <div class="flex gap-2">
            <a href="{{ route('admin.question-bank') }}" class="inline-flex items-center bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-semibold">Question Library</a>
            <button wire:click="$set('showNewCategory', true)" class="inline-flex items-center bg-zapmed-600 hover:bg-zapmed-700 text-white px-4 py-2.5 rounded-lg text-sm font-semibold">+ New Treatment</button>
        </div>
    </div>

    @if($flash)<div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">{{ $flash }}</div>@endif
    @if($error)<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">{{ $error }}</div>@endif

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Category picker -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3">
                <p class="text-xs font-medium text-gray-400 uppercase px-2 mb-2">Treatments</p>
                <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                    @foreach($categories as $c)
                        <button wire:click="selectCategory({{ $c->id }})"
                            class="w-full text-left px-3 py-2 rounded-lg text-sm {{ $category && $category->id === $c->id ? 'bg-zapmed-50 text-zapmed-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
                            {{ $c->name }}
                            <span class="block text-xs text-gray-400">{{ ucfirst($c->status) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Flow -->
        <div class="lg:col-span-3">
            @if(! $category)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center text-gray-400 text-sm">Pick a treatment, or create a new one.</div>
            @else
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $category->name }}</h3>
                            <p class="text-xs text-gray-400">Status: {{ ucfirst($category->status) }} · manifest v{{ $category->manifest_version }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="$toggle('showPreview')" class="px-3 py-2 rounded-lg text-sm font-medium border border-gray-300 text-gray-700 hover:bg-gray-50">{{ $showPreview ? 'Hide' : 'Preview' }}</button>
                            @if($category->status !== 'live')
                                <button wire:click="publish" wire:confirm="Publish this treatment's assessment? Patients will see it immediately." class="px-3 py-2 rounded-lg text-sm font-semibold bg-zapmed-600 hover:bg-zapmed-700 text-white">Publish</button>
                            @endif
                        </div>
                    </div>

                    <!-- Locked CORE block -->
                    <div class="mb-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Core block — common to every treatment</p>
                        </div>
                        <p class="text-xs text-gray-400 mb-3">Locked here. Edit these in the Question Library.</p>
                        @forelse($coreBlock as $cq)
                            <div class="text-sm text-gray-500 py-1">• {{ $cq->currentVersion?->text }}</div>
                        @empty
                            <div class="text-xs text-gray-400 italic">No core questions published yet.</div>
                        @endforelse
                    </div>

                    <!-- Treatment questions (draggable) -->
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Treatment questions</p>
                    <div
                        x-data="{
                            dragEl: null,
                            init() {
                                this.$el.querySelectorAll('[data-id]').forEach(item => {
                                    item.setAttribute('draggable', true);
                                    item.addEventListener('dragstart', () => this.dragEl = item);
                                    item.addEventListener('dragover', e => {
                                        e.preventDefault();
                                        const t = e.target.closest('[data-id]');
                                        if (t && t !== this.dragEl) {
                                            const rect = t.getBoundingClientRect();
                                            const after = (e.clientY - rect.top) > rect.height / 2;
                                            this.$el.insertBefore(this.dragEl, after ? t.nextSibling : t);
                                        }
                                    });
                                    item.addEventListener('dragend', () => {
                                        const ordered = [...this.$el.querySelectorAll('[data-id]')].map(i => parseInt(i.dataset.id));
                                        $wire.reorder(ordered);
                                    });
                                });
                            }
                        }"
                        class="space-y-2" id="flow-list" wire:key="flow-{{ $category->id }}-{{ $category->manifest_version }}">
                        @forelse($flow as $link)
                            <div class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-white" data-id="{{ $link->question_id }}">
                                <svg class="w-4 h-4 text-gray-300 cursor-move shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 truncate">{{ $link->question->currentVersion?->text }}</p>
                                    <p class="text-xs text-gray-400 font-mono">{{ $link->question->question_key }} · {{ $link->question->currentVersion?->type }}</p>
                                </div>
                                @if($link->question->currentVersion?->branch_rules)
                                    <span class="text-xs text-blue-600 shrink-0">conditional</span>
                                @endif
                                <button wire:click="removeQuestion({{ $link->question_id }})" class="text-red-400 hover:text-red-600 text-sm shrink-0">Remove</button>
                            </div>
                        @empty
                            <div class="text-xs text-gray-400 italic py-2">No treatment-specific questions yet — add some below.</div>
                        @endforelse
                    </div>

                    <!-- Add questions -->
                    <div class="mt-6 border-t border-gray-100 pt-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Add a question</p>
                        <input wire:model.live.debounce.300ms="addSearch" type="text" placeholder="Search the bank..."
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm mb-2">
                        <div class="space-y-1 max-h-60 overflow-y-auto">
                            @forelse($available as $a)
                                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50">
                                    <div class="min-w-0">
                                        <p class="text-sm text-gray-800 truncate">{{ $a->currentVersion?->text }}</p>
                                        <p class="text-xs text-gray-400 font-mono">{{ $a->question_key }}</p>
                                    </div>
                                    <button wire:click="addQuestion({{ $a->id }})" class="shrink-0 text-zapmed-600 hover:text-zapmed-800 text-sm font-medium">Add</button>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 italic p-2">No matching questions. Create one in the Question Library.</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Preview -->
                    @if($showPreview)
                        <div class="mt-6 border-t border-gray-100 pt-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Patient preview (full flow, in order)</p>
                            <ol class="list-decimal list-inside space-y-1">
                                @foreach($previewFlow as $pq)
                                    <li class="text-sm text-gray-700">{{ $pq['label'] }} <span class="text-xs text-gray-400">({{ $pq['type'] }}{{ isset($pq['show_if']) ? ', conditional' : '' }})</span></li>
                                @endforeach
                            </ol>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- New category modal -->
    @if($showNewCategory)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="$set('showNewCategory', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
                <div class="p-6 border-b border-gray-100"><h3 class="text-lg font-semibold text-gray-900">New treatment category</h3></div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input wire:model="newName" type="text" placeholder="e.g. Allergy Relief" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                        @error('newName') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL slug</label>
                        <input wire:model="newSlug" type="text" placeholder="e.g. allergy-relief" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm font-mono">
                        @error('newSlug') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="p-6 border-t border-gray-100 flex justify-end gap-3">
                    <button wire:click="$set('showNewCategory', false)" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">Cancel</button>
                    <button wire:click="createCategory" class="px-4 py-2 rounded-lg text-sm font-semibold bg-zapmed-600 hover:bg-zapmed-700 text-white">Create</button>
                </div>
            </div>
        </div>
    @endif
</div>
