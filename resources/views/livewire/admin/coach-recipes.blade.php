<div>
    <x-slot name="header">Coach recipes</x-slot>

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700" data-message>{{ session('message') }}</div>
    @endif

    @if($showForm)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6" data-recipe-form>
            <h3 class="text-base font-semibold text-gray-900 mb-4">{{ $editingId ? 'Edit recipe' : 'New recipe' }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Meal</label>
                    <select wire:model="slot" class="w-full rounded-lg border-gray-300 text-sm" data-field="slot">
                        @foreach($slots as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 text-sm" data-field="name">
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Short description (optional)</label>
                    <input type="text" wire:model="summary" class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>

            <h4 class="text-sm font-semibold text-gray-700 mt-5 mb-2">Nutrition per serving (estimates)</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach([['minutes','Minutes'],['kcal','Calories (kcal)'],['protein_g','Protein (g)'],['fibre_g','Fibre (g)'],['carbs_g','Carbs (g)'],['fat_g','Fat (g)'],['sugars_g','Sugars (g)']] as [$f, $label])
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                        <input type="text" inputmode="decimal" wire:model="{{ $f }}" class="w-full rounded-lg border-gray-300 text-sm" data-field="{{ $f }}">
                        @error($f)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-5">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Ingredients, one per line: name | grams (for ONE serving)</label>
                    <textarea wire:model="ingredients" rows="6" class="w-full rounded-lg border-gray-300 text-sm font-mono" data-field="ingredients"></textarea>
                    @error('ingredients')<p class="text-xs text-red-600 mt-1" data-error="ingredients">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Steps, one per line</label>
                    <textarea wire:model="steps" rows="6" class="w-full rounded-lg border-gray-300 text-sm" data-field="steps"></textarea>
                    @error('steps')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5 text-sm">
                <fieldset>
                    <legend class="text-xs font-medium text-gray-600 mb-1">Suits these diets</legend>
                    @foreach($dietOptions as $key => $label)
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="diet_tags" value="{{ $key }}" class="rounded border-gray-300"> {{ $label }}</label>
                    @endforeach
                </fieldset>
                <fieldset>
                    <legend class="text-xs font-medium text-gray-600 mb-1">Contains (allergens)</legend>
                    @foreach($allergyOptions as $key => $label)
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="allergens" value="{{ $key }}" class="rounded border-gray-300"> {{ $label }}</label>
                    @endforeach
                </fieldset>
                <fieldset>
                    <legend class="text-xs font-medium text-gray-600 mb-1">Contains (things people avoid)</legend>
                    @foreach($avoidOptions as $key => $label)
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="avoid_tags" value="{{ $key }}" class="rounded border-gray-300"> {{ $label }}</label>
                    @endforeach
                </fieldset>
            </div>

            <label class="flex items-center gap-2 mt-5 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-gray-300"> Switched on</label>
            <p class="text-xs text-gray-500 mt-2">Saving clears the approval. A dietitian approves the recipe from the list before members see it.</p>

            <div class="flex gap-3 mt-4">
                <button type="button" wire:click="save" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg" data-save>Save recipe</button>
                <button type="button" wire:click="cancel" class="px-4 py-2 text-sm text-gray-600">Cancel</button>
            </div>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3 mb-4">
        @foreach(['all' => 'All', 'waiting' => 'Waiting for approval', 'approved' => 'Approved', 'off' => 'Switched off', 'demo' => 'Sample recipes'] as $key => $label)
            <button type="button" wire:click="setFilter('{{ $key }}')" data-filter="{{ $key }}"
                class="px-3 py-1.5 rounded-full text-xs font-medium {{ $filter === $key ? 'bg-zapmed-600 text-white' : 'bg-white border border-gray-200 text-gray-600' }}">{{ $label }}</button>
        @endforeach
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search" class="rounded-lg border-gray-300 text-sm ml-auto">
        <button type="button" wire:click="create" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg" data-new>New recipe</button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 divide-y divide-gray-100">
        @forelse($recipes as $r)
            <div class="px-4 py-3 flex flex-wrap items-center gap-3" data-recipe-row="{{ $r->id }}" wire:key="r-{{ $r->id }}">
                <div class="flex-1 min-w-[200px]">
                    <div class="text-sm font-medium text-gray-900">{{ $r->name }}</div>
                    <div class="text-xs text-gray-500">{{ $slots[$r->slot] ?? $r->slot }} &middot; {{ $r->kcal }} kcal &middot; {{ rtrim(rtrim(number_format($r->protein_g, 1), '0'), '.') }} g protein</div>
                </div>
                @if($r->isDemo())<span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">sample</span>@endif
                @if($r->isApproved())
                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-50 text-green-700" data-status="approved">approved</span>
                @else
                    <span class="text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-700" data-status="waiting">waiting for approval</span>
                @endif
                @unless($r->is_active)<span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">off</span>@endunless
                <div class="flex gap-3 text-xs">
                    @if($canApprove && ! $r->isApproved())
                        <button type="button" wire:click="approve({{ $r->id }})" class="text-green-700 font-medium" data-approve="{{ $r->id }}">Approve</button>
                    @endif
                    <button type="button" wire:click="edit({{ $r->id }})" class="text-zapmed-700 font-medium" data-edit="{{ $r->id }}">Edit</button>
                    <button type="button" wire:click="toggleActive({{ $r->id }})" class="text-gray-600" data-toggle="{{ $r->id }}">{{ $r->is_active ? 'Switch off' : 'Switch on' }}</button>
                    @if(! $r->isApproved() || $r->isDemo())
                        <button type="button" wire:click="delete({{ $r->id }})" wire:confirm="Delete this recipe? It is also removed from member plans and favourites." class="text-red-600" data-delete="{{ $r->id }}">Delete</button>
                    @endif
                </div>
            </div>
        @empty
            <p class="px-4 py-8 text-sm text-gray-400 text-center">No recipes here.</p>
        @endforelse
    </div>
</div>
