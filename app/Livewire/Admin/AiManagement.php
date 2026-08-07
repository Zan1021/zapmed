<?php

namespace App\Livewire\Admin;

use App\Models\AiConversation;
use App\Models\AiKnowledgeEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AiManagement extends Component
{
    use WithPagination;

    // Tab state
    #[Url]
    public string $tab = 'stats';

    // Knowledge entry form
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $title = '';
    public string $category = 'general';
    public string $content = '';

    // Conversations filter
    public string $filterMatch = ''; // all, matched, unmatched

    public function getStatsProperty(): array
    {
        return [
            'total_queries' => AiConversation::count(),
            'today' => AiConversation::whereDate('created_at', today())->count(),
            'this_week' => AiConversation::where('created_at', '>=', now()->startOfWeek())->count(),
            'matched_rate' => AiConversation::count() > 0
                ? round((AiConversation::matched()->count() / AiConversation::count()) * 100, 1)
                : 0,
            'unmatched_count' => AiConversation::unmatched()->count(),
            'knowledge_entries' => AiKnowledgeEntry::count(),
        ];
    }

    public function getTopQuestionsProperty()
    {
        return AiConversation::select('question', DB::raw('COUNT(*) as ask_count'))
            ->groupBy('question')
            ->orderByDesc('ask_count')
            ->limit(10)
            ->get();
    }

    public function getTopTreatmentsProperty()
    {
        return AiConversation::matched()
            ->select('matched_treatment_name', 'matched_treatment_slug', DB::raw('COUNT(*) as count'))
            ->groupBy('matched_treatment_name', 'matched_treatment_slug')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
    }

    public function getUnmatchedQuestionsProperty()
    {
        return AiConversation::unmatched()
            ->select('question', DB::raw('COUNT(*) as ask_count'))
            ->groupBy('question')
            ->orderByDesc('ask_count')
            ->limit(20)
            ->get();
    }

    // Knowledge CRUD
    public function createEntry(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editEntry(int $id): void
    {
        $entry = AiKnowledgeEntry::findOrFail($id);
        $this->editingId = $entry->id;
        $this->title = $entry->title;
        $this->category = $entry->category;
        $this->content = $entry->content;
        $this->showForm = true;
    }

    public function saveEntry(): void
    {
        $this->validate([
            'title' => 'required|string|max:200',
            'category' => 'required|in:general,treatment,faq,policy,pricing',
            'content' => 'required|string|max:2000',
        ]);

        $data = [
            'title' => $this->title,
            'category' => $this->category,
            'content' => $this->content,
        ];

        if ($this->editingId) {
            AiKnowledgeEntry::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Knowledge entry updated.');
        } else {
            $data['created_by'] = Auth::id();
            $data['sort_order'] = AiKnowledgeEntry::max('sort_order') + 1;
            AiKnowledgeEntry::create($data);
            session()->flash('message', 'Knowledge entry added. The AI will use this in future responses.');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function toggleEntry(int $id): void
    {
        $entry = AiKnowledgeEntry::findOrFail($id);
        $entry->update(['is_active' => !$entry->is_active]);
    }

    public function deleteEntry(int $id): void
    {
        AiKnowledgeEntry::findOrFail($id)->delete();
        session()->flash('message', 'Knowledge entry deleted.');
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->category = 'general';
        $this->content = '';
    }

    public function render()
    {
        $conversations = AiConversation::query()
            ->when($this->filterMatch === 'matched', fn($q) => $q->matched())
            ->when($this->filterMatch === 'unmatched', fn($q) => $q->unmatched())
            ->latest()
            ->paginate(15);

        $knowledgeEntries = AiKnowledgeEntry::orderBy('category')->orderBy('sort_order')->get();

        return view('livewire.admin.ai-management', [
            'conversations' => $conversations,
            'knowledgeEntries' => $knowledgeEntries,
        ])->layout('layouts.app');
    }
}
