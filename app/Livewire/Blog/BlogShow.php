<?php

namespace App\Livewire\Blog;

use App\Models\BlogPost;
use Livewire\Component;

class BlogShow extends Component
{
    public BlogPost $post;

    public function mount(string $slug)
    {
        $this->post = BlogPost::published()
            ->where('slug', $slug)
            ->with(['category', 'author'])
            ->firstOrFail();

        // Increment views
        $this->post->increment('views');
    }

    public function render()
    {
        $relatedPosts = BlogPost::published()
            ->where('blog_category_id', $this->post->blog_category_id)
            ->where('id', '!=', $this->post->id)
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('livewire.blog.blog-show', compact('relatedPosts'))
            ->layout('layouts.bare', ['title' => $this->post->meta_title_tag]);
    }
}
