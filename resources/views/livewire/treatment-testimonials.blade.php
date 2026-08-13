<div>
    @if($totalCount > 0)
    <section class="py-12 bg-gray-50 rounded-2xl mt-12">
        <div class="max-w-5xl mx-auto px-6">
            <!-- Header -->
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-900">What Our Patients Say</h2>
                <div class="flex items-center justify-center gap-2 mt-2">
                    <div class="flex">
                        @for($i = 1; $i <= 5; $i++)
                        <svg class="w-5 h-5 {{ $i <= round($avgRating) ? 'text-yellow-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        @endfor
                    </div>
                    <span class="text-sm font-semibold text-gray-700">{{ $avgRating }}</span>
                    <span class="text-sm text-gray-500">({{ $totalCount }} {{ $totalCount === 1 ? 'review' : 'reviews' }})</span>
                </div>
            </div>

            <!-- Testimonial Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($testimonials as $testimonial)
                <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
                    <!-- Stars -->
                    <div class="flex mb-3">
                        @for($i = 1; $i <= 5; $i++)
                        <svg class="w-4 h-4 {{ $i <= $testimonial->rating ? 'text-yellow-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        @endfor
                    </div>

                    <!-- Comment -->
                    <p class="text-sm text-gray-700 mb-4 line-clamp-4">"{{ $testimonial->comment }}"</p>

                    <!-- Attribution -->
                    <div class="flex items-center gap-2 pt-3 border-t border-gray-50">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                            {{ $isSensitive || !$testimonial->show_name ? 'bg-gray-100 text-gray-500' : 'bg-zapmed-100 text-zapmed-700' }}">
                            @if($isSensitive || !$testimonial->show_name)
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            @else
                                {{ strtoupper(substr($testimonial->patient->first_name, 0, 1)) }}
                            @endif
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-900">
                                {{ $isSensitive ? 'Verified Patient' : $testimonial->display_name }}
                            </p>
                            <p class="text-xs text-gray-400">
                                <svg class="w-3 h-3 inline text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Verified consultation
                            </p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif
</div>
