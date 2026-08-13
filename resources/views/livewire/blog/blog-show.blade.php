<div>
    @include('partials.public-nav')

    <article class="mt-16 py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <!-- Breadcrumb -->
            <nav class="mb-8 text-sm text-gray-500">
                <a href="/" class="hover:text-zapmed-600">Home</a>
                <span class="mx-2">/</span>
                <a href="/blog" class="hover:text-zapmed-600">Blog</a>
                <span class="mx-2">/</span>
                <a href="/blog?category={{ $post->category->slug }}" class="hover:text-zapmed-600">{{ $post->category->name }}</a>
                <span class="mx-2">/</span>
                <span class="text-gray-900">{{ Str::limit($post->title, 40) }}</span>
            </nav>

            <!-- Category Badge -->
            <span class="inline-block px-3 py-1 text-xs font-medium bg-zapmed-50 text-zapmed-700 rounded-full mb-4">
                {{ $post->category->name }}
            </span>

            <!-- Title -->
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 leading-tight mb-4">{{ $post->title }}</h1>

            <!-- Meta -->
            <div class="flex items-center gap-4 text-sm text-gray-500 mb-8">
                @if($post->author)
                <span>By {{ $post->author->name }}</span>
                <span>&middot;</span>
                @endif
                <span>{{ $post->published_at->format('d M Y') }}</span>
                <span>&middot;</span>
                <span>{{ $post->reading_time }} min read</span>
            </div>

            <!-- Featured Image -->
            @if($post->featured_image_url)
            <div class="rounded-2xl overflow-hidden mb-10">
                <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="w-full h-auto object-cover">
            </div>
            @endif

            <!-- Body -->
            <div class="prose prose-lg prose-gray max-w-none
                prose-headings:text-gray-900 prose-headings:font-bold
                prose-a:text-zapmed-600 prose-a:no-underline hover:prose-a:underline
                prose-img:rounded-xl
                prose-strong:text-gray-900">
                {!! $post->body !!}
            </div>

            <!-- Share -->
            <div class="mt-12 pt-8 border-t border-gray-100">
                <p class="text-sm font-semibold text-gray-700 mb-3">Share this article</p>
                <div class="flex items-center gap-3">
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($post->url) }}" target="_blank" rel="noopener" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-zapmed-50 flex items-center justify-center text-gray-500 hover:text-zapmed-600 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url={{ urlencode($post->url) }}&text={{ urlencode($post->title) }}" target="_blank" rel="noopener" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-zapmed-50 flex items-center justify-center text-gray-500 hover:text-zapmed-600 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($post->url) }}" target="_blank" rel="noopener" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-zapmed-50 flex items-center justify-center text-gray-500 hover:text-zapmed-600 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </article>

    <!-- Related Posts -->
    @if($relatedPosts->count() > 0)
    <section class="py-16 bg-gray-50 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <h2 class="text-2xl font-bold text-gray-900 mb-8">Related Articles</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach($relatedPosts as $related)
                <a href="/blog/{{ $related->slug }}" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition-shadow group">
                    <div class="aspect-[16/10] overflow-hidden bg-gray-100">
                        @if($related->featured_image_url)
                            <img src="{{ $related->featured_image_url }}" alt="{{ $related->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @endif
                    </div>
                    <div class="p-5">
                        <span class="text-xs font-medium text-zapmed-600">{{ $related->category->name }}</span>
                        <h3 class="text-sm font-bold text-gray-900 mt-1 line-clamp-2 group-hover:text-zapmed-600 transition-colors">{{ $related->title }}</h3>
                        <p class="text-xs text-gray-400 mt-2">{{ $related->published_at->format('d M Y') }}</p>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    @include('partials.public-footer')

    {{-- JSON-LD Structured Data --}}
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => $post->meta_description_tag,
        'image' => $post->og_image_url,
        'datePublished' => $post->published_at->toISOString(),
        'dateModified' => $post->updated_at->toISOString(),
        'author' => [
            '@type' => 'Organization',
            'name' => 'Zapmed',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Zapmed',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset('/images/zapmed-logo.png'),
            ],
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $post->url,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
    </script>
</div>
