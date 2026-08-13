<div>
    @include('partials.public-nav')

    <!-- Hero -->
    <section class="mt-16 pt-16 pb-12 bg-gradient-to-b from-zapmed-50 to-white px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900">Help Centre</h1>
            <p class="mt-4 text-lg text-gray-500">Find answers to your questions about Zapmed.</p>

            <!-- Search -->
            <div class="mt-8 max-w-xl mx-auto">
                <div class="relative">
                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input wire:model.live.debounce.300ms="search" type="text"
                        placeholder="Search for answers..."
                        class="w-full rounded-xl border-gray-200 shadow-sm pl-12 pr-4 py-4 text-base focus:border-zapmed-500 focus:ring-zapmed-500">
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto">

            {{-- Search Results --}}
            @if($searchResults !== null)
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Search Results</h2>
                        <span class="text-sm text-gray-500">({{ $searchResults->count() }} {{ Str::plural('result', $searchResults->count()) }})</span>
                    </div>

                    @if($searchResults->isEmpty())
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-100 shadow-sm">
                            <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="mt-4 text-gray-500">No articles found for "{{ $search }}"</p>
                            <p class="mt-1 text-sm text-gray-400">Try different keywords or browse categories below.</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($searchResults as $article)
                                <a href="{{ route('help.show', $article->slug) }}" wire:navigate
                                   class="block bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:border-zapmed-200 hover:shadow-md transition-all">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h3 class="text-sm font-semibold text-gray-900">{{ $article->title }}</h3>
                                            <p class="mt-1 text-sm text-gray-500 line-clamp-2">{{ $article->excerpt_text }}</p>
                                            <span class="mt-2 inline-block text-xs text-zapmed-600 font-medium">{{ $article->category->name ?? '' }}</span>
                                        </div>
                                        <svg class="w-5 h-5 text-gray-300 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

            {{-- Category Articles --}}
            @elseif($selectedCategoryModel)
                <div class="mb-6">
                    <button wire:click="clearCategory" class="inline-flex items-center gap-1 text-sm text-zapmed-600 hover:text-zapmed-700 font-medium mb-4">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back to all categories
                    </button>

                    <div class="flex items-center gap-3 mb-6">
                        @if($selectedCategoryModel->icon)
                            <div class="w-10 h-10 rounded-xl bg-zapmed-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-zapmed-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $selectedCategoryModel->icon }}"/>
                                </svg>
                            </div>
                        @endif
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">{{ $selectedCategoryModel->name }}</h2>
                            @if($selectedCategoryModel->description)
                                <p class="text-sm text-gray-500">{{ $selectedCategoryModel->description }}</p>
                            @endif
                        </div>
                    </div>

                    @if($categoryArticles && $categoryArticles->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($categoryArticles as $article)
                                <a href="{{ route('help.show', $article->slug) }}" wire:navigate
                                   class="block bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:border-zapmed-200 hover:shadow-md transition-all">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="text-sm font-semibold text-gray-900">{{ $article->title }}</h3>
                                            <p class="mt-1 text-sm text-gray-500 line-clamp-2">{{ $article->excerpt_text }}</p>
                                        </div>
                                        <svg class="w-5 h-5 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 py-8 text-center">No articles in this category yet.</p>
                    @endif
                </div>

            {{-- Category Grid --}}
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($categories as $category)
                        <button wire:click="selectCategory('{{ $category->slug }}')"
                            class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 text-left hover:border-zapmed-200 hover:shadow-md transition-all group">
                            <div class="flex items-start justify-between">
                                @if($category->icon)
                                    <div class="w-10 h-10 rounded-xl bg-zapmed-50 group-hover:bg-zapmed-100 flex items-center justify-center transition-colors">
                                        <svg class="w-5 h-5 text-zapmed-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $category->icon }}"/>
                                        </svg>
                                    </div>
                                @endif
                                @if($category->articles_count > 0)
                                    <span class="text-xs font-medium text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">{{ $category->articles_count }}</span>
                                @endif
                            </div>
                            <h3 class="mt-4 text-sm font-semibold text-gray-900 group-hover:text-zapmed-700 transition-colors">{{ $category->name }}</h3>
                            @if($category->description)
                                <p class="mt-1 text-sm text-gray-500 line-clamp-2">{{ $category->description }}</p>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif

            <!-- Still need help? -->
            <div class="mt-16 text-center p-8 bg-zapmed-50 rounded-2xl border border-zapmed-100">
                <h3 class="text-lg font-bold text-gray-900">Still have questions?</h3>
                <p class="text-sm text-gray-600 mt-2">Our team is here to help.</p>
                <a href="mailto:support@zapmed.co.za" class="inline-flex items-center mt-4 px-5 py-2.5 bg-zapmed-600 hover:bg-zapmed-700 text-white rounded-lg text-sm font-semibold transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Email Support
                </a>
            </div>
        </div>
    </section>

    @include('partials.public-footer')
</div>
