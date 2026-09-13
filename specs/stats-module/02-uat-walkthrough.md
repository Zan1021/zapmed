# Stats Module — UAT Walkthrough (Task 7)

**Author:** Naz · **Date:** 2026-09-15 · Verified in Chrome + full test suite.

## What was built
A new canonical admin **Stats** page (`/admin/stats`, sidebar "Stats") that aggregates every metric group
into one sectioned read surface, backed by `App\Services\Stats\StatsService`. The two pre-existing Analytics
pages are intentionally kept (they answer different questions) and cross-linked from Stats:
- `/admin/analytics` — business/marketing stats (revenue, patients, doctors, prescriptions, partners, GA).
- `/admin/crm-analytics` — CRM acquisition funnel (event funnel, conversion, CAC).

## The Stats page sections (all cards/tables, no charts this pass)
1. **Business overview** — revenue (period), platform profit, patients (+this month), consultations + no-show%.
2. **Acquisition** — funnel counts by event kind, blended CAC, step-to-step conversion rates.
3. **Subscriptions** — active, MRR, churn rate, churned, cycles fulfilled/failed. Uses **Craig's active-sub
   rule**: active = last payment within 35 days; churned once past 35 + 5-day grace (or cancelled).
4. **Orders** — counts rolled into 7 board lanes, fulfilment funnel (payment→pharmacy→despatched→delivered),
   PendingPayment aging (stuck > 24h). Covers all 25 order statuses under the hood.
5. **Finance** — revenue by service line, net, refunds, reconciliation status counts, outstanding (aged pending).
6. **CRM funnel** — leads across all 14 stages, risk-band distribution, open flags by kind, dropped-off + cold.

Period switcher: today / week / month / year / all (Livewire, live re-render).

## How to UAT (2 min)
1. Log in as admin (admin@zapmed.co.za). Sidebar → **Stats**.
2. Confirm all six section headings render with cards/tables; no console errors.
3. Toggle the period buttons — figures re-compute (e.g. "Revenue (month)" → "Revenue (all)").
4. Follow the "Business analytics →" and "CRM acquisition →" cross-links.

## Expected-low-numbers caveat (NOT a bug)
On the current clean/dev DB many figures read 0 or tiny. Two known data-quality items make money figures look
low even with data (tracked as todos #16 and #18):
- **#16 Medication seed prices = 0** → prescription/medication revenue understated.
- **#18 Payment "paid-without-charge" pre-launch stub** → paid counts may not reflect real charges.
The Stats CODE is correct; it faithfully reports whatever is in the ledger/tables. A note to this effect is
printed at the foot of the page.

## Tests (all green; full suite 396 pass / 1 pre-existing unrelated fail)
- BusinessAnalyticsCharacterizationTest (7) — pins the previously-untested business analytics numbers.
- StatsServiceTest (3), StatsSubscriptionsTest (5), StatsOrdersTest (4), StatsFinanceTest (3),
  StatsCrmFunnelTest (4), StatsPageTest (3). = 29 new tests this task.

## Deliberately deferred (follow-up, not in this pass)
Alerts/SLA metrics, coaching metrics, compliance/DSAR metrics, deeper per-doctor performance, and visual
charts. All are additive to StatsService + the page when prioritised.
