<div>
    @include('partials.public-nav')

    <div class="mt-16 py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-12">
                <h1 class="text-3xl sm:text-4xl font-bold text-gray-900">Health & Wellness Articles</h1>
                <p class="mt-4 text-lg text-gray-500">Expert insights on weight loss, skincare, sexual health, and more — by Zapmed doctors.</p>
            </div>

            <!-- Category Filter -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-12">
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <button wire:click="filterCategory(null)" class="px-4 py-2 rounded-full text-sm font-medium transition-colors {{ !$category ? 'bg-zapmed-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        All
                    </button>
                    @foreach($categories as $cat)
                        @if($cat->posts_count > 0)
                        <button wire:click="filterCategory('{{ $cat->slug }}')" class="px-4 py-2 rounded-full text-sm font-medium transition-colors {{ $category === $cat->slug ? 'bg-zapmed-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                            {{ $cat->name }}
                        </button>
                        @endif
                    @endforeach
                </div>
                <div class="w-full sm:w-auto">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search articles..." class="w-full sm:w-64 rounded-full border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500 px-5 py-2">
                </div>
            </div>

            <!-- Posts Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @forelse($posts as $post)
                <article class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden group hover:shadow-lg transition-shadow">
                    <!-- Featured Image -->
                    <a href="/blog/{{ $post->slug }}" class="block aspect-[16/10] overflow-hidden bg-gray-100">
                        @if($post->featured_image_url)
                            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                        @else
                            <div class="w-full h-full bg-gradient-to-br from-zapmed-100 to-zapmed-50 flex items-center justify-center">
                                <svg class="w-12 h-12 text-zapmed-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                            </div>
                        @endif
                    </a>

                    <!-- Content -->
                    <div class="p-6">
                        <!-- Category Badge -->
                        <span class="inline-block px-3 py-1 text-xs font-medium bg-zapmed-50 text-zapmed-700 rounded-full mb-3">
                            {{ $post->category->name }}
                        </span>

                        <a href="/blog/{{ $post->slug }}" class="block">
                            <h2 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-zapmed-600 transition-colors line-clamp-2">{{ $post->title }}</h2>
                        </a>

                        <p class="text-sm text-gray-500 leading-relaxed line-clamp-3 mb-4">
                            {{ $post->excerpt ?: Str::limit(strip_tags($post->body), 150) }}
                        </p>

                        <div class="flex items-center justify-between text-xs text-gray-400">
                            <span>{{ $post->published_at->format('d M Y') }}</span>
                            <span>{{ $post->reading_time }} min read</span>
                        </div>
                    </div>
                </article>
                @empty
                <div class="col-span-3 text-center py-16">
                    <p class="text-lg text-gray-500">No articles published yet. Check back soon!</p>
                </div>
                @endforelse
            </div>

            <!-- Pagination -->
            <div class="mt-12">
                {{ $posts->links() }}
            </div>
        </div>
    </div>

    @include('partials.public-footer')
</div>
