<div>
    <x-slot name="header">Blog Management</x-slot>

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('message') }}</div>
    @endif

    @if(!$showForm)
    <!-- List View -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <div class="flex gap-2">
            <button wire:click="$set('statusFilter', 'all')" class="px-3 py-1.5 text-sm rounded-lg {{ $statusFilter === 'all' ? 'bg-zapmed-100 text-zapmed-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">All</button>
            <button wire:click="$set('statusFilter', 'published')" class="px-3 py-1.5 text-sm rounded-lg {{ $statusFilter === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">Published</button>
            <button wire:click="$set('statusFilter', 'draft')" class="px-3 py-1.5 text-sm rounded-lg {{ $statusFilter === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">Drafts</button>
        </div>
        <div class="flex gap-3">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search posts..." class="rounded-lg border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            <button wire:click="create" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-semibold rounded-lg transition-colors">+ New Post</button>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Title</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Category</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Views</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Date</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($posts as $post)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ Str::limit($post->title, 50) }}</p>
                        <p class="text-xs text-gray-400">{{ $post->reading_time }} min read</p>
                    </td>
                    <td class="px-6 py-4"><span class="text-xs px-2 py-0.5 bg-gray-100 rounded-full text-gray-600">{{ $post->category->name ?? 'None' }}</span></td>
                    <td class="px-6 py-4">
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $post->status === 'published' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">{{ ucfirst($post->status) }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ number_format($post->views) }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $post->published_at?->format('d M Y') ?? '-' }}</td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <button wire:click="togglePublish({{ $post->id }})" class="text-xs text-gray-500 hover:text-zapmed-600">{{ $post->isPublished() ? 'Unpublish' : 'Publish' }}</button>
                        <button wire:click="edit({{ $post->id }})" class="text-xs text-zapmed-600 hover:text-zapmed-700 font-medium">Edit</button>
                        <button wire:click="delete({{ $post->id }})" wire:confirm="Delete this post?" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">No posts yet. Create your first one!</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-3 border-t border-gray-100">{{ $posts->links() }}</div>
    </div>

    @else
    <!-- Create/Edit Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-gray-900">{{ $editingId ? 'Edit Post' : 'New Post' }}</h3>
            <button wire:click="cancelForm" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
        </div>

        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" wire:model.live="title" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500">
                        @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                        <input type="text" wire:model="slug" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm text-gray-500">
                        @error('slug') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Excerpt</label>
                        <textarea wire:model="excerpt" rows="2" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm" placeholder="Brief summary for listing pages..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Body *</label>
                        <textarea wire:model="body" rows="15" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm font-mono" placeholder="Write your blog post content here... HTML is supported."></textarea>
                        @error('body') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select wire:model="blog_category_id" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            <option value="">Select...</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('blog_category_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select wire:model="status" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Featured Image</label>
                        @if($existing_image)
                            <img src="{{ asset('storage/' . $existing_image) }}" class="w-full h-32 object-cover rounded-lg mb-2">
                        @endif
                        <input type="file" wire:model="featured_image" accept="image/*" class="w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-zapmed-50 file:text-zapmed-700">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Meta Title (SEO)</label>
                        <input type="text" wire:model="meta_title" maxlength="70" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm" placeholder="Max 70 chars">
                        <p class="text-xs text-gray-400 mt-1">{{ strlen($meta_title) }}/70</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Meta Description (SEO)</label>
                        <textarea wire:model="meta_description" maxlength="160" rows="3" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm" placeholder="Max 160 chars"></textarea>
                        <p class="text-xs text-gray-400 mt-1">{{ strlen($meta_description) }}/160</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" wire:click="cancelForm" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-semibold rounded-lg transition-colors">
                    {{ $editingId ? 'Update Post' : 'Create Post' }}
                </button>
            </div>
        </form>
    </div>
    @endif
</div>
