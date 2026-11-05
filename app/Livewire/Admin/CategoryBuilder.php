<?php

namespace App\Livewire\Admin;

use App\Models\AssessmentCategory;
use App\Models\AssessmentQuestion;
use App\Services\Assessments\AssessmentService;
use App\Services\Assessments\QuestionBankService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

/**
 * Admin Screen 2 — Category Builder (design §4, US-8..12). ADMIN-ONLY.
 *
 * Pick a category → see its flow (locked CORE block at top, then the draggable treatment questions).
 * Add questions from the bank or create new; reorder; preview as a patient; create + publish a brand
 * new category WITHOUT a developer (US-12 — the feature's acceptance test).
 */
class CategoryBuilder extends Component
{
    public ?int $categoryId = null;
    public string $addSearch = '';
    public string $flash = '';
    public string $error = '';

    // new-category form
    public bool $showNewCategory = false;
    public string $newSlug = '';
    public string $newName = '';

    // preview
    public bool $showPreview = false;

    public function mount(?string $slug = null): void
    {
        if ($slug) {
            $this->categoryId = AssessmentCategory::where('slug', $slug)->value('id');
        }
    }

    public function selectCategory(int $id): void
    {
        $this->categoryId = $id;
        $this->reset(['addSearch', 'error', 'flash']);
    }

    public function createCategory(QuestionBankService $bank): void
    {
        $this->validate([
            'newSlug' => 'required|string|max:120',
            'newName' => 'required|string|max:120',
        ]);

        $cat = $bank->createCategory($this->newSlug, $this->newName, Auth::user());
        $this->categoryId = $cat->id;
        $this->showNewCategory = false;
        $this->reset(['newSlug', 'newName']);
        $this->flash = 'Category created as draft. Add questions, then publish.';
    }

    public function addQuestion(int $questionId, QuestionBankService $bank): void
    {
        $this->error = '';
        try {
            $bank->addQuestionToCategory(
                $this->category(),
                AssessmentQuestion::findOrFail($questionId),
                Auth::user(),
            );
            $this->flash = 'Question added.';
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage(); // duplicate / core guard message (US-10)
        }
    }

    public function removeQuestion(int $linkQuestionId): void
    {
        $this->category()->questionLinks()->where('question_id', $linkQuestionId)->delete();
        $this->category()->increment('manifest_version');
        $this->flash = 'Question removed from this category.';
    }

    /** $ordered = array of question ids top→bottom (from the drag UI). */
    public function reorder(array $ordered, QuestionBankService $bank): void
    {
        $bank->reorderCategory($this->category(), $ordered, Auth::user());
        $this->flash = 'Order saved.';
    }

    public function publish(QuestionBankService $bank): void
    {
        $bank->publishCategory($this->category(), Auth::user());
        $this->flash = 'Category published — patients will now see this flow.';
    }

    public function category(): ?AssessmentCategory
    {
        return $this->categoryId ? AssessmentCategory::find($this->categoryId) : null;
    }

    public function render()
    {
        $categories = AssessmentCategory::orderBy('name')->get();
        $category = $this->category();

        $coreBlock = AssessmentQuestion::query()
            ->with('currentVersion')
            ->where('scope', 'core')
            ->where('status', 'live')
            ->orderByRaw('core_order is null')
            ->orderBy('core_order')
            ->get();

        $flow = [];
        $previewFlow = [];
        if ($category) {
            $flow = $category->questionLinks()->with('question.currentVersion')->get();
            if ($this->showPreview) {
                $previewFlow = app(AssessmentService::class)->flowFor($category->slug);
            }
        }

        // bank questions available to add (category scope, not retired, not already in this category)
        $alreadyIn = $category ? $category->questionLinks()->pluck('question_id')->all() : [];
        $available = AssessmentQuestion::query()
            ->with('currentVersion')
            ->where('scope', 'category')
            ->where('status', '!=', 'retired')
            ->when($this->addSearch, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('question_key', 'like', "%{$this->addSearch}%")
                        ->orWhereHas('currentVersion', fn ($v) => $v->where('text', 'like', "%{$this->addSearch}%"));
                });
            })
            ->whereNotIn('id', $alreadyIn)
            ->orderBy('question_key')
            ->limit(15)
            ->get();

        return view('livewire.admin.category-builder', compact(
            'categories', 'category', 'coreBlock', 'flow', 'available', 'previewFlow'
        ))->layout('layouts.app');
    }
}
