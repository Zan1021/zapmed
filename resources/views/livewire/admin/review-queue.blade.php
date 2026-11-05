<div>
    <x-slot name="header">Clinical Review Queue</x-slot>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <p class="text-sm text-gray-500">Review each question as a patient sees it, then approve, reword, or send it back. A treatment goes live once all its questions are approved.</p>
        <div class="flex gap-2">
            <a href="{{ route('admin.question-bank') }}" class="inline-flex items-center bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-semibold">Question Library</a>
            <a href="{{ route('admin.question-bank.categories') }}" class="inline-flex items-center bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-semibold">Category Builder</a>
        </div>
    </div>

    @if($flash)<div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">{{ $flash }}</div>@endif

    <!-- Filter + progress -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <select wire:model.live="treatmentFilter" class="rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                <option value="">All treatments</option>
                @foreach($categories as $c)
                    <option value="{{ $c->slug }}">{{ $c->name }} ({{ ucfirst($c->status) }})</option>
                @endforeach
            </select>
            <div class="flex-1">
                @php $pct = $totalInScope > 0 ? round($reviewed / $totalInScope * 100) : 0; @endphp
                <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                    <span>{{ $reviewed }} of {{ $totalInScope }} reviewed</span>
                    <span>{{ $totalPending }} left</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="bg-zapmed-500 h-2 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                </div>
            </div>
        </div>
    </div>

    @if(! $this->current)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <svg class="w-10 h-10 mx-auto text-green-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <p class="text-sm font-semibold text-gray-900">Nothing left to review{{ $treatmentFilter ? ' for this treatment' : '' }}.</p>
            <p class="text-xs text-gray-500 mt-1">Every question here has been approved or sent back.</p>
        </div>
    @else
        @php $v = $this->current->currentVersion; @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    @foreach($currentCategories as $cn)
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-purple-50 text-purple-700 font-medium">{{ $cn }}</span>
                    @endforeach
                    @if($this->current->clinical)
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-medium">clinical</span>
                    @endif
                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-600">{{ ucfirst(str_replace('_',' ',$this->current->status)) }}</span>
                </div>
                <span class="text-[11px] font-mono text-gray-300">{{ $this->current->question_key }} · v{{ $v?->version }}</span>
            </div>

            <!-- Patient-eye-view of the question -->
            <div class="p-6">
                @if($rejecting)
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <label class="block text-sm font-medium text-red-800 mb-1">Why send this back? (the author will see this)</label>
                        <input wire:model="rejectReason" type="text" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-400 focus:ring-red-400 text-sm" placeholder="e.g. must also screen for smoking in over-35s">
                        @error('rejectReason') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        <div class="mt-3 flex gap-2">
                            <button wire:click="confirmReject" class="px-3 py-2 rounded-lg text-sm font-semibold bg-red-600 hover:bg-red-700 text-white">Send back to draft</button>
                            <button wire:click="$set('rejecting', false)" class="px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100">Cancel</button>
                        </div>
                    </div>
                @elseif($editing)
                    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-blue-900 mb-1">Question wording</label>
                            <input wire:model="editText" type="text" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            @error('editText') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-900 mb-1">Helper text</label>
                            <input wire:model="editHelper" type="text" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-900 mb-1">Options (one per line)</label>
                            <textarea wire:model="optionsRaw" rows="4" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm font-mono"></textarea>
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="saveEdit" class="px-3 py-2 rounded-lg text-sm font-semibold bg-zapmed-600 hover:bg-zapmed-700 text-white">Save new version</button>
                            <button wire:click="$set('editing', false)" class="px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100">Cancel</button>
                        </div>
                    </div>
                @else
                    <label class="block text-base font-semibold text-gray-900 mb-1">
                        {{ $v?->text }}
                        @if($v?->required) <span class="text-red-500">*</span> @endif
                    </label>
                    @if($v?->helper_text)<p class="text-sm text-gray-500 mb-3">{{ $v->helper_text }}</p>@endif

                    <div class="mt-3">
                        @php $type = $v?->type; $options = $v?->options ?? []; @endphp
                        @if(in_array($type, ['single','multi','select']) && count($options))
                            <div class="space-y-2">
                                @foreach($options as $o)
                                    @php $label = is_array($o) ? ($o['label'] ?? $o['code'] ?? '') : $o; @endphp
                                    <div class="flex items-center gap-2 p-2.5 rounded-lg border border-gray-200">
                                        <span class="w-4 h-4 rounded-{{ $type === 'multi' ? 'sm' : 'full' }} border-2 border-gray-300"></span>
                                        <span class="text-sm text-gray-700">{{ $label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @elseif($type === 'textarea')
                            <div class="h-20 rounded-lg border border-gray-200 bg-gray-50"></div>
                        @elseif($type === 'number')
                            <div class="h-10 w-40 rounded-lg border border-gray-200 bg-gray-50 flex items-center px-3 text-sm text-gray-400">{{ $v?->placeholder ?: '123' }}</div>
                        @else
                            <div class="h-10 rounded-lg border border-gray-200 bg-gray-50 flex items-center px-3 text-sm text-gray-400">{{ $v?->placeholder ?: 'Free text answer' }}</div>
                        @endif
                    </div>

                    @if($v?->branch_rules)
                        <p class="mt-4 text-xs text-blue-600">
                            <span class="font-semibold">Conditional:</span> only shown when
                            @foreach($v->branch_rules as $depKey => $vals)
                                <span class="font-mono">{{ $depKey }}</span> = {{ is_array($vals) ? implode(' / ', $vals) : $vals }}@if(!$loop->last); @endif
                            @endforeach
                        </p>
                    @endif
                @endif
            </div>

            <!-- Actions -->
            @unless($editing || $rejecting)
                <div class="p-4 border-t border-gray-100 flex items-center justify-between">
                    <div class="flex gap-2">
                        <button wire:click="approve" class="px-4 py-2 rounded-lg text-sm font-semibold bg-green-600 hover:bg-green-700 text-white">✓ Approve</button>
                        <button wire:click="startEdit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-white border border-zapmed-600 text-zapmed-700 hover:bg-zapmed-50">Edit wording</button>
                        <button wire:click="startReject" class="px-4 py-2 rounded-lg text-sm font-semibold bg-white border border-red-300 text-red-600 hover:bg-red-50">Send back</button>
                    </div>
                    <button wire:click="loadNext" class="px-3 py-2 rounded-lg text-sm text-gray-500 hover:bg-gray-100">Skip →</button>
                </div>
            @endunless
        </div>
    @endif
</div>
