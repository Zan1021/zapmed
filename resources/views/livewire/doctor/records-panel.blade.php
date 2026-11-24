<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5" data-panel="records">
    <h3 class="text-sm font-semibold text-gray-900 mb-3">Records and results</h3>
    @forelse($records as $r)
        <p class="text-xs text-gray-700 mb-1" data-record="{{ $r->id }}">
            <a class="text-zapmed-700 hover:underline" href="{{ route('health-records.show', $r) }}">{{ $r->title }}</a>
            <span class="text-gray-400">{{ $r->uploader_role === 'patient' ? 'from the patient' : 'from a doctor' }} &middot; {{ $r->created_at->format('j M Y') }}</span>
        </p>
    @empty
        <p class="text-xs text-gray-400 mb-2">Nothing uploaded.</p>
    @endforelse

    <label class="block text-xs text-gray-500 mt-3 mb-1">Add a result for the patient</label>
    <input type="text" wire:model="title" maxlength="120" placeholder="Name of the document" class="w-full text-sm border-gray-300 rounded-lg mb-2">
    <input type="file" wire:model="file" class="text-xs mb-2">
    @foreach(['title', 'file'] as $f) @error($f) <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror @endforeach
    <button type="button" wire:click="upload" data-action="upload-result" class="px-3 py-1.5 bg-zapmed-600 text-white text-xs font-medium rounded-lg">Upload</button>
</div>
