# SPAR Close-the-Loop — Design

**Status:** DRAFT. Pairs with requirements.md. Author: Naz. Date: 2026-09-21.
**Principle:** one engine, applied three times. Extend existing models where sane; add the
smallest new schema that makes the state machine + response signals first-class.

## 1. DATA MODEL

### 1.1 Actionable items (FR-B1)
Two viable shapes — design decision, leaning (B):
- (A) One polymorphic `spar_actionables` table (type, subject_type/id, status, snoozed_until,
  last_action, resolved_at). Clean, but a new join layer over journeys/dispenses/orders.
- (B) **Add lifecycle columns to the existing subjects** (SparPrescriptionJourney,
  SparDispenseRecord, SparOrder): `action_status`, `snoozed_until`, `last_action_at`,
  `resolved_at`, and read them through a shared `SparActionable` VIEW/trait. Less migration
  churn, keeps each subject authoritative. **RECOMMENDED for demo-grade.**
- Either way: a shared `SparActionableStatus` enum (open|actioned|awaiting_patient|resolved|snoozed)
  and a `ResolvesActionable` trait with scopes (openItems(), dueNow() excluding snoozed).

### 1.2 Patient responses + insight signals (FR-B4)
- New `spar_patient_signals` (append-only): spar_patient_id, subject_type/id, signal
  (yes_collect|yes_deliver|remind_in_days|remind_next_cycle|stop_reminders|ignore_month|
  ignore_future), payload (e.g. days), channel, created_at. Never updated — it is the audit +
  the insight feed + the lost-customer source.
- Reminder schedule adjustment reads the latest signal per subject.

### 1.3 Reminder preferences (FR-A3 / FR-B3)
- Per (patient, subject-or-global) preference: cadence, muted_until, opted_out. Derived from
  the latest signal; store a small `spar_reminder_prefs` or fold into the patient. Design
  decides; a column set on the journey is enough for demo.

### 1.4 Orders (FR-C1/2)
- Reuse SparOrder + spar_order_items. Add `fulfilment_mode`
  (collect_pay_now|deliver_pay_now|collect_pay_store) + `payment_status`. Lifecycle uses the
  same action_status columns (1.1) so orders auto-drop on dispense.

## 2. THE ENGINE (services)
- **SparActionService** — the single seam. Methods: nudge(item, template), snooze(item, days),
  personalMessage(item, body) [-> posts to coach thread via SparCoachService], and
  recordPatientResponse(subject, signal, payload) [-> writes spar_patient_signals + adjusts
  reminders + maybe resolves]. All consent-gated via MessagingDispatcher; all audited.
- **Auto-resolve (FR-B5):** on import, a reconciler compares new dispense facts to open items
  and flips resolved_at. This is where D2 (data-conflict rule) lands: imported-file = truth for
  history; in-app actions = overlay shown "pending (our side)" until the next file confirms.
- **Reminders:** SparReminderService (exists) extended to honour muted_until / opted_out /
  cadence from signals, and to skip snoozed items.

## 3. UI SURFACES (reuse the <x-spar::page-header> + coach patterns)
- **Patient tracker:** scripts-on-hand summary (FR-A1); a reusable
  `<x-spar::response-actions>` widget (Yes-collect / Yes-deliver / Remind N / Remind next /
  Stop) reused on reminder cards, renewal cards, order prompts; "Order next meds" button + mode
  dropdown (FR-C1). Every reminder card carries the NEVER/MONTH/OPT-IN + benefit copy (FR-A3).
- **Staff PatientDetail / Exceptions:** a reusable `<x-spar::action-buttons>` (Nudge / Snooze /
  Message) on every actionable row (FR-B2, FR-B6). Message routes into the coach thread.
- **Pharmacy Orders dashboard:** new staff Livewire screen listing orders by status; process ->
  patient alert; auto-drop on dispense (FR-C2).
- **Renewals view:** staff filter this-week/this-month; per-patient + bulk comms (FR-C3).
- **Internal broadcast:** staff compose -> dispatcher fan-out to their consented patients,
  reusing banner styling (FR-C4).
- **Insights:** per-stat tooltips (FR-A2); a Lost-Customers panel from stop/ignore signals
  (FR-C5).

## 4. DECISIONS' IMPACT ON DESIGN
- **D1 dependant visibility:** if primary-sees-all/dependants-see-self, SparPatientView.members()
  gains a viewer-scoped variant; the tracker roll-up filters by viewer. If full household, no
  change. MUST be resolved before FR-D build.
- **D2 data conflict:** picks the reconciler rule in §2 auto-resolve. Overlay model =
  add a `source` + `provisional` flag to in-app dispense-affecting actions.
- **D3 funnel:** a config flag already exists (spar.online_consult); decision only changes copy
  + when the CTA shows on renewal_due.

## 5. WHAT WE ARE NOT BUILDING (v1)
- Catalogue / stock / pricing sync; AI auto-reply; dedicated coach accounts; real payment
  gateway wiring beyond mode capture; cross-member household chat.

## 6. TESTING STRATEGY
- Unit: state machine transitions, snooze exclusion, signal writing, reminder adjustment,
  reconciler auto-resolve (both D2 branches behind a config so we can flip).
- Consent: every new comms path refuses non-consented (mirror coach tests).
- Privacy: once D1 chosen, a test that a dependant cannot load another member's history.
- Browser (Dusk/Playwright): patient response widget, order placement, staff action buttons,
  orders dashboard drop-off.
- Package purity: extend the existing grep test to new files.
