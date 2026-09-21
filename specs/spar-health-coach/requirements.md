# SPAR — Health Coach v1 (patient ↔ pharmacy messenger + suggest-to-basket)

**Status:** Draft for review
**Author:** Naz (Team Lead)
**Date:** 2026-09-19
**Related:** `specs/spar-standalone/`, `specs/spar-staff-patient-view/`, `specs/spar-group-banners/`
**Source:** Craig/SPAR post-demo testing notes (2026-09-19). See vault `2026-09-19_mem_spar-client-feedback-and-decisions.md`.

---

## 1. Problem / Idea

The patient mobi tracker is a read-only "filing cabinet" — it shows scripts, next collection, and
consent, but nobody is *talking* to the patient. SPAR's stated key differentiator is a **Health
Coach layer**: a human on the other end who holds the patient's hand, answers questions, offers
customer service, and — crucially for SPAR's revenue — **recommends products (supplements, support
items) and adds them to the patient's basket/next order.**

This turns the tracker from a cold ledger into (a) a relationship and (b) a soft, helpful sales
channel — Craig's twin north stars: *patient = minimum noise / maximum value*, *pharmacy = minimum
admin / maximum sales & retention*.

## 2. Scope decision (locked by Captain Zan 2026-09-19)

- **v1 = demo-grade.** Build the real, working magic: a threaded patient ↔ staff conversation and
  the "suggest a product → patient taps Add to my order" flow. This is enough to *show SPAR the
  vision* and let their reaction shape v2.
- **Coach = the existing pharmacy staff role.** No new "coach" user type. Any staffer scoped to the
  patient's pharmacy is the coach. Modelled so a dedicated coach role can be split out later without
  a repaint.
- **Explicitly OUT of v1** (see Non-Goals): AI auto-replies, canned-response libraries, message
  routing/queues/SLAs, typing indicators/read receipts, a real product catalogue with live
  stock/pricing sync, dedicated coach accounts, coach performance dashboards.

## 3. Goals

- **G1** A patient on their (no-login, consent-gated) mobi tracker can open a **Messages** thread and
  exchange messages with their pharmacy.
- **G2** Pharmacy staff, from the existing staff patient view, can open the same thread and reply.
- **G3** Staff can send a **suggested-product card** into the thread; the patient can tap
  **"Add to my order"** and the item is attached to their pending/next order (basket).
- **G4** Everything is **consent-gated** and **audit-logged**, consistent with existing SPAR PHI rules.
- **G5** The messenger reuses existing infra (no drift): `MessagingDispatcher`/`InAppChannel` for the
  "you have a new message" nudge, `SparPatientView` for patient/dependant scoping, `SparOrderService`
  for the basket, and stays **package-pure** (no host `User`/`Prescription` classes in spar-core).

## 4. Non-Goals (v1)

- No AI/auto-generated coach replies. A human staffer types every coach message.
- No product catalogue integration, live stock, or pricing/promotions sync from SPAR's system. v1
  products are **free-text/lightweight suggestion cards** (name, optional price, optional note),
  not catalogue SKUs.
- No dedicated coach accounts, coach queues, routing, SLAs, typing indicators, or read receipts.
- No new patient login. Patients stay on the tokenised, OTP/consent-gated session.
- No WhatsApp two-way chat. WhatsApp is still deferred (no Meta creds); the messenger is in-app.
  The "new message" nudge rides the existing dispatcher, so it upgrades to WhatsApp automatically
  once that channel is provisioned — no messenger rework.
- No dependant-level separate threads in v1 (thread is at the **profile/primary** level, matching the
  roll-up model). Per-dependant threading is a v2 open question (see §8).

## 5. Data gaps to close (audited from code)

Nothing like a conversation/message/thread exists — grep of `packages/spar-core` for
`coach|messenger|conversation|thread` returns **zero hits**. This is net-new.

Relevant existing anchors we build ON (not duplicating):
- `SparPatient` — the profile/primary member; `SparPatientView` resolves primary + dependants +
  journeys (one source of truth for roll-up).
- `SparOrder` (`spar_orders`) — statuses `requested|preparing|ready|completed|cancelled`, `type`
  `collection|delivery`, references `SP-XXXXXXXX`. This is the "basket"/order target for G3.
- `MessagingDispatcher::send(SparPatient, payload)` — **hard-stops if `!hasConsented()`**; always
  records an in-app companion via `InAppChannel`. We reuse this for the new-message nudge.
- `LogsSparActivity` + `spar_audit` log channel — audit trail.
- Pharmacy scope: `SparPharmacy::visibleToCurrentActor()` + `SparIdentityProvider` (super/group/
  pharmacy/staff), national-aware.

**FR-DATA** New tables (details in design.md):
`spar_conversations`, `spar_messages`, `spar_product_suggestions` (or a message `kind` +
suggestion columns — design decides). No FK to any host `User`; staff author stored as a plain
`author_role` + `author_id` + `author_name` snapshot (package-purity, mirrors `SparConsent` /
staff-patient-view NFR-3).

## 6. Functional Requirements

### Conversation & messaging
- **FR-1** A `SparConversation` belongs to exactly one **profile (primary `SparPatient`)** and one
  `spar_pharmacy_id`. Created lazily on first message from either side. At most one **open**
  conversation per (patient, pharmacy).
- **FR-2** A `SparMessage` belongs to a conversation, has a `direction`
  (`from_patient` | `from_staff`), a `kind` (`text` | `product_suggestion` | `system`), a `body`,
  timestamps, and a nullable `read_at`. Staff messages snapshot `author_id`/`author_name`/`author_role`.
- **FR-3 (patient side)** On the mobi tracker (after consent), a **Messages** panel shows the thread
  newest-last, lets the patient send a text message, and shows an unread count. Sending is blocked
  with a friendly note if the patient is not consented (defence-in-depth; the tracker already gates).
- **FR-4 (staff side)** From the staff patient view (`spar-staff-patient-view`), staff open the same
  thread and can send a text reply or a product suggestion, scoped to a patient in their pharmacy.
- **FR-5** Sending a message from either side triggers a **new-message nudge** to the other side:
  - staff→patient: `MessagingDispatcher::send()` (consent-gated, in-app + push) with neutral copy
    ("You have a new message from your SPAR pharmacy") — **no PHI / no med names** in the nudge
    (consistent with existing comms rules).
  - patient→staff: surfaces as an unread badge on the staff patient list / dashboard (pull), no PHI leaves the system.
- **FR-6** Unread accounting per side (`read_at` set when the recipient opens the thread).

### Suggest-to-basket
- **FR-7** Staff can compose a **product suggestion**: `product_name` (required), `price_cents`
  (optional), `note` (optional), and send it as a `product_suggestion` message.
- **FR-8** The patient sees the suggestion as a card in-thread with an **"Add to my order"** action
  and a **"No thanks"** action.
- **FR-9** "Add to my order" attaches the suggested item to the patient's **current pending order**
  for that pharmacy (status `requested`/`preparing`); if none exists, it creates a lightweight
  order/basket to hold it (design decides: reuse `SparOrder` + a new `spar_order_items`, or an
  order `extras` collection). The suggestion is marked `accepted` (with `accepted_at`); a `system`
  message records the acceptance in-thread ("Added Magnesium to your order").
- **FR-10** "No thanks" marks the suggestion `declined` (with `declined_at`) — this is a v2 insight
  signal (upsell decline), captured now, surfaced later.
- **FR-11** Accepting a suggestion notifies the pharmacy (unread badge + optional dispatcher nudge to
  staff-side) so they know to add the item when preparing the order.

### Consent, scope, audit
- **FR-12** No message is *delivered outward* to a non-consented patient (dispatcher hard-stop). A
  patient at the consent gate cannot use the messenger (the tracker already blocks pre-consent).
- **FR-13** Staff access to a conversation is **scope-gated** exactly like the patient view
  (pharmacy/group/super, national-aware); opening/replying outside scope → 403.
- **FR-14** Every staff open of a thread logs a `patient_access` (PHI-read) audit entry; every send
  (either side) and every suggestion accept/decline logs a `spar_audit` event
  (`coach_message_sent`, `coach_suggestion_sent`, `coach_suggestion_accepted`, `coach_suggestion_declined`).

## 7. Non-Functional

- **NFR-1 Package purity:** spar-core must not reference `App\Models\User` / `Prescription` /
  `UserRole`. Staff identity on a message = plain `author_id` + snapshot `author_name` + `author_role`
  string (resolved via `SparIdentityProvider`), mirroring `SparConsent` and staff-patient-view NFR-3.
- **NFR-2 Dual-host:** works identically in standalone and integrated ZapMed (no telehealth coupling).
- **NFR-3 No PHI in outbound nudges:** the "new message" SMS/email/WhatsApp nudge carries neutral copy
  only — no medication names, no message body, no patient identifiers beyond the signed tracker link.
- **NFR-4 Reuse, don't drift:** patient/dependant resolution via `SparPatientView`; order attach via
  `SparOrderService`; nudges via `MessagingDispatcher`. No parallel implementations.
- **NFR-5 Encryption:** message `body` may contain health context → store on a TEXT column and apply
  `EncryptsSensitiveFields` (consistent with `profile_code`/`cellphone`/`email`). NOTE the known
  gotcha: encrypted columns cannot be queried with `where()` — filter/scope by conversation id, not body.
- **NFR-6 No regressions:** all existing SPAR suites stay green; the one pre-existing unrelated Auth
  failure (dashboard 302) stays at exactly 1 and is not ours.

## 8. Open questions (v2, do not block v1)

- **OQ-1** Per-dependant threads vs one profile thread? v1 = one profile thread (matches roll-up).
- **OQ-2** Should a real SPAR product catalogue/stock feed replace free-text suggestions? (Needs SPAR data.)
- **OQ-3** Dedicated coach role / ZapMed-provided coaches as a service (would split from pharmacy staff).
- **OQ-4** Upsell-decline + acceptance analytics surface (ties into the Theme-2 insights engine — Wave 2).
- **OQ-5** Patient-initiated "start a chat" entry copy — tie to the health-coach intro/branding.

## 9. Acceptance Criteria

- **AC-1** A consented patient can open Messages on the tracker, send a text, and see staff replies.
- **AC-2** Staff (scoped to the patient's pharmacy) can open the same thread and reply.
- **AC-3** A staff→patient message produces a consent-gated new-message nudge with **no PHI** in it.
- **AC-4** Staff can send a product suggestion; the patient sees an "Add to my order" card.
- **AC-5** Tapping "Add to my order" attaches the item to the patient's pending order (or creates one),
  marks the suggestion accepted, posts a system line in-thread, and notifies the pharmacy.
- **AC-6** "No thanks" marks the suggestion declined (captured for later insight).
- **AC-7** A non-consented patient cannot use the messenger; no outward message is delivered to them.
- **AC-8** Staff cannot open/reply to a conversation outside their scope (national-aware) → 403.
- **AC-9** Opening a thread (staff) writes `patient_access`; sends & accept/decline write `spar_audit` events.
- **AC-10** spar-core contains no host `User`/`Prescription`/`UserRole` reference (grep = 0) — package-pure.
- **AC-11** Works in both standalone and integrated modes with no telehealth dependency.
- **AC-12** All existing SPAR suites still pass; new tests cover AC-1..AC-11.
