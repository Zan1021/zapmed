<div>
    @include('partials.public-nav')

    <div class="pt-24 pb-16 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
        <!-- Doctor Header -->
        <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-8">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6">
                <!-- Avatar -->
                <div class="w-20 h-20 bg-zapmed-100 rounded-2xl flex items-center justify-center shrink-0">
                    <span class="text-2xl font-bold text-zapmed-600">
                        {{ strtoupper(substr($doctor->first_name, 0, 1)) }}{{ strtoupper(substr($doctor->last_name, 0, 1)) }}
                    </span>
                </div>

                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900">Dr. {{ $doctor->first_name }} {{ $doctor->last_name }}</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ $profile->speciality }} &middot; {{ $profile->qualification }}</p>

                    <!-- Rating -->
                    @if($profile->total_reviews > 0)
                        <div class="flex items-center gap-2 mt-3">
                            <div class="flex items-center">
                                @foreach($profile->stars as $star)
                                    @if($star === 'full')
                                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    @elseif($star === 'half')
                                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    @else
                                        <svg class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    @endif
                                @endforeach
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ $profile->formatted_rating }}</span>
                            <span class="text-sm text-gray-500">({{ $profile->total_reviews }} {{ Str::plural('review', $profile->total_reviews) }})</span>
                        </div>
                    @else
                        <p class="text-sm text-gray-400 mt-3">No reviews yet</p>
                    @endif
                </div>

                <!-- Book Button -->
                <a href="{{ route('patient.book') }}" class="shrink-0 bg-zapmed-600 hover:bg-zapmed-700 text-white px-6 py-3 rounded-xl text-sm font-semibold transition-colors">
                    Book Consultation
                </a>
            </div>

            <!-- Bio -->
            @if($profile->bio)
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $profile->bio }}</p>
                </div>
            @endif

            <!-- Quick Stats -->
            <div class="mt-6 pt-6 border-t border-gray-100 grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="text-center">
                    <p class="text-lg font-bold text-gray-900">{{ $profile->formatted_fee }}</p>
                    <p class="text-xs text-gray-500">Consultation</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-bold text-gray-900">{{ $profile->consultation_duration }} min</p>
                    <p class="text-xs text-gray-500">Duration</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-bold text-gray-900">{{ $profile->year_qualified ? now()->year - $profile->year_qualified : '—' }}+</p>
                    <p class="text-xs text-gray-500">Years Experience</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-bold text-gray-900">{{ $profile->university ?? '—' }}</p>
                    <p class="text-xs text-gray-500">University</p>
                </div>
            </div>
        </div>

        <!-- Rating Breakdown -->
        @if($profile->total_reviews > 0)
            <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Rating Breakdown</h2>
                <div class="space-y-2">
                    @foreach($this->ratingBreakdown as $stars => $data)
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-gray-600 w-12">{{ $stars }} star</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                <div class="bg-yellow-400 h-full rounded-full transition-all" style="width: {{ $data['percentage'] }}%"></div>
                            </div>
                            <span class="text-sm text-gray-500 w-8 text-right">{{ $data['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Reviews List -->
        <div class="bg-white rounded-2xl border border-gray-200 p-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-6">Patient Reviews</h2>

            @forelse($this->reviews as $review)
                <div class="pb-6 mb-6 border-b border-gray-100 last:border-0 last:pb-0 last:mb-0">
                    <div class="flex items-center gap-3 mb-2">
                        <!-- Stars -->
                        <div class="flex items-center">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-4 h-4 {{ $review->rating >= $i ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            @endfor
                        </div>
                        <span class="text-xs text-gray-400">{{ $review->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-sm text-gray-700 leading-relaxed">{{ $review->comment }}</p>
                    <p class="text-xs text-gray-400 mt-2">— {{ $review->display_name }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500 text-center py-8">No reviews yet. Be the first to leave one after your consultation!</p>
            @endforelse

            {{ $this->reviews->links() }}
        </div>
    </div>

    @include('partials.public-footer')
</div>
