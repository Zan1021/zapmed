<div>
    @if($subscribed)
    <div class="flex items-center gap-2 text-sm text-green-700">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        <span>You're subscribed! Check your inbox.</span>
    </div>
    @else
    <form wire:submit="subscribe" class="flex gap-2">
        <input wire:model="email" type="email" placeholder="Your email address" required
            class="flex-1 rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500 placeholder-gray-400">
        <button type="submit" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-semibold rounded-lg transition-colors whitespace-nowrap">
            Subscribe
        </button>
    </form>
    @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    @endif
</div>
