# SPAR Close-the-Loop + Patient/Pharmacy Workflow — Requirements

**Status:** DRAFT for approval (Captain Zan + Craig). Author: Naz. Date: 2026-09-21.
**Source:** Craig's post-demo testing notes (2026-09-19 + 2026-09-21 restate). Supersedes the scattered bullets with a single scoped spec.
**Relationship to existing work:** Health Coach v1 (spec spar-health-coach) is BUILT. This spec covers everything else Craig raised. Do NOT re-scope the coach here.

## 0. GUIDING NORTH STARS (Craig)
- **Patient:** minimum noise, maximum value.
- **Pharmacy:** minimum admin, maximum sales potential / retention.

## 1. THE CORE INSIGHT (drives the whole design)
Almost every request is the SAME primitive: an **actionable item** with a lifecycle
`open -> actioned -> awaiting_patient -> resolved` (+ `snoozed_until`), a set of **staff
actions**, a mirrored **patient structured response**, and **auto-resolution** when the
underlying fact changes. Exceptions, orders, and renewals are all instances of it.
Build the engine ONCE; apply three times. Anti-goal: 3 bespoke workflows.

## 2. ALREADY LIVE (context, not to build)
- Health Coach messenger + suggest-product-to-basket (consent-gated). [spar-health-coach]
- Hard consent lock in MessagingDispatcher (no comms to non-consented). 
- Dependant roll-up under a primary member (SparPatientView).
- Renewal detection (journey.status = renewal_due) + tracker renewal card.
- SparOrder + SparOrderService + spar_order_items (used by coach).
- Insights page (SparStatsService) + per-page location headers + "Powered by ZapMed".

## 3. FUNCTIONAL REQUIREMENTS

### FR-A — Quick wins (Wave A; no blocking decisions)
- **FR-A1 Scripts-on-hand summary:** top of the patient tracker shows each active
  script as "Name X/Y" (dispenses_completed / total_dispenses). Data already exists.
- **FR-A2 Per-insight definitions:** a short tooltip/definition under each Insights stat.
- **FR-A3 Reminder opt-in copy:** any reminder offers NEVER / REMIND ME IN A MONTH /
  OPT IN, plus one line explaining the benefit of pharmacy comms.
- **FR-A4 Fix "can't add a new SPAR":** SparPharmacies create must work for an
  authorised admin (group_id + permission gating). Real defect.

### FR-B — Actionable-item + response engine (Wave B; the spine)
- **FR-B1** A unified item state machine (open/actioned/awaiting_patient/resolved/snoozed)
  over exceptions, orders, renewals. May extend existing models rather than a new table —
  design decides.
- **FR-B2 Staff actions (reusable):** Nudge (standard template), Snooze (sleeps N days →
  `snoozed_until`, drops off list until then), Personalised message (free text → posts into
  the existing coach thread).
- **FR-B3 Patient structured response (reusable widget):** Yes — collecting / Yes — deliver /
  Remind me in N days / Remind next cycle / Stop reminding me.
- **FR-B4** Every patient response (a) adjusts the reminder schedule and (b) writes an
  insight signal (typed event) for later mining.
- **FR-B5 Auto-resolve:** an item leaves the dashboard when the underlying fact changes
  (next import shows dispensed; patient collected). No manual "mark done" required for the
  common path.
- **FR-B6** Exceptions view (overdue dispenses / missing renewals / unresponsive) gains the
  FR-B2 action buttons (today it is read-only lists).

### FR-C — Apply the engine (Wave C)
- **FR-C1 Patient order flow:** "Order next meds" button → dropdown Collect & pay now /
  Deliver & pay now / Collect & pay at store → creates an order item.
- **FR-C2 Pharmacy orders dashboard:** lists placed orders; staff process; order auto-drops
  when dispensed (FR-B5). Patient gets "Order processed — come collect" alert, which may
  suggest specials/support (reuse coach + banner infra).
- **FR-C3 Renewals workflow:** pharmacy filter "due this week / this month"; per-patient OR
  bulk comms ("your script is due, shall we prepare it?"); patient responds via FR-B3.
- **FR-C4 Pharmacy internal broadcast:** pharmacist pushes specials / closing-time changes
  to THEIR patients (reuse dispatcher + banners; consent-gated).
- **FR-C5 Lost-customer queue:** "Stop reminding me" / "ignore future" responses feed a
  follow-up queue for win-back with a personalised incentive. Surfaced in Insights.

### FR-D — Dependants & privacy (BLOCKED on decision D1)
- **FR-D1** Define profile ↔ member visibility: primary-sees-all + dependants-see-only-self
  (Naz recommendation) vs full household visibility.
- **FR-D2** Per-dependant reminder routing + labelling ("For Anjali:") so the primary is not
  confused by dependant messages; decide whether dependants can hold their own contact.

### FR-E — ZapMed funnel (BLOCKED on decision D3)
- **FR-E1** Decide if/when standalone surfaces "Book a ZapMed online consult" (e.g. on
  renewal_due) vs keeping "see your own doctor".

## 4. NON-FUNCTIONAL / CONSTRAINTS
- **NFR-1 Consent hard-lock** stays absolute — every new comms path routes through the
  consent-gated dispatcher. No exceptions.
- **NFR-2 POPIA:** message bodies encrypted at rest (as coach already does); dependant
  visibility must not leak PHI across members (see D1).
- **NFR-3 Package purity:** all domain logic in packages/spar-core; no host User/Prescription
  class references (enforced by existing purity test — extend it to new files).
- **NFR-4 Two hosts:** integrated ZapMed + standalone must both work; layouts/bridges stay
  host-supplied via config.
- **NFR-5 Demo-grade:** v1 is demo quality — real flows, no catalogue/stock/pricing sync, no
  AI. Don't gold-plate.
- **NFR-6 Deploy:** public/build assets are gitignored → npm run build on deploy.

## 5. OPEN DECISIONS (block parts of the build)
- **D1 (blocks FR-D):** Dependant visibility model. Naz rec = primary-sees-all,
  dependants-see-only-self.
- **D2 (blocks reconciliation in FR-B5/FR-C2):** Data-conflict rule — imported-file-wins-
  with-overlay (Naz rec) vs SPAR-system-wins. Our counts are inferred from the import; SPAR's
  dispensing system is real-time; they WILL disagree.
- **D3 (blocks FR-E):** ZapMed funnel in standalone — teleconsult on renewal now vs
  own-doctor until integrated. Commercial call.

## 6. ACCEPTANCE (high level; detailed AC live in design/tasks)
- Wave A visibly demoable with no decisions taken.
- The engine (Wave B) is one implementation reused by exceptions + orders + renewals.
- Every comms path provably refuses a non-consented patient.
- Dependant privacy cannot leak once D1 is chosen.
