<div>
    @if($showPopup)
    <div x-data="{ open: true }" x-show="open" x-transition
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-cloak>
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50" @click="open = false; $wire.dismiss()"></div>

        <!-- Modal -->
        <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 z-10">
            @if($submitted)
                <!-- Thank you state -->
                <div class="text-center py-4">
                    <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Thank you!</h3>
                    <p class="text-sm text-gray-500 mt-1">Your feedback helps other patients find the care they need.</p>
                    <button @click="open = false" class="mt-4 text-sm text-zapmed-600 hover:text-zapmed-700 font-medium">Close</button>
                </div>
            @else
                <!-- Collection form -->
                <button @click="open = false; $wire.dismiss()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <h3 class="text-lg font-bold text-gray-900 mb-1">How was your consultation?</h3>
                <p class="text-sm text-gray-500 mb-5">Your honest feedback helps us improve.</p>

                <!-- Star Rating -->
                <div class="mb-5">
                    <div class="flex items-center gap-1">
                        @for($i = 1; $i <= 5; $i++)
                        <button wire:click="setRating({{ $i }})" type="button"
                            class="p-0.5 transition-transform hover:scale-110">
                            <svg class="w-8 h-8 {{ $rating >= $i ? 'text-yellow-400' : 'text-gray-200' }}"
                                 fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </button>
                        @endfor
                    </div>
                    @if($rating > 0)
                    <p class="text-xs text-gray-500 mt-1">
                        {{ ['', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'][$rating] }}
                    </p>
                    @endif
                </div>

                <!-- Comment -->
                <div class="mb-4">
                    <textarea wire:model="comment" rows="3"
                        placeholder="What was your experience like? (min 10 characters)"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"></textarea>
                    @error('comment') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Would recommend -->
                <label class="flex items-center gap-2 mb-3 cursor-pointer">
                    <input wire:model="wouldRecommend" type="checkbox" class="rounded text-zapmed-600 focus:ring-zapmed-500 border-gray-300">
                    <span class="text-sm text-gray-700">I would recommend Zapmed</span>
                </label>

                <!-- Show name -->
                <label class="flex items-center gap-2 mb-5 cursor-pointer">
                    <input wire:model="showName" type="checkbox" class="rounded text-zapmed-600 focus:ring-zapmed-500 border-gray-300">
                    <span class="text-sm text-gray-700">Show my first name (otherwise displayed as "Verified Patient")</span>
                </label>

                @error('rating') <p class="text-xs text-red-600 mb-3">Please select a rating.</p> @enderror

                <button wire:click="submit"
                    wire:loading.attr="disabled"
                    class="w-full bg-zapmed-600 hover:bg-zapmed-700 disabled:bg-zapmed-300 text-white py-2.5 rounded-lg text-sm font-semibold transition-colors">
                    <span wire:loading.remove wire:target="submit">Submit Feedback</span>
                    <span wire:loading wire:target="submit">Submitting...</span>
                </button>

                <p class="text-center text-xs text-gray-400 mt-3">You can skip this — it won't affect your care.</p>
            @endif
        </div>
    </div>
    @endif
</div>
