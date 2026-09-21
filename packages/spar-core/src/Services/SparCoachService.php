<?php

namespace Zapmed\SparCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Zapmed\SparCore\Models\SparConversation;
use Zapmed\SparCore\Models\SparMessage;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparOrderItem;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparProductSuggestion;

/**
 * Health Coach v1 orchestration (spec spar-health-coach §4).
 *
 * The single seam both the patient (MyMedsTracker → PatientCoachMessages) and
 * staff (PatientDetail → StaffCoachMessages) surfaces call, so the messaging /
 * suggest-to-basket rules live in ONE testable place — not duplicated in Livewire.
 *
 * Design guarantees honoured:
 *  - Conversations bind to the PRIMARY member (SparPatientView) — never a dependant.
 *  - One OPEN conversation per (patient, pharmacy).
 *  - Outward staff→patient nudges go through the consent-gated MessagingDispatcher
 *    and carry NO PHI (neutral "you have a new message" copy).
 *  - Accepting a suggestion attaches a line item to the patient's pending order
 *    (or creates a basket order), posts a system line, and notifies staff.
 *  - Every mutating action writes a spar_audit event.
 *  - Package-pure: references no host User model, Prescription model, or role enum.
 */
class SparCoachService
{
    public function __construct(
        private SparPatientView $patientView,
        private MessagingDispatcher $dispatcher,
        private SparOrderService $orders,
    ) {
    }

    /**
     * Find or lazily create the single OPEN conversation for the given patient's
     * PROFILE (resolved to primary) at the given pharmacy.
     */
    public function openConversation(SparPatient $patient, int $pharmacyId): SparConversation
    {
        $primary = $this->patientView->primary($patient);

        return SparConversation::query()
            ->where('spar_patient_id', $primary->id)
            ->where('spar_pharmacy_id', $pharmacyId)
            ->where('status', 'open')
            ->first()
            ?? SparConversation::create([
                'spar_patient_id' => $primary->id,
                'spar_pharmacy_id' => $pharmacyId,
                'status' => 'open',
            ]);
    }

    /**
     * Patient sends a text message. No dispatcher nudge — staff pull via the
     * unread badge (patient→staff never leaves the system).
     */
    public function postPatientMessage(SparConversation $conversation, string $body): SparMessage
    {
        $message = $conversation->messages()->create([
            'direction' => 'from_patient',
            'kind' => 'text',
            'body' => $this->clip($body),
        ]);

        $conversation->bumpUnread('staff');
        $conversation->touchLastMessage();

        $this->audit('coach_message_sent', $conversation, [
            'message_id' => $message->id,
            'direction' => 'from_patient',
        ]);

        return $message;
    }

    /**
     * Staff sends a text reply. Fires a consent-gated, no-PHI nudge to the patient.
     *
     * @param  array{id?:int|null,name?:string|null,role?:string|null}  $author
     */
    public function postStaffMessage(SparConversation $conversation, string $body, array $author): SparMessage
    {
        $message = $conversation->messages()->create([
            'direction' => 'from_staff',
            'kind' => 'text',
            'body' => $this->clip($body),
            'author_id' => $author['id'] ?? null,
            'author_name' => $author['name'] ?? null,
            'author_role' => $author['role'] ?? null,
        ]);

        $conversation->bumpUnread('patient');
        $conversation->touchLastMessage();

        $this->nudgePatient($conversation);

        $this->audit('coach_message_sent', $conversation, [
            'message_id' => $message->id,
            'direction' => 'from_staff',
            'author_id' => $author['id'] ?? null,
        ]);

        return $message;
    }

    /**
     * Staff suggests a product (in-thread card). Creates the message + the
     * SparProductSuggestion(offered), bumps the patient badge, nudges.
     *
     * @param  array{id?:int|null,name?:string|null,role?:string|null}  $author
     */
    public function suggestProduct(
        SparConversation $conversation,
        array $author,
        string $productName,
        ?int $priceCents = null,
        ?string $note = null,
    ): SparMessage {
        return DB::transaction(function () use ($conversation, $author, $productName, $priceCents, $note) {
            $body = 'Suggested product: ' . $productName
                . ($priceCents !== null ? ' (R' . number_format($priceCents / 100, 2) . ')' : '');

            $message = $conversation->messages()->create([
                'direction' => 'from_staff',
                'kind' => 'product_suggestion',
                'body' => $this->clip($body),
                'author_id' => $author['id'] ?? null,
                'author_name' => $author['name'] ?? null,
                'author_role' => $author['role'] ?? null,
            ]);

            SparProductSuggestion::create([
                'spar_message_id' => $message->id,
                'spar_conversation_id' => $conversation->id,
                'product_name' => $productName,
                'price_cents' => $priceCents,
                'note' => $note,
                'status' => 'offered',
            ]);

            $conversation->bumpUnread('patient');
            $conversation->touchLastMessage();

            $this->nudgePatient($conversation);

            $this->audit('coach_suggestion_sent', $conversation, [
                'message_id' => $message->id,
                'product' => $productName,
            ]);

            return $message->load('productSuggestion');
        });
    }

    /**
     * Patient accepts a suggestion → attach to their pending order (or create a
     * basket order), mark accepted, post a system line, bump staff badge.
     */
    public function acceptSuggestion(SparProductSuggestion $suggestion): SparProductSuggestion
    {
        if (! $suggestion->isOffered()) {
            return $suggestion;
        }

        return DB::transaction(function () use ($suggestion) {
            $conversation = $suggestion->conversation;
            $order = $this->resolvePendingOrder($conversation);

            $item = $order->items()->create([
                'source' => 'coach_suggestion',
                'product_name' => $suggestion->product_name,
                'price_cents' => $suggestion->price_cents,
                'qty' => 1,
                'note' => $suggestion->note,
            ]);

            $suggestion->markAccepted($order, $item);

            $this->postSystemMessage(
                $conversation,
                'Added ' . $suggestion->product_name . ' to your order (' . $order->reference . ').',
                'order_event'
            );

            $conversation->bumpUnread('staff');
            $conversation->touchLastMessage();

            $this->audit('coach_suggestion_accepted', $conversation, [
                'suggestion_id' => $suggestion->id,
                'order' => $order->reference,
                'order_item_id' => $item->id,
            ]);

            return $suggestion->fresh();
        });
    }

    /**
     * Patient declines a suggestion (captured for later upsell-decline insight).
     */
    public function declineSuggestion(SparProductSuggestion $suggestion): SparProductSuggestion
    {
        if (! $suggestion->isOffered()) {
            return $suggestion;
        }

        $conversation = $suggestion->conversation;
        $suggestion->markDeclined();

        $this->postSystemMessage(
            $conversation,
            'Declined the suggestion: ' . $suggestion->product_name . '.'
        );
        $conversation->touchLastMessage();

        $this->audit('coach_suggestion_declined', $conversation, [
            'suggestion_id' => $suggestion->id,
            'product' => $suggestion->product_name,
        ]);

        return $suggestion->fresh();
    }

    /**
     * Mark the OTHER side's messages read from the perspective of $side.
     * $side is who is READING ('patient' reading clears their own badge).
     */
    public function markRead(SparConversation $conversation, string $side): void
    {
        $unreadFrom = $side === 'patient' ? 'from_staff' : 'from_patient';

        $conversation->messages()
            ->where('direction', $unreadFrom)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->clearUnread($side);
    }

    // ---- internals ---------------------------------------------------------

    /**
     * Resolve the patient's current pending order at the conversation pharmacy
     * (requested|preparing). If none, create a lightweight basket order.
     */
    private function resolvePendingOrder(SparConversation $conversation): SparOrder
    {
        $existing = SparOrder::query()
            ->where('spar_patient_id', $conversation->spar_patient_id)
            ->where('spar_pharmacy_id', $conversation->spar_pharmacy_id)
            ->whereIn('status', ['requested', 'preparing'])
            ->latest('created_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return SparOrder::create([
            'spar_patient_id' => $conversation->spar_patient_id,
            'spar_pharmacy_id' => $conversation->spar_pharmacy_id,
            'type' => 'collection',
            'status' => 'requested',
            'notes' => 'Basket created from a Health Coach product suggestion.',
        ]);
    }

    private function postSystemMessage(SparConversation $conversation, string $body, string $kind = 'system'): SparMessage
    {
        return $conversation->messages()->create([
            'direction' => 'system',
            'kind' => $kind,
            'body' => $body,
        ]);
    }

    /**
     * Consent-gated, NO-PHI new-message nudge to the patient (spec NFR-3).
     * The dispatcher already refuses a non-consented patient.
     */
    private function nudgePatient(SparConversation $conversation): void
    {
        $patient = $conversation->patient;
        if (! $patient) {
            return;
        }

        $this->dispatcher->send($patient, [
            'subject' => 'New message from your SPAR pharmacy',
            'body' => 'You have a new message. Open your medication tracker to read and reply.',
            'link' => null,
        ]);
    }

    private function clip(string $body): string
    {
        $max = (int) config('spar.coach.max_message_len', 2000);

        return mb_substr(trim($body), 0, $max);
    }

    private function audit(string $action, SparConversation $conversation, array $context = []): void
    {
        $channel = config('logging.channels.spar_audit') ? 'spar_audit' : 'stack';

        Log::channel($channel)->info("{$action}: conversation #{$conversation->id}", array_merge([
            'action' => $action,
            'spar_conversation_id' => $conversation->id,
            'spar_patient_id' => $conversation->spar_patient_id,
            'spar_pharmacy_id' => $conversation->spar_pharmacy_id,
            'timestamp' => now()->toISOString(),
        ], $context));
    }
}
