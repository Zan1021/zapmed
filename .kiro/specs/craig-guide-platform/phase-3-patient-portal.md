# Craig's build guide, platform phase 3: patient portal tabs

**Date:** 2026-11-25 - **Owner:** Naz for Captain Zan - Staging only (zapdev.co.za). Source: Craig's System Build Guide section 3 (text copy `C:\workspace\zapmed-guide\guide.md`). Captain Zan said "phase 3" on 2026-11-25.
Tabs live under `/my/*` (role patient, onboarding done), with a shared tab bar (`<x-patient-tabs>`) and one sidebar link "My Orders and Health". Config: `config/patient.php`.

## Rules that hold for every step
- A patient only ever sees and changes their OWN data. Every action loads rows by the signed-in patient's id (someone else's id is a 404).
- Plain words on screen: no status codes, no medicine names on reminders or messages, never any dose advice.
- Nothing here charges a card by itself. Paused and cancelled orders never charge or repeat (phase 1 rules do that).
- Sending switches stay OFF. Medical files sit on a private disk under random names and are served through access-checked routes.

## Steps (all built, committed, tested)
- [x] **3.1 My Orders** (`/my/orders`, `PatientOrders`). Plain status words (every status in `config/orders.php` has one), repeats left ("4 of 6", worked out as floor(authorisation days / pack days) minus repeats used), next payment date + amount, "on hold" explanation, authorisation end date with a "Book a check-in" link when due, open follow-up, tracking link when `metadata.tracking_url` exists, finish an unpaid checkout (card page or restart a repeat order's checkout), pause / cancel (reason required, recorded as the patient), continue within 90 days, archive (new `orders.archived_at`; only hides, never changes a status). Order status follows the repeat order when the state machine allows it. 19 tests.
- [x] **3.2 Health Stats** (`/my/health`, `HealthRecords`, doctor panel "Records and results"). Weight chart + log (reuses the coach app's `WeightLogger`, so coach-entered weight shows too), height, side-effect log for weight-loss patients only (reuses `SideEffectLogger`, serious scores raise the same safety flag), tests the doctor asked for with upload of results, upload of medical records, results a treating doctor uploaded, latest summary the doctor wrote for the patient. 17 tests.
- [x] **3.3 Follow-Up** (`/my/follow-up`, `PatientFollowUps`). Book a doctor who has already seen the patient, 15 or 30 minutes, a follow-up or an authorisation check-in. A follow-up a doctor asked for and marked free is confirmed straight away; anything else is held for the reservation time and paid on the card page. A doctor's request counts as booked only once the payment completes (`PaymentController` calls `onPaymentCompleted`). A paid follow-up no longer creates a stray order (`LiveOrderBridge`). 19 tests.
- [x] **3.4 Notifications** (`/my/notifications`, `PatientChannels`, `PatientMessenger`, `PatientInbox`). WhatsApp or email, WhatsApp consent recorded as a `ConsentRecord` (`whatsapp_service`, with the wording, never edited), the chosen channel first and the other as fallback, every attempt logged with attempt number and fallback flag. WhatsApp that is not set up is skipped so nothing is lost. The phase 2 patient messages (follow-up, blood tests) now go through the messenger. "From your care team" lists follow-up and blood-test requests, a due renewal and new summaries, never confidential or staff notes. 16 tests.
- [x] **3.5 Rewards and Community** (`/my/rewards`, `/my/community`). Rewards shows coupons made for the patient (`catalog_coupons.metadata.patient_id`, stored as text) or marked public (`metadata.audience = all`); cancelled, expired and used-up ones are hidden; imported Contro coupons are never shown. Community is a placeholder. 10 tests.

## NOT built (needs a decision or a later phase)
- **Apple Pay and instant EFT** (guide 3.3): Craig must confirm PayFast supports them for repeats. Only cards today.
- **Dosage reminders for weight-loss orders** on this tab: they already exist inside the coach app (add-on). Patients without the add-on get side-effect logging but no reminders.
- **Courier tracking links**: the card shows `metadata.tracking_url` when present; the DPS Laser feed is phase 4.
- **Update personal details / delivery address / payment details** screens (guide Appendix A, existing screens) were left as they are.
- **Operations assigning a coupon to a patient** (`PatientRewards::assign` is ready): phase 4 per-order coupon work.
- **Community**: requirements still undefined by Craig.
- **Pause / cancel of non-repeat orders**: only repeat (subscription) orders can be paused or cancelled by the patient.
- Browser check on zapdev (2026-11-25, Playwright, patient test account): all six tabs load, tab bar shows, no console errors. Button-by-button clicking was not done; the actions are covered by feature tests.

## Decisions / assumptions (provisional, to confirm with Craig)
1. "Repeats left" is derived (authorisation length / pack length); the guide gives the example "4 of 6" but no formula.
2. A follow-up costs the doctor's `followup_fee` for 30 minutes and half of it for 15 minutes (`patient.follow_up.fee_factor`). PROVISIONAL.
3. A patient can only book a doctor who has already seen them (guide says "a selected doctor").
4. A free check-in only exists when a doctor requested it (first follow-up per order is free, phase 2.3). A patient who starts a check-in on their own pays.
5. Email is always allowed for service messages; WhatsApp needs consent and a phone number. Consent wording is `patient.notifications.whatsapp_wording` (version 1), Craig / legal to approve.
6. Archivable orders: cancelled, refunded, unpaid/failed checkout, cancelled repeat order, or an order whose authorisation ended with nothing still repeating.
7. Rewards show only coupons assigned to the patient or marked public; a plain coupon code is never listed.
