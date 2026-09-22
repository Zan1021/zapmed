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

## WAVE C — Apply the engine ✅ DONE + DEPLOYED 2026-09-22 (spar.zapmed.africa)
- [x] **C1 Patient order flow** (FR-C1) — "Order next meds" + mode dropdown
      (collect_pay_now/deliver_pay_now/collect_pay_store); fulfilment_mode + payment_status on
      SparOrder (migration 2026_09_22_100026). On MyMedsTracker, consent-gated. SparPatientOrderTest (5).
- [x] **C2 Pharmacy orders dashboard** (FR-C2) — Admin\SparOrders Livewire (list by status, process
      requested→preparing→ready→completed), scope-gated (SparOrder::visibleToCurrentActor),
      consent-gated patient "order ready" alert (SparOrderService::markReady→MessagingDispatcher).
      Route admin.spar.orders + nav + page-header. SparOrdersDashboardTest (4).
- [x] **C3 Renewals workflow** (FR-C3) — Admin\SparRenewals filter due-this-week/month/all;
      per-patient remindOne + bulk remindAll via SparActionService::nudge (consent-gated →
      awaiting_patient); patient responds via B3 widget. SparRenewalsWorkflowTest (4).
- [x] **C4 Internal broadcast** (FR-C4) — Admin\SparBroadcast compose → dispatcher fan-out to
      visibleToCurrentActor+consented patients only. Consent-gated + audited. SparBroadcastTest (3).
- [x] **C5 Lost-customer queue** (FR-C5) — SparWinBackService (latest opt-out signal = lost) +
      Admin\SparWinBack queue + personalised win-back (consent-gated) + Insights lost-customers
      panel (SparStatsService::lostCustomersFor). SparWinBackTest (5).
- [x] **C6** Wave C closeout — standalone suite 121 pass / 1 pre-existing unrelated fail
      (SparTestToolsTest, not Wave C); ZapMed SPAR subset 106 pass (no cross-host regression);
      npm run build; browser-verified all 5 surfaces live; deployed local→GitHub→staging (247a83f).
      All package-pure (NFR-3), consent hard-locked (NFR-1). +21 tests.

## WAVE D/E
- [x] 🟢 **D1 Dependant visibility** (FR-D) — **DECIDED + BUILT + DEPLOYED 2026-09-22** (Craig via
      Captain Zan): dependants stay LISTED under the main member, but the main member does NOT see a
      dependant's medication — only the dependant sees their own. Display = name + masked placeholder
      "<Name>'s medication — private" (option b). BLANKET rule, all dependants. Impl:
      `SparPatientView::selfJourneys/selfHistory/selfPastJourneys` (primary-only); `MyMedsTracker`
      + `MyMedsHistory` switched to self-only + a `dependants` list panel; STAFF `PatientDetail`
      keeps the FULL roll-up for care (privacy applies to patient tracker only). Updated AC-9 tests
      (SparDependantRollupTest + SparPhase53VerificationTest) to the new rule. New
      `SparDependantPrivacyTest` (4). Standalone 126 pass / ZapMed SPAR 106 pass.
      ⚠ COMPLIANCE: adult-dependant privacy sign-off still recommended before wide rollout.
- [x] 🟢 **E1 ZapMed funnel** (FR-E1) — **DECIDED (D3=push) + BUILT + DEPLOYED 2026-09-22** (Captain Zan:
      "the entire idea behind the platform — SPAR pushes clients to zapmed.co.za to renew"). Standalone
      renewal card now leads with **"Book a ZapMed online consult"** → `https://zapmed.co.za` (config
      `spar.online_consult`, label updated, `enabled` default true; config-gated so it can be turned off).
      Per-journey + bottom renewal CTAs aligned. `SparRenewalFunnelTest` (2). Both hosts green
      (standalone 128 / ZapMed SPAR 106).

## CROSS-CUTTING (every wave)
- [ ] Extend package-purity grep test to all new files (NFR-3).
- [ ] Every new comms path: a consent-refusal test (NFR-1).
- [ ] npm run build before each demo/deploy (NFR-6); public/build is gitignored.
- [ ] Update the SPAR master memory + this tasklist as items complete.

## DECISIONS TO CHASE (owner: Captain Zan → Craig)
- 🔵 D1 dependant visibility · 🔵 D2 data-conflict rule · 🔵 D3 ZapMed funnel in standalone.
