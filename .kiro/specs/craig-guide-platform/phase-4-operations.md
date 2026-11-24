# Craig's build guide, platform phase 4: Operations portal

**Date:** 2026-11-25 - **Owner:** Naz for Captain Zan - Staging only (zapdev.co.za). Source: Craig's System Build Guide section 6 (text copy `C:\workspace\zapmed-guide\guide.md`). Captain Zan said "proceed" on 2026-11-25.
"Operations" = the `admin` role. Config: `config/operations.php`.

## Rules that hold for every step
- Operations never reads confidential doctor notes, coach notes or blood-test results (phase 2 rules). Reports carry no clinical text.
- Every Operations change to an order leaves an audit row (`order_audit_events`); role and profile changes go into the tamper-evident `crm_audit_events` chain.
- Nothing here sends a real message. The only money effect is the service-fee coupon (4.2), which lowers a repeat charge, and the charge engine itself is still OFF.
- Reports and exports are read-only/safe: CSV cells that start like a formula are neutralised, dates are strictly checked.

## Steps (all built, committed, tested)
- [x] **4.1 Reports** (`/admin/reports`, `OperationsReports`, CSV at `/admin/reports/{key}/export`): upcoming script repeats (with a Monday/Wednesday batch day), customer tracker (counts by status + "needs action"), authorisation report, user medication report (by product, active/expired), add-on client report, pause/cancel analysis (patient vs Operations vs system, with percentages and top reasons). Reconciliation stays on the Finance page. 15 tests.
- [x] **4.2 Order controls** (panel inside the order board drawer, `OrderControls`): a coupon on the monthly SERVICE fee (percent or rand, 1 to 6 months or until switched off, capped at the service fee so the medication is never discounted, one at a time), wired into the repeat charge amount; a manual payment record (transaction ID, amount, time, reference, completed/failed, same transaction only once, finance entry + order moves to Payment received); audit trail. 18 tests.
- [x] **4.3 Repeat cycles** (`/admin/repeat-cycles`, `RepeatCycles`): table of cycles (guide examples seeded: 28/23, 30/25, 21, 24, 45, 60, same-day), the scheduler uses them for the first-repeat day, with the old delivery-buffer rule as the fallback; "Reset repeat" on an order (cancels waiting repeats but keeps them as history, clears failure strikes, first repeat worked out again from today, audited). 13 tests.
- [x] **4.4 User Admin** (`/admin/users/{user}/staff`, `StaffAdmin`): give a signed-up user the Operations, Doctor or Health Coach role (not yourself, never back to patient), staff profile (registration number, board, practice address, registration status, country, booking and authorisation check-in links), doctor signature upload (private, image only). "Verified" switches the doctor's `is_verified` on so patients can book them. 16 tests.
- [x] **4.5 Product list** (`/admin/product-list`, `ProductCatalogIO`): export the catalogue as CSV, edit it, check the file (nothing is written), then load it: all or nothing, matched by NAPPI or name + strength + form, blank cell = leave alone, rand in the file and cents in the database. 15 tests.

## NOT built (needs a decision, an outside service or a later piece of work)
- **Category configuration extras** (exclusive categories, one-off vs recurring service fee, store collection, hex colour, image upload) and **B2B partner management** (Emeris, IV Bar: discounts, codes, stores, auto-enrolment, HR sick-note routing, service-fee waiver). The existing Category builder and Partners (affiliate commissions) screens were left as they are: the guide's partner model is different from the affiliate one and needs a design decision first.
- **Integration log with safe resend** (RX Hub / Scriptwise, about ten automatic retries, never a duplicate charge) and the **patient integration history**.
- **DPS Laser courier tracking** (needs API access). The My Orders card already shows a tracking link when `metadata.tracking_url` exists.
- **Configurable notification templates and triggers** (WhatsApp first, email fallback, delivery/open/click status). The messenger and the fallback exist (phase 3.4); the template editor does not.
- **Add-ons add / pause / cancel from the order** and categories where each add-on is offered.
- **Abandoned-checkout follow-up with a coupon.** The reward coupons exist (`PatientRewards::assign`) but nothing creates or sends one.
- **Dashboard extras** (daily order chart by category, revenue segments, cancelled-order reasons, coaching oversight metrics). The reports cover part of it.
- **Multiple doctors / coaches per patient with start and end dates** (guide 6.2 "Review"). The coach assignment table already records start and end for coaches only.
- **Booking-link expiry settings** (about 3 uses and 2 weeks).
- **Electronic prescription PDF using the uploaded signature**: the signature is stored and shown to Operations and the doctor, but nothing prints it on a prescription yet.
- Browser check on zapdev as an admin (see the session notes).

## Decisions / assumptions (provisional, to confirm with Craig)
1. **Batch day** = the next Monday or Wednesday on or after the repeat date (`operations.reports.batch_weekdays`). Craig's wording ("batch to nearest Monday/Wednesday") is loose.
2. **Customer tracker "needs action"** statuses are a list in `config/operations.php` (failed payments, missing information, no-show, paused, follow-up/blood-test waits, claim rejected, not delivered, refund needed).
3. **Service-fee coupon** is capped at `orders.service_fee_minor`, so an order with no recorded service fee cannot be discounted. It is applied to the first charge of each repeat and every retry keeps that amount.
4. **Repeat cycles 21, 24, 45 and 60** use the same 5-day buffer as the 28-day one (first repeat on day 16, 19, 40, 55). The guide only gives 28/23 and 30/25. Same-day = first repeat on the last day of the pack.
5. **Reset repeat** also clears failed-payment strikes and sets the order back to active; it only works on a running (active or payment-failed) repeat order.
6. **Roles Operations can give**: Operations, Doctor, Health Coach. Pharmacy staff are managed with the pharmacy integration.
7. **Manual payments** are stored as provider `payfast`, type `manual`, with the Operations reference in the description. A "completed" one is real cash in the finance ledger.
