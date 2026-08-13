<div>
    <x-slot name="header">Help Center Management</x-slot>

    @if(session()->has('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm">
            {{ session('message') }}
        </div>
    @endif
    @if(session()->has('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
            {{ session('error') }}
        </div>
    @endif

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="flex space-x-8">
            <button wire:click="$set('activeTab', 'categories')"
                class="pb-3 px-1 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'categories' ? 'border-zapmed-600 text-zapmed-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Categories
            </button>
            <button wire:click="$set('activeTab', 'articles')"
                class="pb-3 px-1 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'articles' ? 'border-zapmed-600 text-zapmed-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Articles
            </button>
            <button wire:click="$set('activeTab', 'analytics')"
                class="pb-3 px-1 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'analytics' ? 'border-zapmed-600 text-zapmed-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Analytics
            </button>
        </nav>
    </div>

    {{-- CATEGORIES TAB --}}
    @if($activeTab === 'categories')
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Categories</h2>
            <button wire:click="createCategory" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">
                Add Category
            </button>
        </div>

        @if($showCategoryForm)
            <div class="mb-6 bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ $editingCategoryId ? 'Edit' : 'New' }} Category</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input wire:model.live="categoryName" type="text" class="w-full rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        @error('categoryName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                        <input wire:model="categorySlug" type="text" class="w-full rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        @error('categorySlug') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea wire:model="categoryDescription" rows="2" class="w-full rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Icon (SVG path)</label>
                        <input wire:model="categoryIcon" type="text" class="w-full rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input wire:model="categorySortOrder" type="number" min="0" class="w-full rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    </div>
                    <div class="flex items-center gap-2">
                        <input wire:model="categoryIsActive" type="checkbox" id="categoryActive" class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                        <label for="categoryActive" class="text-sm text-gray-700">Active</label>
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button wire:click="saveCategory" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">Save</button>
                    <button wire:click="$set('showCategoryForm', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">Cancel</button>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Articles</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($categories as $category)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    @if($category->icon)
                                        <div class="w-8 h-8 rounded-lg bg-zapmed-50 flex items-center justify-center">
                                            <svg class="w-4 h-4 text-zapmed-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $category->icon }}"/>
                                            </svg>
                                        </div>
                                    @endif
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $category->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $category->slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $category->articles_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $category->sort_order }}</td>
                            <td class="px-6 py-4">
                                <button wire:click="toggleCategoryActive({{ $category->id }})"
                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $category->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button wire:click="editCategory({{ $category->id }})" class="text-sm text-zapmed-600 hover:text-zapmed-700 font-medium">Edit</button>
                                <button wire:click="deleteCategory({{ $category->id }})" wire:confirm="Delete this category?" class="text-sm text-red-600 hover:text-red-700 font-medium">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No categories yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- ARTICLES TAB --}}
    @if($activeTab === 'articles')
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Articles</h2>
            <button wire:click="createArticle" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">
                New Article
            </button>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <input wire:model.live.debounce.300ms="articleSearch" type="text" placeholder="Search articles..."
                class="flex-1 rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            <select wire:model.live="filterCategory" class="rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterStatus" class="rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                <option value="">All Statuses</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
            </select>
        </div>

        @if($showArticleForm)
            <div class="mb-6 bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ $editingArticleId ? 'Edit' : 'New' }} Article</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input wire:model.live="articleTitle" type="text" class="w-full rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        @error('articleTitle') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                        <input wire:model="articleSlug" type="text" class="w-full rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        @error('articleSlug') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select wire:model="articleCategoryId" class="w-full rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                            <option value="">Select category...</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('articleCategoryId') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select wire:model="articleStatus" class="w-full rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Visibility</label>
                            <select wire:model="articleVisibility" class="w-full rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                                <option value="public">Public</option>
                                <option value="internal">Internal</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort</label>
                            <input wire:model="articleSortOrder" type="number" min="0" class="w-full rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Body (HTML)</label>
                        <textarea wire:model="articleBody" rows="10" class="w-full rounded-lg border-gray-200 text-sm font-mono focus:border-zapmed-500 focus:ring-zapmed-500"></textarea>
                        @error('articleBody') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button wire:click="saveArticle" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">Save Article</button>
                    <button wire:click="$set('showArticleForm', false)" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">Cancel</button>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Views</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Helpful</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($articles as $article)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-900">{{ $article->title }}</p>
                                <p class="text-xs text-gray-500">{{ $article->slug }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $article->category->name ?? '—' }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $article->status === 'published' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                    {{ ucfirst($article->status) }}
                                </span>
                                @if($article->visibility === 'internal')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700 ml-1">Internal</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ number_format($article->views) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                @if($article->helpful_percentage !== null)
                                    {{ $article->helpful_percentage }}%
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button wire:click="editArticle({{ $article->id }})" class="text-sm text-zapmed-600 hover:text-zapmed-700 font-medium">Edit</button>
                                @if($article->status === 'draft')
                                    <button wire:click="publishArticle({{ $article->id }})" class="text-sm text-green-600 hover:text-green-700 font-medium">Publish</button>
                                @else
                                    <button wire:click="unpublishArticle({{ $article->id }})" class="text-sm text-yellow-600 hover:text-yellow-700 font-medium">Unpublish</button>
                                @endif
                                <button wire:click="deleteArticle({{ $article->id }})" wire:confirm="Delete this article?" class="text-sm text-red-600 hover:text-red-700 font-medium">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No articles found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $articles->links() }}
        </div>
    @endif

    {{-- ANALYTICS TAB --}}
    @if($activeTab === 'analytics')
        @php $analytics = $this->analytics; @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <p class="text-sm text-gray-500">Total Views This Month</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($analytics['totalViewsThisMonth']) }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <p class="text-sm text-gray-500">Total Articles</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ \App\Models\HelpArticle::count() }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <p class="text-sm text-gray-500">Published Articles</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ \App\Models\HelpArticle::published()->count() }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top Searches -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Top Searched Queries</h3>
                @if($analytics['topSearches']->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($analytics['topSearches'] as $search)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-700">{{ $search->query }}</span>
                                <span class="text-gray-500">{{ $search->search_count }}x</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500">No search data yet.</p>
                @endif
            </div>

            <!-- Zero Result Searches -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Searches with 0 Results (Content Gaps)</h3>
                @if($analytics['zeroResultSearches']->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($analytics['zeroResultSearches'] as $search)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-700">{{ $search->query }}</span>
                                <span class="text-red-500">{{ $search->search_count }}x</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500">No content gaps found.</p>
                @endif
            </div>

            <!-- Most Viewed -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Most Viewed Articles</h3>
                @if($analytics['mostViewed']->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($analytics['mostViewed'] as $article)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-700 truncate mr-2">{{ $article->title }}</span>
                                <span class="text-gray-500 flex-shrink-0">{{ number_format($article->views) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500">No view data yet.</p>
                @endif
            </div>

            <!-- Least Helpful -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Lowest Helpfulness Rating</h3>
                @if($analytics['leastHelpful']->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($analytics['leastHelpful'] as $article)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-700 truncate mr-2">{{ $article->title }}</span>
                                <span class="text-red-500 flex-shrink-0">{{ $article->helpful_percentage }}%</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500">No feedback data yet.</p>
                @endif
            </div>
        </div>
    @endif
</div>
