# SPAR Close-the-Loop — Task List

**Status:** DRAFT. Pairs with requirements.md + design.md. Author: Naz. 2026-09-21.
Legend: [ ] pending · [~] in progress · [x] done · 🔵 blocked on a decision
Sequencing: Wave A (now, no blockers) → Wave B (engine) → Wave C (apply). D1/D2/D3 gate D/E.

## WAVE A — Quick wins (no blocking decisions) ✅ start here
- [ ] **A1 Scripts-on-hand summary** (FR-A1) — tracker top: per active journey "Name X/Y" from
      dispenses_completed/total_dispenses. View-only + a small component. + test.
- [ ] **A2 Per-insight definitions** (FR-A2) — tooltip/subtext under each Insights stat. Copy +
      a reusable `<x-spar::stat-hint>` or title attr. No logic.
- [ ] **A3 Reminder opt-in copy** (FR-A3) — NEVER / REMIND IN A MONTH / OPT IN + benefit line in
      reminder templates. (Wiring of the choices = B3; copy lands now.)
- [ ] **A4 Fix "can't add a new SPAR"** (FR-A4) — reproduce as super_admin + as group-less admin;
      fix SparPharmacies create (group_id default/selector + permission gate). Regression test.
- [ ] **A5** Browser-verify Wave A; npm run build; run suite.

## WAVE B — Actionable-item + response engine (the spine)
- [ ] **B1 State machine** (FR-B1) — SparActionableStatus enum + ResolvesActionable trait +
      lifecycle columns on SparPrescriptionJourney / SparDispenseRecord / SparOrder
      (migration). Scopes: openItems(), dueNow() (excludes snoozed). Tests for transitions.
- [ ] **B2 SparActionService** (FR-B2) — nudge(template) / snooze(days) / personalMessage(body)
      (routes to SparCoachService). Consent-gated + audited. Tests incl. snooze-excludes-from-list.
- [ ] **B3 Patient response** (FR-B3/B4) — spar_patient_signals migration + model;
      recordPatientResponse() writes signal + adjusts reminder schedule (+ resolve where apt);
      `<x-spar::response-actions>` widget. Tests: each signal type, schedule adjust, signal append.
- [ ] **B4 Reminder honouring** (FR-B4) — SparReminderService reads muted_until/opted_out/cadence
      from latest signal; skips snoozed. Tests.
- [ ] **B5 Auto-resolve reconciler** (FR-B5) — on import, flip resolved_at when dispense facts
      change. 🔵 D2 sets the rule (overlay vs system-wins) — build behind a config flag so both
      branches are testable; default to Naz overlay rec pending sign-off. Tests both branches.
- [ ] **B6 Exceptions action buttons** (FR-B6) — add `<x-spar::action-buttons>` to overdue /
      missing-renewal / unresponsive rows on SparExceptions. Browser-verify close-the-loop.
- [ ] **B7** Browser-verify engine end-to-end; build; suite.

## WAVE C — Apply the engine
- [ ] **C1 Patient order flow** (FR-C1) — "Order next meds" + mode dropdown
      (collect_pay_now/deliver_pay_now/collect_pay_store); fulfilment_mode + payment_status on
      SparOrder. Tests.
- [ ] **C2 Pharmacy orders dashboard** (FR-C2) — staff Livewire screen (list by status, process),
      auto-drop on dispense (B5), patient "order processed" alert w/ specials/support suggestion.
      Route + nav + page-header. Tests + browser.
- [ ] **C3 Renewals workflow** (FR-C3) — staff filter due-this-week/month; per-patient + bulk
      comms; patient responds via B3 widget. Tests + browser.
- [ ] **C4 Internal broadcast** (FR-C4) — staff compose → dispatcher fan-out to their consented
      patients (banner styling). Consent-gated. Tests.
- [ ] **C5 Lost-customer queue** (FR-C5) — stop/ignore signals → Insights panel + personalised
      win-back message action. Tests.
- [ ] **C6** Browser-verify Wave C; build; suite.

## WAVE D/E — BLOCKED on decisions
- [ ] 🔵 **D1 Dependant visibility** (FR-D1/D2) — implement chosen model
      (Naz rec: primary-sees-all, dependants-see-only-self). Viewer-scoped SparPatientView;
      per-dependant reminder routing + "For <name>:" labelling. Privacy test (dependant cannot
      load another member). BLOCKED until Captain Zan/Craig decide D1.
- [ ] 🔵 **E1 ZapMed funnel** (FR-E1) — copy + trigger on renewal_due per D3. BLOCKED on D3.

## CROSS-CUTTING (every wave)
- [ ] Extend package-purity grep test to all new files (NFR-3).
- [ ] Every new comms path: a consent-refusal test (NFR-1).
- [ ] npm run build before each demo/deploy (NFR-6); public/build is gitignored.
- [ ] Update the SPAR master memory + this tasklist as items complete.

## DECISIONS TO CHASE (owner: Captain Zan → Craig)
- 🔵 D1 dependant visibility · 🔵 D2 data-conflict rule · 🔵 D3 ZapMed funnel in standalone.
