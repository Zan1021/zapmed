<div>
    <div class="flex items-center gap-6">
        <!-- Avatar Preview -->
        <div class="relative">
            @if($currentAvatarUrl)
                <img src="{{ $currentAvatarUrl }}" alt="Profile Photo" class="w-20 h-20 rounded-full object-cover border-2 border-gray-200">
            @else
                <div class="w-20 h-20 rounded-full bg-zapmed-600 flex items-center justify-center">
                    <span class="text-2xl font-semibold text-white">{{ auth()->user()->initials }}</span>
                </div>
            @endif

            <!-- Upload overlay on hover -->
            <label for="avatar-upload" class="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity cursor-pointer">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </label>
        </div>

        <!-- Upload Controls -->
        <div>
            <input type="file" wire:model="avatar" id="avatar-upload" class="hidden" accept="image/jpeg,image/png,image/webp">

            <label for="avatar-upload" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 cursor-pointer transition-colors">
                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Upload Photo
            </label>

            @if($currentAvatarUrl)
                <button wire:click="removeAvatar" class="ml-2 inline-flex items-center px-3 py-2 text-sm font-medium text-red-600 hover:text-red-700 transition-colors">
                    Remove
                </button>
            @endif

            <p class="mt-1.5 text-xs text-gray-500">JPG, PNG or WebP. Max 2MB.</p>

            @error('avatar')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror

            @if(session('avatar-success'))
                <p class="mt-1 text-xs text-green-600">{{ session('avatar-success') }}</p>
            @endif
        </div>
    </div>

    <!-- Loading state -->
    <div wire:loading wire:target="avatar" class="mt-3">
        <div class="flex items-center text-sm text-gray-500">
            <svg class="animate-spin w-4 h-4 mr-2 text-zapmed-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Uploading...
        </div>
    </div>
</div>
