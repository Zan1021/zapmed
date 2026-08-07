<div>
    <!-- Header -->
    <div class="text-center mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $treatmentName }} Assessment</h1>
        <p class="mt-2 text-sm text-gray-500">Please answer all questions honestly so our doctors can provide the best care.</p>
        <p class="mt-1 text-xs text-gray-400">{{ count($questions) }} questions &middot; Takes about 2 minutes</p>
    </div>

    <form wire:submit="submitAssessment" class="space-y-6">
        @foreach($questions as $index => $question)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 sm:p-6"
                 @if(isset($question['prefill']) && $question['prefill'] === 'treatment_name') x-data="{ hidden: true }" x-show="!hidden" @endif>

                <!-- Question number & text -->
                <div class="flex items-start gap-3 mb-4">
                    <span class="flex-shrink-0 w-7 h-7 bg-zapmed-100 text-zapmed-700 rounded-full flex items-center justify-center text-xs font-bold">
                        {{ $index + 1 }}
                    </span>
                    <label class="text-sm font-medium text-gray-900 pt-0.5">
                        {{ $question['text'] }}
                        @if($question['required'])
                            <span class="text-red-500">*</span>
                        @endif
                    </label>
                </div>

                <!-- Input based on type -->
                <div class="pl-10">
                    @if($question['type'] === 'text')
                        <input
                            type="text"
                            wire:model="answers.{{ $question['id'] }}"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"
                            placeholder="Type your answer..."
                        >

                    @elseif($question['type'] === 'textarea')
                        <textarea
                            wire:model="answers.{{ $question['id'] }}"
                            rows="3"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"
                            placeholder="Type your answer..."
                        ></textarea>

                    @elseif($question['type'] === 'select')
                        <select
                            wire:model="answers.{{ $question['id'] }}"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"
                        >
                            <option value="">Select an option...</option>
                            @foreach($question['options'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>

                    @elseif($question['type'] === 'radio')
                        <div class="flex flex-wrap gap-3">
                            @foreach($question['options'] as $option)
                                <label class="inline-flex items-center cursor-pointer">
                                    <input
                                        type="radio"
                                        wire:model="answers.{{ $question['id'] }}"
                                        value="{{ $option }}"
                                        class="text-zapmed-600 border-gray-300 focus:ring-zapmed-500"
                                    >
                                    <span class="ml-2 text-sm text-gray-700">{{ $option }}</span>
                                </label>
                            @endforeach
                        </div>

                    @elseif($question['type'] === 'checkbox')
                        <div class="flex flex-wrap gap-3">
                            @foreach($question['options'] as $option)
                                <label class="inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model="answers.{{ $question['id'] }}"
                                        value="{{ $option }}"
                                        class="rounded text-zapmed-600 border-gray-300 focus:ring-zapmed-500"
                                    >
                                    <span class="ml-2 text-sm text-gray-700">{{ $option }}</span>
                                </label>
                            @endforeach
                        </div>

                    @elseif($question['type'] === 'image')
                        <div>
                            <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:border-zapmed-300 transition-colors">
                                <input type="file" wire:model="photoUploads.{{ $question['id'] }}" multiple accept="image/*"
                                    class="hidden" id="photo-{{ $question['id'] }}">
                                <label for="photo-{{ $question['id'] }}" class="cursor-pointer">
                                    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <p class="text-sm text-gray-600 font-medium">Click to upload photos</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ $question['hint'] ?? 'Up to 3 images, max 5MB each (JPG, PNG)' }}</p>
                                </label>
                            </div>

                            <!-- Preview uploaded photos -->
                            @if(isset($photoUploads[$question['id']]) && is_array($photoUploads[$question['id']]))
                                <div class="mt-3 grid grid-cols-3 gap-3">
                                    @foreach($photoUploads[$question['id']] as $photo)
                                        <div class="relative rounded-lg overflow-hidden bg-gray-100 aspect-square">
                                            <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover" alt="Upload preview">
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div wire:loading wire:target="photoUploads.{{ $question['id'] }}" class="mt-2">
                                <p class="text-xs text-zapmed-600 animate-pulse">Uploading...</p>
                            </div>
                        </div>
                    @endif

                    @error("answers.{$question['id']}")
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        @endforeach

        <!-- Submit -->
        <div class="pt-4">
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="w-full bg-zapmed-600 hover:bg-zapmed-700 disabled:opacity-50 text-white py-4 rounded-xl text-base font-semibold transition-all shadow-lg shadow-zapmed-200 hover:shadow-xl"
            >
                <span wire:loading.remove>Submit Assessment</span>
                <span wire:loading class="inline-flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Submitting...
                </span>
            </button>
        </div>

        <p class="text-center text-xs text-gray-400 mt-4">
            Your answers are confidential and will only be shared with your consulting doctor.
        </p>
    </form>
</div>
