<?php

namespace App\Livewire\Admin;

use App\Models\AssessmentCategory;
use App\Models\AssessmentQuestion;
use App\Services\Assessments\QuestionBankService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Admin Screen 3 — Clinical Review Queue (Phase 9, Option 2). ADMIN-ONLY.
 *
 * Built for Dr Anke (working through the admin login) to sign off the imported Draft questions one at
 * a time: full wording + options + branch shown exactly as the patient sees them, with
 * Approve (publish Live) / Edit (reword → new version) / Reject (back to Draft with a reason) and a
 * progress bar. Filter by treatment. When every question in a treatment is Live, its category is
 * auto-published so patients get the flow.
 */
class ReviewQueue extends Component
{
    public string $treatmentFilter = '';   // category slug, or '' for all
    public ?int $currentId = null;          // the question being reviewed

    // edit-in-place
    public bool $editing = false;
    public string $editText = '';
    public string $editHelper = '';
    public string $optionsRaw = '';

    // reject
    public bool $rejecting = false;
    public string $rejectReason = '';

    public string $flash = '';

    public function mount(): void
    {
        $this->loadNext();
    }

    public function updatedTreatmentFilter(): void
    {
        $this->currentId = null;
        $this->resetSubForms();
        $this->loadNext();
    }

    /** Pull the next question needing review (draft or in_review), honouring the treatment filter. */
    public function loadNext(): void
    {
        $this->currentId = $this->pendingQuery()->orderBy('id')->value('id');
        $this->resetSubForms();
    }

    public function approve(QuestionBankService $bank): void
    {
        if (! $this->current) {
            return;
        }
        $q = $this->current;
        $latest = $q->versions()->orderByDesc('version')->first();
        if ($latest) {
            $bank->publish($q, $latest, 'clinically approved', Auth::user());
        }
        $this->flash = 'Approved — live.';
        $this->maybePublishCategory($bank, $q);
        $this->loadNext();
    }

    public function startEdit(): void
    {
        if (! $this->current) {
            return;
        }
        $v = $this->current->currentVersion;
        $this->editText = $v?->text ?? '';
        $this->editHelper = $v?->helper_text ?? '';
        $this->optionsRaw = collect($v?->options ?? [])
            ->map(fn ($o) => is_array($o) ? ($o['label'] ?? $o['code'] ?? '') : $o)
            ->implode("\n");
        $this->editing = true;
    }

    public function saveEdit(QuestionBankService $bank): void
    {
        $this->validate(['editText' => 'required|string|max:500']);

        $v = $this->current->currentVersion;
        $bank->editQuestion($this->current, [
            'text' => $this->editText,
            'helper_text' => $this->editHelper ?: null,
            'type' => $v?->type ?? 'single',
            'options' => $this->textToOptions($this->optionsRaw),
            'branch_rules' => $v?->branch_rules,
            'flag_rule' => $v?->flag_rule,
            'required' => $v?->required ?? false,
            'placeholder' => $v?->placeholder,
        ], Auth::user());

        $this->editing = false;
        $this->flash = 'Reworded — new version saved (still needs approval).';
        // reload the same question so the reviewer sees the new wording
    }

    public function startReject(): void
    {
        $this->rejecting = true;
    }

    public function confirmReject(QuestionBankService $bank): void
    {
        $this->validate(['rejectReason' => 'required|string|max:255']);
        $bank->reject($this->current, $this->rejectReason, Auth::user());
        $this->flash = 'Rejected — sent back to draft with your note.';
        $this->loadNext();
    }

    public function getCurrentProperty(): ?AssessmentQuestion
    {
        return $this->currentId
            ? AssessmentQuestion::with('currentVersion')->find($this->currentId)
            : null;
    }

    private function pendingQuery()
    {
        return AssessmentQuestion::query()
            ->whereIn('status', ['draft', 'in_review'])
            ->when($this->treatmentFilter, function ($q) {
                $q->whereHas('categoryLinks.category', fn ($c) => $c->where('slug', $this->treatmentFilter));
            });
    }

    /** When a category has NO remaining draft/in_review questions, publish it Live. */
    private function maybePublishCategory(QuestionBankService $bank, AssessmentQuestion $q): void
    {
        foreach ($q->categoryLinks()->with('category')->get() as $link) {
            $cat = $link->category;
            if (! $cat || $cat->status === 'live') {
                continue;
            }
            $hasPending = $cat->questionLinks()
                ->whereHas('question', fn ($qq) => $qq->whereIn('status', ['draft', 'in_review']))
                ->exists();
            if (! $hasPending) {
                $bank->publishCategory($cat, Auth::user());
                $this->flash = "All questions approved — \"{$cat->name}\" is now live for patients.";
            }
        }
    }

    private function textToOptions(string $raw): ?array
    {
        $lines = array_filter(array_map('trim', explode("\n", $raw)), fn ($l) => $l !== '');
        if (empty($lines)) {
            return null;
        }
        return array_map(fn ($label) => [
            'code' => \Illuminate\Support\Str::slug($label, '_'),
            'label' => $label,
        ], array_values($lines));
    }

    private function resetSubForms(): void
    {
        $this->editing = false;
        $this->rejecting = false;
        $this->rejectReason = '';
    }

    public function render()
    {
        $categories = AssessmentCategory::orderBy('name')->get();

        $totalPending = $this->pendingQuery()->count();
        $totalInScope = AssessmentQuestion::query()
            ->when($this->treatmentFilter, fn ($q) => $q->whereHas('categoryLinks.category', fn ($c) => $c->where('slug', $this->treatmentFilter)))
            ->count();
        $reviewed = $totalInScope - $totalPending;

        // which categories does the current question belong to (for context)
        $currentCategories = [];
        if ($this->current) {
            $currentCategories = $this->current->categoryLinks()->with('category')->get()
                ->map(fn ($l) => $l->category?->name)->filter()->unique()->values()->all();
        }

        return view('livewire.admin.review-queue', [
            'categories' => $categories,
            'totalPending' => $totalPending,
            'totalInScope' => $totalInScope,
            'reviewed' => $reviewed,
            'currentCategories' => $currentCategories,
        ])->layout('layouts.app');
    }
}
