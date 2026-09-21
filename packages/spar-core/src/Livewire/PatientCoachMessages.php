<?php

namespace Zapmed\SparCore\Livewire;

use Livewire\Component;
use Zapmed\SparCore\Models\SparConversation;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparProductSuggestion;
use Zapmed\SparCore\Services\SparCoachService;
use Zapmed\SparCore\Services\SparPatientSession;
use Zapmed\SparCore\Services\SparPatientView;

/**
 * Patient side of the Health Coach (spec spar-health-coach FR-3). Embedded in
 * the MyMedsTracker dashboard step — reachable ONLY after the consent gate.
 *
 * No login: the patient is the session patient (SparPatientSession). Consent is
 * enforced as defence-in-depth (the tracker already gates the whole surface).
 */
class PatientCoachMessages extends Component
{
    public string $body = '';
    public string $error = '';

    /**
     * Chip filter over the single thread (views, not separate storage):
     *   all | recommendations | orders
     * Reminders are NOT stored as messages (they go out via the dispatcher),
     * so there is deliberately no "reminders" chip — it would always be empty.
     */
    public string $filter = 'all';

    /**
     * Windowing: render only the most recent N messages, "Load earlier" grows
     * the window. Keeps the Livewire payload sane over a year of chat and the
     * encrypted-body decrypts bounded.
     */
    public int $limit = 20;

    private const PAGE = 20;

    /**
     * When true the component renders as a full page (own route, wrapped in the
     * patient layout with a back link). When embedded elsewhere pass :page=false.
     * Defaults to true so the routed full-page mount needs no extra wiring.
     */
    public bool $page = true;

    private function session(): SparPatientSession
    {
        return app(SparPatientSession::class);
    }

    private function coachService(): SparCoachService
    {
        return app(SparCoachService::class);
    }

    public function mount(): void
    {
        if (! $this->session()->patientId()) {
            abort(403);
        }

        if ($this->consented()) {
            $this->coachService()->markRead($this->conversation, 'patient');
        }
    }

    private function primary(): ?SparPatient
    {
        $patient = $this->session()->patient();

        return $patient ? app(SparPatientView::class)->primary($patient) : null;
    }

    private function consented(): bool
    {
        return (bool) $this->primary()?->hasConsented();
    }

    /**
     * The conversation for the profile's primary member at their pharmacy.
     */
    public function getConversationProperty(): ?SparConversation
    {
        $primary = $this->primary();
        if (! $primary) {
            return null;
        }

        $pharmacyId = (int) ($primary->spar_pharmacy_id
            ?? optional(app(SparPatientView::class)->journeys($primary)->first())->spar_pharmacy_id);

        if (! $pharmacyId) {
            return null;
        }

        return $this->coachService()->openConversation($primary, $pharmacyId);
    }

    /**
     * The window of messages to render: the most recent {$limit} rows of the
     * thread (optionally narrowed by the active chip), returned oldest→newest
     * so the newest sits at the bottom (chat style).
     *
     * Filtering happens at the DB level by `kind` (never the encrypted body):
     *   recommendations -> product_suggestion
     *   orders          -> order_event
     */
    public function getMessagesProperty()
    {
        $conversation = $this->conversation;
        if (! $conversation) {
            return collect();
        }

        $query = $conversation->messages()->with('productSuggestion');

        if ($this->filter === 'recommendations') {
            $query->where('kind', 'product_suggestion');
        } elseif ($this->filter === 'orders') {
            $query->where('kind', 'order_event');
        }

        // Most-recent window, then flip to chronological for bottom-anchored display.
        return $query->orderByDesc('id')
            ->limit($this->limit)
            ->get()
            ->sortBy('id')
            ->values();
    }

    /**
     * Whether there are older messages beyond the current window (for the
     * "Load earlier" control). Counts by the SAME filter, cheaply by column.
     */
    public function getHasMoreProperty(): bool
    {
        return $this->filteredTotal($this->filter) > $this->limit;
    }

    /**
     * Chip counts across the WHOLE thread (not just the window). All by column
     * — no encrypted body is read to produce these.
     *
     * @return array{all:int,recommendations:int,orders:int}
     */
    public function getCountsProperty(): array
    {
        return [
            'all' => $this->filteredTotal('all'),
            'recommendations' => $this->filteredTotal('recommendations'),
            'orders' => $this->filteredTotal('orders'),
        ];
    }

    private function filteredTotal(string $filter): int
    {
        $conversation = $this->conversation;
        if (! $conversation) {
            return 0;
        }

        $query = $conversation->messages();

        if ($filter === 'recommendations') {
            $query->where('kind', 'product_suggestion');
        } elseif ($filter === 'orders') {
            $query->where('kind', 'order_event');
        }

        return (int) $query->count();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'recommendations', 'orders'], true) ? $filter : 'all';
        $this->limit = self::PAGE; // reset the window when switching chips
    }

    public function loadEarlier(): void
    {
        $this->limit += self::PAGE;
    }

    public function send(): void
    {
        if (! $this->consented()) {
            $this->error = 'Please give consent before messaging your pharmacy.';

            return;
        }

        $this->validate(['body' => 'required|string|max:2000']);

        $conversation = $this->conversation;
        if (! $conversation) {
            $this->error = 'No pharmacy is linked to your profile yet.';

            return;
        }

        $this->coachService()->postPatientMessage($conversation, trim($this->body));
        $this->body = '';
        $this->error = '';

        // Newly sent message lands at the bottom — nudge the thread to scroll.
        $this->dispatch('coach-scroll-bottom');
    }

    public function accept(int $suggestionId): void
    {
        $suggestion = $this->resolveSuggestion($suggestionId);
        if ($suggestion) {
            $this->coachService()->acceptSuggestion($suggestion);
        }
    }

    public function decline(int $suggestionId): void
    {
        $suggestion = $this->resolveSuggestion($suggestionId);
        if ($suggestion) {
            $this->coachService()->declineSuggestion($suggestion);
        }
    }

    /**
     * Resolve a suggestion, guarding that it belongs to THIS patient's
     * conversation (a patient can only act on their own thread).
     */
    private function resolveSuggestion(int $suggestionId): ?SparProductSuggestion
    {
        $conversation = $this->conversation;
        if (! $conversation) {
            return null;
        }

        return SparProductSuggestion::where('id', $suggestionId)
            ->where('spar_conversation_id', $conversation->id)
            ->first();
    }

    public function render()
    {
        $view = view('spar::livewire.patient-coach-messages');

        // Full-page mount (own route) gets the patient layout; embedded mounts
        // (:page=false) render bare so they slot into a parent view.
        if ($this->page) {
            $view->layout(config('spar.layouts.patient', 'layouts.spar-meds'));
        }

        return $view;
    }
}
