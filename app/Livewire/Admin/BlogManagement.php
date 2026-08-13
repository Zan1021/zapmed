<?php

namespace App\Livewire\Admin;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class BlogManagement extends Component
{
    use WithPagination, WithFileUploads;

    public string $statusFilter = 'all';
    public string $search = '';

    // Form fields
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $title = '';
    public string $slug = '';
    public string $excerpt = '';
    public string $body = '';
    public ?int $blog_category_id = null;
    public string $meta_title = '';
    public string $meta_description = '';
    public string $status = 'draft';
    public $featured_image;
    public ?string $existing_image = null;

    protected function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:blog_posts,slug,' . $this->editingId,
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string',
            'blog_category_id' => 'required|exists:blog_categories,id',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'status' => 'required|in:draft,published,archived',
            'featured_image' => 'nullable|image|max:5120',
        ];
    }

    public function updatedTitle()
    {
        if (!$this->editingId) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id)
    {
        $post = BlogPost::findOrFail($id);
        $this->editingId = $post->id;
        $this->title = $post->title;
        $this->slug = $post->slug;
        $this->excerpt = $post->excerpt ?? '';
        $this->body = $post->body;
        $this->blog_category_id = $post->blog_category_id;
        $this->meta_title = $post->meta_title ?? '';
        $this->meta_description = $post->meta_description ?? '';
        $this->status = $post->status;
        $this->existing_image = $post->featured_image;
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt ?: null,
            'body' => $this->body,
            'blog_category_id' => $this->blog_category_id,
            'meta_title' => $this->meta_title ?: null,
            'meta_description' => $this->meta_description ?: null,
            'status' => $this->status,
            'author_id' => auth()->id(),
            'reading_time' => BlogPost::calculateReadingTime($this->body),
        ];

        if ($this->status === 'published' && !$this->editingId) {
            $data['published_at'] = now();
        }

        if ($this->featured_image) {
            $data['featured_image'] = $this->featured_image->store('blog', 'public');
        }

        if ($this->editingId) {
            $post = BlogPost::findOrFail($this->editingId);
            if ($this->status === 'published' && !$post->published_at) {
                $data['published_at'] = now();
            }
            $post->update($data);
            session()->flash('message', 'Post updated.');
        } else {
            BlogPost::create($data);
            session()->flash('message', 'Post created.');
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id)
    {
        BlogPost::findOrFail($id)->delete();
        session()->flash('message', 'Post deleted.');
    }

    public function togglePublish(int $id)
    {
        $post = BlogPost::findOrFail($id);
        if ($post->isPublished()) {
            $post->update(['status' => 'draft']);
        } else {
            $post->update(['status' => 'published', 'published_at' => $post->published_at ?? now()]);
        }
    }

    public function cancelForm()
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm()
    {
        $this->editingId = null;
        $this->title = '';
        $this->slug = '';
        $this->excerpt = '';
        $this->body = '';
        $this->blog_category_id = null;
        $this->meta_title = '';
        $this->meta_description = '';
        $this->status = 'draft';
        $this->featured_image = null;
        $this->existing_image = null;
    }

    public function render()
    {
        $posts = BlogPost::with(['category', 'author'])
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(15);

        $categories = BlogCategory::orderBy('sort_order')->get();

        return view('livewire.admin.blog-management', compact('posts', 'categories'));
    }
}
