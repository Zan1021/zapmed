<?php

namespace App\Livewire\HelpCenter;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\HelpSearchLog;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';
    public ?string $selectedCategory = null;

    public function updatedSearch(): void
    {
        if (strlen($this->search) >= 2) {
            $this->logSearch();
        }
        $this->selectedCategory = null;
    }

    public function selectCategory(?string $slug): void
    {
        $this->selectedCategory = $slug;
        $this->search = '';
    }

    public function clearCategory(): void
    {
        $this->selectedCategory = null;
    }

    private function logSearch(): void
    {
        if (strlen($this->search) < 2) {
            return;
        }

        $resultsCount = HelpArticle::published()
            ->public()
            ->search($this->search)
            ->count();

        HelpSearchLog::create([
            'query' => $this->search,
            'results_count' => $resultsCount,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
        ]);
    }

    public function render()
    {
        $categories = HelpCategory::active()
            ->ordered()
            ->withCount(['articles' => fn ($q) => $q->published()->public()])
            ->get();

        $searchResults = null;
        $categoryArticles = null;

        if (strlen($this->search) >= 2) {
            $searchResults = HelpArticle::published()
                ->public()
                ->search($this->search)
                ->with('category')
                ->ordered()
                ->limit(20)
                ->get();
        } elseif ($this->selectedCategory) {
            $categoryArticles = HelpArticle::published()
                ->public()
                ->whereHas('category', fn ($q) => $q->where('slug', $this->selectedCategory))
                ->ordered()
                ->get();
        }

        $selectedCategoryModel = $this->selectedCategory
            ? $categories->firstWhere('slug', $this->selectedCategory)
            : null;

        return view('livewire.help-center.index', [
            'categories' => $categories,
            'searchResults' => $searchResults,
            'categoryArticles' => $categoryArticles,
            'selectedCategoryModel' => $selectedCategoryModel,
        ])->layout('layouts.bare', ['title' => 'Help Center | Zapmed']);
    }
}
