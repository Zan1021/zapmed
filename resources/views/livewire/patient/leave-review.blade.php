<div>
    @if($submitted)
        <!-- Success State -->
        <div class="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
            <svg class="w-12 h-12 text-green-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="text-lg font-semibold text-green-800">Thank you for your review!</h3>
            <p class="text-sm text-green-600 mt-1">Your feedback helps other patients and our doctors. It will be visible once approved.</p>
        </div>
    @elseif($alreadyReviewed)
        <!-- Already Reviewed -->
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 text-center">
            <p class="text-sm text-gray-600">You've already left a review for this consultation.</p>
        </div>
    @else
        <!-- Review Form -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Rate Your Experience</h3>
            <p class="text-sm text-gray-500 mb-6">How was your consultation with Dr. {{ $consultation->doctor->last_name }}?</p>

            <!-- Star Rating -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Rating</label>
                <div class="flex items-center gap-1">
                    @for($i = 1; $i <= 5; $i++)
                        <button type="button" wire:click="$set('rating', {{ $i }})"
                            class="focus:outline-none transition-transform hover:scale-110">
                            <svg class="w-8 h-8 {{ $rating >= $i ? 'text-yellow-400' : 'text-gray-300' }}"
                                fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </button>
                    @endfor
                    <span class="ml-2 text-sm text-gray-500">
                        {{ ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'][$rating] }}
                    </span>
                </div>
            </div>

            <!-- Comment -->
            <div class="mb-6">
                <label for="comment" class="block text-sm font-medium text-gray-700 mb-2">Your Review</label>
                <textarea wire:model="comment" id="comment" rows="4"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"
                    placeholder="What was your experience like? How did the doctor help you?"></textarea>
                @error('comment') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <!-- Would Recommend -->
            <div class="mb-6">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model="wouldRecommend" type="checkbox" class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                    <span class="text-sm text-gray-700">I would recommend this doctor to others</span>
                </label>
            </div>

            <!-- Show Name -->
            <div class="mb-6">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model="showName" type="checkbox" class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                    <span class="text-sm text-gray-700">Show my first name with this review (otherwise shown as "Verified Patient")</span>
                </label>
            </div>

            <!-- Submit -->
            <button wire:click="submit" wire:loading.attr="disabled"
                class="w-full bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 px-4 rounded-lg text-sm font-semibold transition-colors disabled:opacity-50">
                <span wire:loading.remove>Submit Review</span>
                <span wire:loading>Submitting...</span>
            </button>
        </div>
    @endif
</div>
