<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Add-ons</h1>
        <p class="mt-1 text-sm text-gray-500">Extras that can be put on an order, such as health coach support. The price is copied onto an order when the add-on is added, so changing it here never changes an existing order. Leave the categories empty to offer it on every order.</p>
    </div>

    <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3 text-left">Name</th><th class="px-4 py-3 text-right">Per month</th><th class="px-4 py-3 text-left">Offered for</th><th class="px-4 py-3 text-center">On</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($addons as $a)
                    <tr data-addon="{{ $a->id }}">
                        <td class="px-4 py-2 text-gray-900">{{ $a->name }}</td>
                        <td class="px-4 py-2 text-right">R{{ number_format($a->price_cents / 100, 2) }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $a->categories ? implode(', ', $a->categories) : 'Every order' }}</td>
                        <td class="px-4 py-2 text-center">{{ $a->is_active ? 'On' : 'Off' }}</td>
                        <td class="px-4 py-2 text-right"><button type="button" wire:click="edit({{ $a->id }})" data-action="edit" class="text-indigo-600">Edit</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400" data-empty="addons">No add-ons yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @foreach(['name', 'price', 'categories'] as $f) @error($f) <p class="mb-2 text-sm text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach

    <div class="rounded-xl border border-gray-200 bg-white p-4" data-form="addon">
        <h2 class="mb-3 text-sm font-semibold uppercase text-gray-500">{{ $editing ? 'Edit add-on' : 'Add an add-on' }}</h2>
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <label>Name <input type="text" wire:model="name" class="mt-1 block w-56 rounded-lg border-gray-300 text-sm"></label>
            <label>Per month (R) <input type="text" wire:model="price" class="mt-1 block w-28 rounded-lg border-gray-300 text-sm"></label>
            <label>Offered for (words, comma separated) <input type="text" wire:model="categories" placeholder="weight" class="mt-1 block w-64 rounded-lg border-gray-300 text-sm"></label>
            <label class="flex items-center"><input type="checkbox" wire:model="active" class="mr-2 rounded border-gray-300"> On</label>
            <button type="button" wire:click="save" data-action="save" class="rounded-lg bg-indigo-600 px-4 py-2 text-white">Save</button>
            @if($editing)<button type="button" wire:click="cancel" class="text-gray-500">Cancel</button>@endif
        </div>
    </div>
</div>
