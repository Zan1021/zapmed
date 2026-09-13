# Stats Module — Scope + Gap Analysis (Task 7)

**Author:** Naz · **Date:** 2026-09-15 · **Status:** DRAFT for Captain Zan sign-off (do NOT build until approved)

Captain Zan's ask: an EXHAUSTIVE admin stats dashboard — "every single stat possible" — but first
**compare what we already have vs what Craig's repo does**, scope it, design it, then follow an agreed task
list. This doc is that comparison + scope. No code is written until the task list is approved.

---

## 1. What WE already have (verified by reading the code)

### 1a. `App\Services\AnalyticsService` (the "business stats" service) — used by Livewire `Admin\Analytics` (/admin/analytics)
Already computes a LOT:
- **Revenue:** summary (total/count/growth vs prev period/avg per txn), by-type (consult/medication/
  subscription/other), 12-month revenue chart, profit summary (revenue, doctor/pharmacy/partner payouts,
  delivery costs, platform profit).
- **Patients & growth:** patient stats (total/this-month/last-month/growth), 12-month signup chart,
  conversion funnel (registered→booked→paid→completed), demographics (by province, by gender).
- **Consultations & doctors:** consult stats (total/completed/avg duration/no-shows/no-show rate),
  doctor performance (per-doctor completed totals + this-month), treatment popularity.
- **Prescriptions & pharmacy:** prescription stats (total/chronic/one-off/chronic ratio/avg value),
  top medications.
- **Partners:** per-partner referrals/conversions/rate/earned.
- **Predictions:** naive linear next-month revenue/patients projection, top-growing treatment,
  marketing suggestion.
- Period filter: today/week/month/year/all.

### 1b. `App\Services\Analytics\AnalyticsService` (the "CRM parity" service) — used by Livewire `Admin\AnalyticsDashboard`
- **Funnel counts** per FunnelEventKind (page_view→…→subscribed), **step-to-step conversion rates**,
  **blended CAC** (ad spend ÷ signups), **kpiSummary** (new patients, signup→payment, signup→consult,
  CAC), attribution capture, ad-spend recording, daily KPI snapshot command (AnalyticsSnapshot).
- Backed by parity tables: analytics_funnel_events, analytics_attribution, analytics_ad_spend,
  analytics_kpi_snapshot. NOW being fed by the live journey (Task 6 JourneyCapture).

### 1c. Other stat surfaces already present
- `Admin\Dashboard` (/admin): headline cards (users, today's appts, revenue MTD, total revenue) + recent lists.
- `Admin\FinanceReports` + `FinanceService`: revenue entries + PayFast↔pharmacy reconciliation.
- `Admin\LeadsFunnel` + `LeadFunnel`: CRM funnel board by stage with counts.
- `Patient360`, alerts (AlertScanner/AlertsBoard), coaching (CoachConsole), compliance screens.

## 2. What CRAIG's repo analytics module does (from KB: analytics module migrations + config)
- Same core primitives we mirrored: funnel_event (13 kinds), attribution (first/last touch), ad_spend
  (channel/campaign CAC), kpi_snapshot (nightly, jsonb metric bag, sliced by dimension/dimension_value).
- **AnalyticsConfig** rules we should honour: defaultLookbackDays=30; **active-subscription rule** = last
  payment within N days (default 35) + grace (default 5) before churn flips. -> We currently have NO
  churn/active-sub metric using this rule.
- Publishes events: funnel.recorded / spend.recorded / kpi.snapshot_taken. Scopes: analytics:read/write/admin.
- **Craig's analytics is a CAPTURE + KPI-summary module**, NOT a giant "every stat" dashboard. The
  "exhaustive" dashboard is OUR ask, not a Craig parity item. Craig parity here = funnel/attribution/CAC/KPI
  snapshot + the active-sub/churn rule. We largely HAVE this (Task 6 wired capture).

## 3. THE GAPS (what "every stat possible" needs that we don't yet have)

### G1 — TWO competing AnalyticsService classes + TWO dashboards (rationalise)
`App\Services\AnalyticsService` vs `App\Services\Analytics\AnalyticsService`; `Admin\Analytics` vs
`Admin\AnalyticsDashboard`. They compute DIFFERENT conversion funnels (one off Users/Appointments, one off
analytics_funnel_events). This is confusing + risks contradictory numbers on different screens. DECISION
NEEDED: one canonical Stats surface, or keep both with clearly separated purposes.

### G2 — Metrics we do NOT surface yet (candidate additions)
- **Subscriptions:** active count, churn rate (using Craig's last-payment-within-N-days rule), MRR,
  new/cancelled/paused this period, cycle success/fail (3-strike), reactivations.
- **Orders (CRM Order aggregate):** counts by each of the 25 statuses, aging in PendingPayment,
  fulfilment funnel (Ordered→PaymentReceived→…→Delivered), avg time-in-status.
- **Finance (from FinanceService/revenue entries):** revenue by service line, reconciliation status
  (matched/unmatched PayFast↔pharmacy), refunds, outstanding/unpaid.
- **CRM funnel (from LeadFunnel/CrmLead):** lead count per stage (14 stages), R/A/G health-score
  distribution, risk-band distribution, flags open by kind, avg time-in-stage, dropped-off/cold count.
- **Alerts/SLA:** open alerts by severity, breached SLA count, mean time to acknowledge/resolve.
- **Coaching:** assignments, touchpoints this period, offers accepted/declined.
- **Compliance/DSAR:** consent coverage %, open DSAR requests + SLA, retention/legal-hold counts.
- **Doctor performance (deeper):** avg consult duration, no-show rate per doctor, scripts issued per doctor,
  utilisation vs availability.
- **Attribution/marketing:** signups by first-touch source/channel, CAC by channel, campaign table.

### G3 — Data-quality caveats that will make stats look wrong until fixed (already on todo)
- Medication seed prices = 0 -> prescription/medication revenue understated (todo #16).
- Payment "paid-without-charge" stub -> paid counts may not reflect real charges (todo #18).
- OpenAI/Daily keys absent -> AI/video-derived metrics limited (todos #14/#17).

## 4. PROPOSED APPROACH (for sign-off — pick one)
- **Option A (recommended): Rationalise + extend into ONE canonical Stats dashboard.** Pick the parity
  `Analytics\AnalyticsService` as the funnel/CAC source of truth, fold the rich business metrics from the
  older service into a clearly-sectioned StatsService, deprecate the duplicate screen. Add the G2 metrics
  section-by-section, each with tests. Biggest clarity win; medium effort.
- **Option B: Leave both, just ADD a new exhaustive "Stats" page** pulling every metric read-only from
  existing services + new queries. Less refactor risk, but leaves the confusing duplication (G1) in place.
- **Option C: Minimal** — only fill the most demo-relevant gaps (subscriptions churn/MRR, orders-by-status,
  CRM funnel counts, finance by service line) and defer the rest.

## 5. OPEN DECISIONS FOR CAPTAIN ZAN
1. Option A / B / C? (I recommend A.)
2. Is "exhaustive" for the ADMIN only, or do doctor/partner dashboards also get expanded? (Assume admin-only.)
3. Priority order of the G2 sections (which matter most for Craig's UAT demo)?
4. Charts (visual) required, or tables/number-cards sufficient for UAT? (Charts = more effort.)
5. Honour Craig's active-sub churn rule (35d + 5d grace) as our churn definition? (Recommend yes.)

## 6. DRAFT TASK LIST (created in the todo tool once an Option is chosen — NOT before)
(Illustrative for Option A; final list depends on decisions above.)
1. Rationalise analytics services/screens (resolve G1) — no behaviour loss, add tests pinning current numbers.
2. StatsService scaffold + canonical period/filter handling + tests.
3. Subscriptions metrics (active/churn/MRR/cycles) + tests.
4. Orders-by-status + fulfilment funnel + aging + tests.
5. Finance by service line + reconciliation + refunds + tests.
6. CRM funnel + health/risk/flags distributions + tests.
7. Alerts/SLA + coaching + compliance/DSAR metrics + tests.
8. Deeper doctor performance + marketing/attribution + tests.
9. Assemble the exhaustive admin Stats page (sections/cards; charts if approved) + Chrome E2E verify.
10. Full-suite regression + UAT walkthrough note.

---
**NOTHING BUILT YET.** Awaiting Captain Zan's answers to §5 before creating the real task list and coding.



---

## 7. DECISION (Captain Zan, 2026-09-15) — APPROVED, proceeding

Approach = **Naz's recommendation (Option A-lean, pragmatic):** rationalise the two analytics services +
add the genuinely-missing metrics. NOT a build-everything-from-scratch pass (most already exists).
- Admin-only (doctor/partner dashboards untouched this task).
- Cards + tables for UAT (NO chart library this pass — visual charts deferred).
- Adopt Craig's active-subscription / churn rule (last payment within 35d + 5d grace).
- Each step ships with tests; Chrome E2E verify at the end; full-suite regression; no secrets in commits.

### The agreed task list (execute in order, to the tee)
- **T7.1** Rationalise duplication (G1): audit both AnalyticsService classes + Analytics/AnalyticsDashboard
  screens. Decide canonical: keep `Analytics\AnalyticsService` (parity/funnel/CAC) + KEEP the rich business
  `AnalyticsService` but under a clearly-named StatsService umbrella; ensure ONE admin Stats entry point.
  Pin CURRENT numbers with characterization tests BEFORE any move (no silent number changes).
- **T7.2** `StatsService` facade: single service the admin Stats page calls; delegates to the existing
  Analytics/Finance/LeadFunnel/Patient360 services; canonical period handling. Tests.
- **T7.3** Subscriptions metrics: active (Craig 35+5 churn rule), churn rate, MRR, new/cancelled/paused,
  cycle success/fail (3-strike), reactivations. Tests.
- **T7.4** Orders-by-status (25 statuses) + fulfilment funnel + PendingPayment aging + avg time-in-status. Tests.
- **T7.5** Finance by service line + reconciliation (matched/unmatched) + refunds + outstanding. Tests.
- **T7.6** CRM funnel: 14-stage lead counts + health R/A/G + risk band + open flags by kind + dropped/cold. Tests.
- **T7.7** Assemble the exhaustive admin **Stats** page (sectioned cards/tables) wired to StatsService; Chrome E2E.
- **T7.8** Full-suite regression + brief UAT walkthrough note. (Alerts/coaching/compliance/deeper-doctor
  metrics = FOLLOW-UP if time/priority; not in this pass unless quick.)

Data-quality caveats (todos #16 med prices=0, #18 paid-without-charge) will make some £ figures look low until
resolved — the stats CODE is still correct; note on the page/UAT doc.
