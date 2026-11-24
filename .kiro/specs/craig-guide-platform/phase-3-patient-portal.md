# Craig's build guide, platform phase 3: patient portal tabs

**Date:** 2026-11-25 - **Owner:** Naz for Captain Zan - Staging only (zapdev.co.za). Source: Craig's System Build Guide section 3 (text copy `C:\workspace\zapmed-guide\guide.md`). Captain Zan said "phase 3" on 2026-11-25.
Tabs live under `/my/*` (role patient, onboarding done), with a shared tab bar. Config for patient settings: `config/patient.php`.

## Rules that hold for every step
- A patient only ever sees and changes their OWN data. Every action loads rows by the signed-in patient's id.
- Plain words on screen: no status codes, no medicine names on reminders or messages, never any dose advice.
- Nothing here charges a card. Paused and cancelled orders never charge or repeat (phase 1 rules do that).
- Sending switches stay OFF.

## Steps
- [ ] **3.1 My Orders** (guide 3.1, 3.2): orders with plain status, repeats left ("4 of 6"), next payment and renewal date, tracking link when there is one, resume an unfinished checkout, pause / cancel (reason required, recorded as the patient), continue a paused order within 90 days, archive cancelled / ended / unfinished orders, "book a check-in" when the authorisation is due.
- [ ] **3.2 Health Stats** (guide 3.1): weight chart and side-effect log (weight-loss orders only), upload medical records, results the doctor uploaded, the latest doctor summary written for the patient.
- [ ] **3.3 Follow-Up** (guide 3.1): book a chosen doctor, 15 or 30 minutes, pay when it is a paid follow-up, free authorisation check-ins a doctor asked for.
- [ ] **3.4 Notifications** (guide 3.3): choose WhatsApp or email, record consent, fallback to the other channel, list of requests and notes the doctor made visible.
- [ ] **3.5 Rewards and Community** (guide 3.1): coupons and discounts the patient can use; Community tab as a placeholder (requirements still undefined).

## Decisions / assumptions
(filled in as each step is built)
