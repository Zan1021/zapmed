<?php

namespace App\Livewire\Admin;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\HelpSearchLog;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class HelpCenter extends Component
{
    use WithPagination;

    public string $activeTab = 'categories';

    // Category form
    public bool $showCategoryForm = false;
    public ?int $editingCategoryId = null;
    public string $categoryName = '';
    public string $categorySlug = '';
    public string $categoryDescription = '';
    public string $categoryIcon = '';
    public int $categorySortOrder = 0;
    public bool $categoryIsActive = true;

    // Article form
    public bool $showArticleForm = false;
    public ?int $editingArticleId = null;
    public string $articleTitle = '';
    public string $articleSlug = '';
    public ?int $articleCategoryId = null;
    public string $articleBody = '';
    public string $articleStatus = 'draft';
    public string $articleVisibility = 'public';
    public int $articleSortOrder = 0;

    // Filters
    public string $articleSearch = '';
    public ?int $filterCategory = null;
    public string $filterStatus = '';

    protected function rules(): array
    {
        return [
            'categoryName' => 'required|string|max:255',
            'categorySlug' => 'required|string|max:255|unique:help_categories,slug,' . $this->editingCategoryId,
            'categoryDescription' => 'nullable|string',
            'categoryIcon' => 'nullable|string',
            'categorySortOrder' => 'integer|min:0',
            'categoryIsActive' => 'boolean',
        ];
    }

    public function updatedCategoryName(): void
    {
        if (!$this->editingCategoryId) {
            $this->categorySlug = Str::slug($this->categoryName);
        }
    }

    public function updatedArticleTitle(): void
    {
        if (!$this->editingArticleId) {
            $this->articleSlug = Str::slug($this->articleTitle);
        }
    }

    public function updatedArticleSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCategory(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    // Category CRUD
    public function createCategory(): void
    {
        $this->resetCategoryForm();
        $this->showCategoryForm = true;
    }

    public function editCategory(int $id): void
    {
        $category = HelpCategory::findOrFail($id);
        $this->editingCategoryId = $category->id;
        $this->categoryName = $category->name;
        $this->categorySlug = $category->slug;
        $this->categoryDescription = $category->description ?? '';
        $this->categoryIcon = $category->icon ?? '';
        $this->categorySortOrder = $category->sort_order;
        $this->categoryIsActive = $category->is_active;
        $this->showCategoryForm = true;
    }

    public function saveCategory(): void
    {
        $this->validate([
            'categoryName' => 'required|string|max:255',
            'categorySlug' => 'required|string|max:255|unique:help_categories,slug,' . $this->editingCategoryId,
        ]);

        HelpCategory::updateOrCreate(
            ['id' => $this->editingCategoryId],
            [
                'name' => $this->categoryName,
                'slug' => $this->categorySlug,
                'description' => $this->categoryDescription ?: null,
                'icon' => $this->categoryIcon ?: null,
                'sort_order' => $this->categorySortOrder,
                'is_active' => $this->categoryIsActive,
            ]
        );

        $this->resetCategoryForm();
        session()->flash('message', 'Category saved successfully.');
    }

    public function deleteCategory(int $id): void
    {
        $category = HelpCategory::findOrFail($id);

        if ($category->articles()->count() > 0) {
            session()->flash('error', 'Cannot delete category with articles. Move or delete articles first.');
            return;
        }

        $category->delete();
        session()->flash('message', 'Category deleted.');
    }

    public function toggleCategoryActive(int $id): void
    {
        $category = HelpCategory::findOrFail($id);
        $category->update(['is_active' => !$category->is_active]);
    }

    private function resetCategoryForm(): void
    {
        $this->showCategoryForm = false;
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->categorySlug = '';
        $this->categoryDescription = '';
        $this->categoryIcon = '';
        $this->categorySortOrder = 0;
        $this->categoryIsActive = true;
    }

    // Article CRUD
    public function createArticle(): void
    {
        $this->resetArticleForm();
        $this->showArticleForm = true;
    }

    public function editArticle(int $id): void
    {
        $article = HelpArticle::findOrFail($id);
        $this->editingArticleId = $article->id;
        $this->articleTitle = $article->title;
        $this->articleSlug = $article->slug;
        $this->articleCategoryId = $article->help_category_id;
        $this->articleBody = $article->body;
        $this->articleStatus = $article->status;
        $this->articleVisibility = $article->visibility;
        $this->articleSortOrder = $article->sort_order;
        $this->showArticleForm = true;
    }

    public function saveArticle(): void
    {
        $this->validate([
            'articleTitle' => 'required|string|max:255',
            'articleSlug' => 'required|string|max:255|unique:help_articles,slug,' . $this->editingArticleId,
            'articleCategoryId' => 'required|exists:help_categories,id',
            'articleBody' => 'required|string',
            'articleStatus' => 'required|in:draft,published',
            'articleVisibility' => 'required|in:public,internal',
            'articleSortOrder' => 'integer|min:0',
        ]);

        $data = [
            'title' => $this->articleTitle,
            'slug' => $this->articleSlug,
            'help_category_id' => $this->articleCategoryId,
            'body' => $this->articleBody,
            'status' => $this->articleStatus,
            'visibility' => $this->articleVisibility,
            'sort_order' => $this->articleSortOrder,
        ];

        if ($this->articleStatus === 'published' && !$this->editingArticleId) {
            $data['published_at'] = now();
        }

        if ($this->editingArticleId) {
            $article = HelpArticle::findOrFail($this->editingArticleId);
            if ($this->articleStatus === 'published' && !$article->published_at) {
                $data['published_at'] = now();
            }
            $article->update($data);
        } else {
            $data['author_id'] = auth()->id();
            HelpArticle::create($data);
        }

        // Update category article counts
        HelpCategory::find($this->articleCategoryId)?->updateArticlesCount();

        $this->resetArticleForm();
        session()->flash('message', 'Article saved successfully.');
    }

    public function publishArticle(int $id): void
    {
        $article = HelpArticle::findOrFail($id);
        $article->update([
            'status' => 'published',
            'published_at' => $article->published_at ?? now(),
        ]);
        $article->category->updateArticlesCount();
        session()->flash('message', 'Article published.');
    }

    public function unpublishArticle(int $id): void
    {
        $article = HelpArticle::findOrFail($id);
        $article->update(['status' => 'draft']);
        $article->category->updateArticlesCount();
        session()->flash('message', 'Article unpublished.');
    }

    public function deleteArticle(int $id): void
    {
        $article = HelpArticle::findOrFail($id);
        $categoryId = $article->help_category_id;
        $article->delete();
        HelpCategory::find($categoryId)?->updateArticlesCount();
        session()->flash('message', 'Article deleted.');
    }

    private function resetArticleForm(): void
    {
        $this->showArticleForm = false;
        $this->editingArticleId = null;
        $this->articleTitle = '';
        $this->articleSlug = '';
        $this->articleCategoryId = null;
        $this->articleBody = '';
        $this->articleStatus = 'draft';
        $this->articleVisibility = 'public';
        $this->articleSortOrder = 0;
    }

    // Analytics
    public function getAnalyticsProperty(): array
    {
        $topSearches = HelpSearchLog::selectRaw('query, count(*) as search_count, max(results_count) as results_count')
            ->groupBy('query')
            ->orderByDesc('search_count')
            ->limit(10)
            ->get();

        $zeroResultSearches = HelpSearchLog::selectRaw('query, count(*) as search_count')
            ->where('results_count', 0)
            ->groupBy('query')
            ->orderByDesc('search_count')
            ->limit(10)
            ->get();

        $mostViewed = HelpArticle::published()
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        $leastHelpful = HelpArticle::published()
            ->whereRaw('(helpful_yes + helpful_no) > 0')
            ->orderByRaw('CAST(helpful_yes AS REAL) / (helpful_yes + helpful_no) ASC')
            ->limit(10)
            ->get();

        $totalViewsThisMonth = HelpArticle::whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->sum('views');

        return [
            'topSearches' => $topSearches,
            'zeroResultSearches' => $zeroResultSearches,
            'mostViewed' => $mostViewed,
            'leastHelpful' => $leastHelpful,
            'totalViewsThisMonth' => $totalViewsThisMonth,
        ];
    }

    public function render()
    {
        $categories = HelpCategory::ordered()->get();

        $articles = HelpArticle::with('category')
            ->when($this->articleSearch, fn ($q) => $q->search($this->articleSearch))
            ->when($this->filterCategory, fn ($q) => $q->where('help_category_id', $this->filterCategory))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.admin.help-center', [
            'categories' => $categories,
            'articles' => $articles,
        ])->layout('layouts.app');
    }
}
