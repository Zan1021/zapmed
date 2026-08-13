<div>
    @include('partials.public-nav')

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
        {!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

    <section class="mt-16 pt-8 pb-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto">

            <!-- Breadcrumb -->
            <nav class="mb-8">
                <ol class="flex items-center space-x-2 text-sm">
                    <li><a href="{{ route('help') }}" wire:navigate class="text-gray-500 hover:text-zapmed-600 transition-colors">Help</a></li>
                    <li><svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
                    <li><a href="{{ route('help') }}?category={{ $article->category->slug }}" wire:click.prevent="$dispatch('selectCategory', { slug: '{{ $article->category->slug }}' })" class="text-gray-500 hover:text-zapmed-600 transition-colors">{{ $article->category->name }}</a></li>
                    <li><svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></li>
                    <li class="text-gray-900 font-medium truncate">{{ $article->title }}</li>
                </ol>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-3">
                    <article class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $article->title }}</h1>

                        @if($article->published_at)
                            <p class="mt-2 text-sm text-gray-400">Updated {{ $article->updated_at->format('M d, Y') }}</p>
                        @endif

                        <div class="mt-8 prose prose-gray max-w-none prose-headings:font-semibold prose-a:text-zapmed-600 prose-a:no-underline hover:prose-a:underline">
                            {!! $article->body !!}
                        </div>
                    </article>

                    <!-- Was this helpful? -->
                    <div class="mt-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        @if($hasVoted)
                            <div class="text-center">
                                <p class="text-sm font-medium text-gray-900">Thanks for your feedback!</p>
                                <p class="text-xs text-gray-500 mt-1">Your response helps us improve our help articles.</p>
                            </div>
                        @else
                            <div class="text-center">
                                <p class="text-sm font-medium text-gray-900 mb-3">Was this article helpful?</p>
                                <div class="flex items-center justify-center gap-3">
                                    <button wire:click="vote(true)"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-green-50 hover:border-green-200 hover:text-green-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/>
                                        </svg>
                                        Yes
                                        @if($article->helpful_yes > 0)
                                            <span class="text-xs text-gray-400">({{ $article->helpful_yes }})</span>
                                        @endif
                                    </button>
                                    <button wire:click="vote(false)"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-red-50 hover:border-red-200 hover:text-red-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018a2 2 0 01.485.06l3.76.94m-7 10v5a2 2 0 002 2h.096c.5 0 .905-.405.905-.904 0-.715.211-1.413.608-2.008L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/>
                                        </svg>
                                        No
                                        @if($article->helpful_no > 0)
                                            <span class="text-xs text-gray-400">({{ $article->helpful_no }})</span>
                                        @endif
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Sidebar: Related Articles -->
                <div class="lg:col-span-1">
                    <div class="sticky top-24">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                            <h3 class="text-sm font-semibold text-gray-900 mb-3">Related Articles</h3>
                            @if($relatedArticles->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach($relatedArticles as $related)
                                        <a href="{{ route('help.show', $related->slug) }}" wire:navigate
                                           class="block text-sm text-gray-600 hover:text-zapmed-600 transition-colors py-1">
                                            {{ $related->title }}
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-400">No related articles.</p>
                            @endif
                        </div>

                        <a href="{{ route('help') }}" wire:navigate
                           class="mt-4 block text-center text-sm text-zapmed-600 hover:text-zapmed-700 font-medium">
                            &larr; Back to Help Centre
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @include('partials.public-footer')
</div>
