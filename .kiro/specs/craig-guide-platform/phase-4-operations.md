# Craig's build guide, platform phase 4: Operations portal

**Date:** 2026-11-25 - **Owner:** Naz for Captain Zan - Staging only (zapdev.co.za). Source: Craig's System Build Guide section 6 (text copy `C:\workspace\zapmed-guide\guide.md`). Captain Zan said "proceed" on 2026-11-25.
"Operations" = the `admin` role. Config: `config/operations.php`.

## Rules that hold for every step
- Operations never reads confidential doctor notes, coach notes or blood-test results (phase 2 rules). Reports carry no clinical text.
- Every Operations change to an order leaves an audit row (who, what, old, new, reason).
- Nothing here charges a card or sends a real message. Switches stay OFF.
- Reports are read-only and export to CSV (formula-safe).

## Steps
- [ ] **4.1 Reports** (guide 6.3): upcoming script repeats (batched to Monday/Wednesday), customer tracker, authorisation report, user medication report, add-on client report, pause/cancel analysis. Reconciliation already exists in Finance.
- [ ] **4.2 Order controls** (guide 6.2): per-order coupon on the monthly service fee (1 to 6 months or until switched off, never the medication), manual payment record, audit trail.
- [ ] **4.3 Repeat cycles** (guide 6.4): configurable repeat cycles (days, first-repeat days, same-day) and "reset repeat dates" with audit history.
- [ ] **4.4 User Admin** (guide 6.4): assign Doctor / Health Coach / Operations and keep the staff profile (registration number, board, address, registration status, country, booking links, signature upload).
- [ ] **4.5 Product list** (guide 6.4): CSV import and export of the medication catalogue with repeat cycle and add-on flags.

## Decisions / assumptions
(filled in as each step is built)
