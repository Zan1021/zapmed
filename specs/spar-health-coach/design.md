# SPAR — Health Coach v1 — Design

**Status:** Draft for review
**Author:** Naz (Team Lead)
**Date:** 2026-09-19
**Implements:** `specs/spar-health-coach/requirements.md`

---

## 1. Architecture overview

All code lands in `packages/spar-core` (shared by standalone + integrated). Three new tables, three
new models, one service, two Livewire surfaces (staff + patient), reusing existing infra:

```
Patient tracker (MyMedsTracker)            Staff patient view (PatientDetail)
        │  Messages panel (new)                   │  Messages tab (new)
        ▼                                          ▼
              SparCoachService  ───────────────────────────────
              • openConversation(primary, pharmacy)
              • postPatientMessage() / postStaffMessage()
              • suggestProduct()  • acceptSuggestion() • declineSuggestion()
              • markRead()
                     │
     ┌───────────────┼───────────────────────────────┐
     ▼               ▼                                ▼
 spar_conversations  spar_messages            SparOrderService (existing)
 spar_messages       spar_product_suggestions   → attach item to pending order
                     │                          → spar_order_items (new)
                     ▼
        MessagingDispatcher (existing, consent-gated)  → new-message nudge (no PHI)
        LogsSparActivity + spar_audit (existing)       → audit
        SparPatientView (existing)                     → primary/dependant scoping
        SparIdentityProvider (existing)                → staff identity + scope gate
```

Design principles honoured (from the mature codebase):
- **Package purity (NFR-1/AC-10):** no `App\Models\User`. Staff author is `author_id` + snapshot
  `author_name` + `author_role` (string). Mirrors `SparConsent` evidence rows + PatientDetail's
  `staff_name_resolver` pattern.
- **One source of truth (NFR-4):** conversation is always bound to the **primary member** resolved by
  `SparPatientView::primary()`. Never a dependant. Never a duplicate implementation.
- **Consent hard-stop (FR-12):** outward nudges go through `MessagingDispatcher::send()`, which already
  returns false for a non-consented patient. The messenger UI is only reachable *after* the consent
  gate on the tracker.

## 2. Data model

### 2.1 `spar_conversations`
| column | type | notes |
|---|---|---|
| id | pk | |
| spar_patient_id | fk → spar_patients | the **primary** member |
| spar_pharmacy_id | fk → spar_pharmacies | which pharmacy the coach speaks for |
| status | string | `open` \| `closed` (v1 stays `open`) |
| last_message_at | datetime nullable | for list sorting |
| patient_unread_count | unsignedInt default 0 | badge for patient |
| staff_unread_count | unsignedInt default 0 | badge for staff |
| created_at / updated_at | | |

Unique-ish invariant enforced in the service: **one `open` conversation per (patient, pharmacy)**.
Index `[spar_patient_id, spar_pharmacy_id, status]` and `[spar_pharmacy_id, last_message_at]`.

### 2.2 `spar_messages`
| column | type | notes |
|---|---|---|
| id | pk | |
| spar_conversation_id | fk → spar_conversations (cascade) | |
| direction | string | `from_patient` \| `from_staff` \| `system` |
| kind | string | `text` \| `product_suggestion` \| `system` |
| body | **text** | **encrypted** (EncryptsSensitiveFields) — may hold health context (NFR-5) |
| author_id | unsignedBigInt nullable | staff id (host user id) for `from_staff`; null for patient/system |
| author_name | string nullable | **snapshot** of staff display name (package purity) |
| author_role | string nullable | `pharmacy_staff` etc. |
| read_at | datetime nullable | set when the *other* side reads |
| created_at / updated_at | | |

Index `[spar_conversation_id, id]` (thread order). Because `body` is encrypted we **never** `where()`
on it — we always scope by `spar_conversation_id` (known gotcha, called out in NFR-5).

### 2.3 `spar_product_suggestions`
Kept as its own row (1:1 with the suggestion message) so accept/decline analytics (OQ-4) are clean
and don't overload `spar_messages`.
| column | type | notes |
|---|---|---|
| id | pk | |
| spar_message_id | fk → spar_messages (cascade) | the in-thread card |
| spar_conversation_id | fk → spar_conversations (cascade) | denormalised for quick queries |
| product_name | string | required |
| price_cents | unsignedInt nullable | optional |
| note | string nullable | optional coach note (NOT encrypted — product marketing copy, not PHI) |
| status | string | `offered` \| `accepted` \| `declined` |
| accepted_at / declined_at | datetime nullable | |
| spar_order_id | fk → spar_orders nullable | set on accept |
| spar_order_item_id | fk → spar_order_items nullable | set on accept |
| created_at / updated_at | | |

### 2.4 `spar_order_items` (basket lines)
`SparOrder` currently has no line items — it wraps a single dispense. To hold coach-suggested extras
without perverting the dispense model, add a lightweight line-item table. This is the "basket".
| column | type | notes |
|---|---|---|
| id | pk | |
| spar_order_id | fk → spar_orders (cascade) | |
| source | string | `coach_suggestion` (v1) \| `manual` (future) |
| product_name | string | |
| price_cents | unsignedInt nullable | |
| qty | unsignedInt default 1 | |
| note | string nullable | |
| created_at / updated_at | | |

## 3. Models

- `SparConversation` — `belongsTo` patient + pharmacy; `hasMany` messages; scopes `open()`,
  `forPharmacy($id)`, `visibleToCurrentActor()` (delegates to the pharmacy/group/super logic via
  `SparIdentityProvider`, mirroring `SparPatient`). Helpers: `touchLastMessage()`,
  `bumpUnread('patient'|'staff')`, `clearUnread(side)`.
- `SparMessage` — `belongsTo` conversation; `hasOne` productSuggestion; `EncryptsSensitiveFields`
  on `['body']`; casts `read_at`. Scope `chronological()`.
- `SparProductSuggestion` — `belongsTo` message, conversation, order, orderItem; status helpers
  `markAccepted($order,$item)`, `markDeclined()`.
- `SparOrderItem` — `belongsTo` order. `SparOrder` gains `items(): HasMany` + a `basketTotalCents()`.

## 4. Service — `SparCoachService`

Single orchestration seam (keeps Livewire thin, testable without a browser):

```
openConversation(SparPatient $any, int $pharmacyId): SparConversation
    // resolves to primary via SparPatientView, finds/creates the open conversation

postPatientMessage(SparConversation $c, string $body): SparMessage
    // direction from_patient; bump staff_unread; touch; audit coach_message_sent(patient)
    // (patient→staff needs no dispatcher nudge — staff pull via unread badge)

postStaffMessage(SparConversation $c, string $body, array $author): SparMessage
    // direction from_staff, snapshot author; bump patient_unread; touch;
    // fire MessagingDispatcher nudge (consent-gated, neutral copy, NO PHI); audit

suggestProduct(SparConversation $c, array $author, string $name, ?int $priceCents, ?string $note): SparMessage
    // creates from_staff kind=product_suggestion message + SparProductSuggestion(offered)
    // bump patient_unread; nudge; audit coach_suggestion_sent

acceptSuggestion(SparProductSuggestion $s): SparProductSuggestion
    // find/create the patient's pending order for the conversation pharmacy via
    // SparOrderService semantics; add SparOrderItem(source=coach_suggestion);
    // mark suggestion accepted (+order/item ids); post a `system` message
    // ("Added X to your order"); bump staff_unread; audit coach_suggestion_accepted

declineSuggestion(SparProductSuggestion $s): SparProductSuggestion
    // mark declined; post system message; audit coach_suggestion_declined

markRead(SparConversation $c, 'patient'|'staff'): void
    // set read_at on the other side's unread messages; clearUnread(side)
```

**Pending-order resolution for accept (FR-9):** look for the patient's most recent `SparOrder` at that
pharmacy with status in `requested|preparing`. If found, attach the line. If none, create a minimal
`SparOrder` (type defaults `collection`, status `requested`, no dispense_record) to act as the basket.
Reason: coach upsells aren't tied to a specific dispense; they're an extra on the next collection.

**Author payload** comes from the staff Livewire component via `SparIdentityProvider` +
`config('spar.staff_name_resolver')` (same resolver PatientDetail already uses), so no host class
is named in the package.

## 5. Livewire surfaces

### 5.1 Patient — `PatientCoachMessages` (embedded in the tracker)
- Rendered inside `MyMedsTracker`'s dashboard step (a new "Messages" panel/tab), only after consent.
- Resolves the conversation for `session patient primary` + their pharmacy (via `SparPatientView`).
- Lists messages chronologically; `product_suggestion` renders as a card with **Add to my order** /
  **No thanks**; `system` renders as a centered muted line.
- Actions: `send($body)`, `accept($suggestionId)`, `decline($suggestionId)`. On mount + on open,
  `markRead('patient')`.
- Consent defence-in-depth: if `!patient->hasConsented()` the input is disabled with a note.
- Registered alias `spar.patient-coach-messages`.

### 5.2 Staff — `StaffCoachMessages` (a tab on `PatientDetail`)
- New `tab = 'messages'` on PatientDetail (extend the `setTab` allow-list) OR a self-contained
  component embedded in the detail view. Chosen: **embedded component** `spar.staff-coach-messages`
  taking `patientId`, so PatientDetail stays a read-only mirror and the messenger is the one place
  staff can *act*. Scope gate re-checked in the component mount (`visibleToCurrentActor`), 403 if out.
- Lists the same thread; staff can send text and open a "Suggest a product" mini-form
  (name / optional price / optional note). On mount `markRead('staff')`; logs `patient_access`.

## 6. Nudges & copy (NFR-3)

Staff→patient nudge payload to `MessagingDispatcher::send($primary, [...])`:
```
subject: "New message from your SPAR pharmacy"
body:    "You have a new message. Open your medication tracker to read and reply."
link:    signed my-meds tracker URL (temporarySignedRoute 'spar.track')
```
No medication names, no message content, no identifiers — matches the existing reminder-copy rule.
Patient→staff: no outward message; surfaces as `staff_unread_count` badge on PatientList/dashboard.

## 7. Config additions (`config/spar.php`)
```php
'coach' => [
    'enabled' => env('SPAR_COACH_ENABLED', true),
    'max_message_len' => 2000,
],
```
Register the 3 new Livewire aliases in `SparCoreServiceProvider::registerLivewireComponents()`.
No new routes strictly required (both surfaces are embedded), but add an unread-count poll-friendly
computed property. Keep everything under existing route middleware groups.

## 8. Package purity / dual-host (NFR-1/NFR-2/AC-10/AC-11)
- No `App\Models\User`, `Prescription`, `UserRole` anywhere in the new code (grep gate in tasks).
- Staff identity via `SparIdentityProvider` + snapshot columns; name via `spar.staff_name_resolver`.
- No `TelehealthBridge` touch — the coach is orthogonal to renewal/teleconsult. Works standalone.

## 9. Security / POPIA
- `body` encrypted at rest (NFR-5). Nudges carry no PHI (NFR-3).
- Staff open of a thread = `patient_access` audit; sends & accept/decline = `spar_audit` events (FR-14).
- Scope gate on both the conversation model scope and the staff component mount (AC-8).
- Consent hard-stop preserved (AC-7) — dispatcher refuses non-consented; UI gated post-consent.

## 10. Testing strategy (see tasks Phase 0 + final gate)
- **Characterisation first:** capture the current SPAR suite pass count as the regression baseline
  BEFORE touching anything (the 1 known unrelated Auth failure must stay exactly 1).
- **Feature tests** (no browser) drive `SparCoachService` for every AC: open/reuse conversation,
  patient/staff post + unread accounting, consent hard-stop, suggest → accept attaches order item +
  system line + statuses, decline, scope 403, audit events, package-purity grep.
- **Chrome/manual** (per Captain Zan's steer): seed demo data, log in as pharmacy staff
  (`staff@sparmeds.test` / `Testing123!`), open a demo patient, send a message + a product
  suggestion; open the patient tracker via a signed link, reply, tap "Add to my order", confirm the
  system line + the order gains the item. Screenshot-free; assert via DB + on-screen state.

## 11. Rejected alternatives
- *Per-dependant threads:* rejected for v1 — conversation binds to primary (matches roll-up). (OQ-1)
- *Overloading spar_messages with suggestion columns:* rejected — separate table keeps upsell
  analytics clean (OQ-4) and messages lean.
- *Adding a `coach` role now:* rejected per Captain Zan — pharmacy staff = coach; extract later (OQ-3).
- *Storing suggested products as SparOrder rows directly:* rejected — needs line items; added
  `spar_order_items` so one order can hold a dispense + coach extras.
