<?php

namespace App\Livewire\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Livewire\Component;
use Livewire\WithPagination;

class BlogIndex extends Component
{
    use WithPagination;

    public ?string $category = null;
    public string $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function filterCategory(?string $slug)
    {
        $this->category = $slug;
        $this->resetPage();
    }

    public function render()
    {
        $posts = BlogPost::published()
            ->with(['category', 'author'])
            ->when($this->category, function ($q) {
                $q->whereHas('category', fn($c) => $c->where('slug', $this->category));
            })
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('title', 'like', "%{$this->search}%")
                       ->orWhere('excerpt', 'like', "%{$this->search}%");
                });
            })
            ->latest('published_at')
            ->paginate(9);

        $categories = BlogCategory::withCount(['posts' => fn($q) => $q->published()])->orderBy('sort_order')->get();

        return view('livewire.blog.blog-index', compact('posts', 'categories'))
            ->layout('layouts.bare', ['title' => 'Health & Wellness Blog | Zapmed']);
    }
}
