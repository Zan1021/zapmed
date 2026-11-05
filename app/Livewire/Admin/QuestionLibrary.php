<?php

namespace App\Livewire\Admin;

use App\Models\AssessmentQuestion;
use App\Services\Assessments\QuestionBankService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin Screen 1 — Question Library (specs/question-bank design §4, US-5..7).
 *
 * ADMIN-ONLY (route middleware role:admin). A searchable table of every question in the bank with
 * Edit (= new version) / Preview / Duplicate / Retire. Questions are NEVER deleted. Editing a question
 * shows how many categories use it before the admin types. Publishing is a single admin gate — no
 * clinical-approver role (Captain Zan decision).
 */
class QuestionLibrary extends Component
{
    use WithPagination;

    public string $search = '';
    public string $scopeFilter = '';
    public string $statusFilter = '';
    public string $clinicalFilter = '';

    public bool $showModal = false;
    public ?int $editingId = null;
    public string $usageWarning = '';

    // version/editable fields
    public string $question_key = '';
    public string $scope = 'category';
    public bool $clinical = false;
    public string $text = '';
    public string $helper_text = '';
    public string $why_we_ask = '';
    public string $type = 'single';
    public string $optionsRaw = '';      // one "label" per line
    public bool $required = false;
    public string $placeholder = '';
    public string $publishReason = '';

    protected function rules(): array
    {
        return [
            'text' => 'required|string|max:500',
            'type' => 'required|in:single,multi,text,textarea,number,date,select,upload',
            'scope' => 'required|in:core,category',
            'question_key' => 'nullable|string|max:64',
            'helper_text' => 'nullable|string|max:500',
            'why_we_ask' => 'nullable|string|max:500',
            'placeholder' => 'nullable|string|max:255',
            'publishReason' => 'nullable|string|max:255',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $q = AssessmentQuestion::with('currentVersion')->findOrFail($id);
        $v = $q->currentVersion;

        $this->editingId = $id;
        $this->question_key = $q->question_key;
        $this->scope = $q->scope;
        $this->clinical = $q->clinical;
        $this->text = $v?->text ?? '';
        $this->helper_text = $v?->helper_text ?? '';
        $this->why_we_ask = $v?->why_we_ask ?? '';
        $this->type = $v?->type ?? 'single';
        $this->optionsRaw = $this->optionsToText($v?->options);
        $this->required = (bool) ($v?->required ?? false);
        $this->placeholder = $v?->placeholder ?? '';
        $this->publishReason = '';

        // US-6: show how many categories use this before they type.
        $count = $q->usedInCount();
        $this->usageWarning = $count > 0
            ? "Used in {$count} " . ($count === 1 ? 'category' : 'categories') . ". A change here affects all of them."
            : 'Not yet used in any category.';

        $this->showModal = true;
    }

    public function save(QuestionBankService $bank): void
    {
        $this->validate();

        $versionAttrs = [
            'text' => $this->text,
            'helper_text' => $this->helper_text ?: null,
            'why_we_ask' => $this->why_we_ask ?: null,
            'type' => $this->type,
            'options' => $this->textToOptions($this->optionsRaw),
            'required' => $this->required,
            'placeholder' => $this->placeholder ?: null,
        ];

        if ($this->editingId) {
            $question = AssessmentQuestion::findOrFail($this->editingId);
            $bank->editQuestion($question, $versionAttrs, Auth::user());
        } else {
            $bank->createQuestion(
                ['scope' => $this->scope, 'clinical' => $this->clinical, 'question_key' => $this->question_key ?: null],
                $versionAttrs,
                Auth::user(),
            );
        }

        $this->showModal = false;
        $this->resetForm();
        session()->flash('qb_status', 'Saved as a new draft version. Publish it to go live.');
    }

    public function publishCurrent(QuestionBankService $bank): void
    {
        if (! $this->editingId) {
            return;
        }
        $question = AssessmentQuestion::with('versions')->findOrFail($this->editingId);
        $latest = $question->versions()->orderByDesc('version')->first();
        if ($latest) {
            $bank->publish($question, $latest, $this->publishReason ?: 'published via question library', Auth::user());
            session()->flash('qb_status', 'Published live.');
        }
        $this->showModal = false;
        $this->resetForm();
    }

    public function duplicate(int $id, QuestionBankService $bank): void
    {
        $bank->duplicate(AssessmentQuestion::findOrFail($id), Auth::user());
        session()->flash('qb_status', 'Duplicated into a new draft.');
    }

    public function retire(int $id, QuestionBankService $bank): void
    {
        $bank->retire(AssessmentQuestion::findOrFail($id), Auth::user());
        session()->flash('qb_status', 'Question retired (kept for historical answers).');
    }

    private function optionsToText(?array $options): string
    {
        if (empty($options)) {
            return '';
        }
        return implode("\n", array_map(
            fn ($o) => is_array($o) ? ($o['label'] ?? $o['code'] ?? '') : $o,
            $options
        ));
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

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->question_key = '';
        $this->scope = 'category';
        $this->clinical = false;
        $this->text = '';
        $this->helper_text = '';
        $this->why_we_ask = '';
        $this->type = 'single';
        $this->optionsRaw = '';
        $this->required = false;
        $this->placeholder = '';
        $this->publishReason = '';
        $this->usageWarning = '';
    }

    public function render()
    {
        $questions = AssessmentQuestion::query()
            ->with('currentVersion')
            ->withCount(['categoryLinks as used_in_count'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('question_key', 'like', "%{$this->search}%")
                        ->orWhereHas('currentVersion', fn ($v) => $v->where('text', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->scopeFilter, fn ($q) => $q->where('scope', $this->scopeFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->clinicalFilter !== '', fn ($q) => $q->where('clinical', $this->clinicalFilter === '1'))
            ->orderBy('scope')
            ->orderBy('question_key')
            ->paginate(25);

        return view('livewire.admin.question-library', compact('questions'))
            ->layout('layouts.app');
    }
}
