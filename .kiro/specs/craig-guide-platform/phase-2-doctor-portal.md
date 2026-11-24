# Craig's build guide, platform phase 2: doctor portal gaps

**Date:** 2026-11-24/25 - **Owner:** Naz for Captain Zan - Staging only (zapdev.co.za). Source: Craig's System Build Guide sections 4, 4.1 to 4.3 and 8.1 (text copy `C:\workspace\zapmed-guide\guide.md`).
Captain Zan chose "option 1" (platform phases 2 to 4) on 2026-11-24. This file is the tickable list for phase 2. Config for all of it: `config/doctor.php`.

## Rules that hold for every step
- Nothing here moves money or sends a real message by default. Sending switches are OFF: `DOCTOR_REQUEST_NOTIFY`, `DOCTOR_FOLLOWUP_NOTIFY` (+ `DOCTOR_FOLLOWUP_OPS_EMAIL`), `DOCTOR_BLOODTEST_NOTIFY`, `DOCTOR_NOTE_NOTIFY`.
- No health detail, note text, test names or medicine names in any message (guide 8.3).
- Access is by assignment: a doctor sees what they are on or treat. "Treats" = has an appointment with the patient (`CoachAccess::canView`). Operations (admin) never reads clinical notes, blood results or coach notes.
- Files (request attachments, blood results, lab forms) sit on a private disk under random names and are only served through access-checked routes.
- Every state change leaves a row (events, history, adjustments). Notes are append-only.

## Steps (all built, committed, tested)
- [x] **2.1 Doctor Requests** (`DoctorRequestService`, `/doctor/requests`). Requester, assignee doctors, participants, several patient/order refs, encrypted description + response, attachments, event log. Status Pending > In review (assignee opens it) > In progress > Submitted > Completed (requester or Operations). Admin, doctor and coach can ask; only doctors are assigned. 17 tests.
- [x] **2.2 Time tracking** (`TimeTracker`, `/doctor/time`, timer button on the consultation + request pages). One timer at a time, forgotten timer cut at 240 min and flagged, manual entries (not future, 62 days back), edits need a reason and keep the old value, monthly summary + CSV (`/doctor/time/export`, formula-safe). Operations can download a doctor's CSV for the invoice, cannot edit. 15 tests.
- [x] **2.3 Follow-up requests** (`FollowUpService`, `/doctor/follow-ups`, panel on the consultation screen, `followups:prompt` daily 09:00 SA). 7/14/20/30/60/90 days. While open the order's next repeat is held (`SubscriptionLifecycle::repeatBlockedBy` now also checks this). Delivered/Completed orders also move to Pending follow-up request; orders still being prepared are left alone. First follow-up on an order free, later ones paid (recorded only). Cancel needs a reason and puts the order back. 18 tests.
- [x] **2.4 Blood tests** (`BloodTestService`, `/doctor/blood-tests`, panel on the consultation screen). Ticked tests + free text + lab form (chosen by name or uploaded). An In-review order moves to Pending blood tests, first result moves it to Pending review/feedback, review sends it back to In review. Results from the patient or a treating doctor, readable only by them. 16 tests.
- [x] **2.5 Authorisations** (`AuthorisationService`, `/doctor/authorisations`, panel on the consultation screen, `authorisations:expire` daily 08:00 SA). Six months, review window opens 31 days before the end, renew carries on from the old end date, or ask for a follow-up first. An order that has authorisation records but no valid one has its next repeat held (`DOCTOR_AUTH_BLOCK_EXPIRED`, default on). 16 tests.
- [x] **2.6 Note separation** (`PatientNoteService`, notes panel on the consultation screen, `/staff-notes`). Clinical (treating doctors only), patient summary (patient + treating doctors), staff note (author + people/roles it is addressed to, optional action open/done). Coach notes stay in `coach_notes`. 18 tests.
- [x] **2.7 Doctor lists** (`DoctorWorkboard`, `/doctor/appointments`, `/doctor/patient-history`). New and past appointments, date + service filters, day/week/month views, newest-booked-first, past shows signed prescriptions and next repeat; patient history board with readable-note counts. 9 tests.

## NOT built (needs a decision or a later phase)
- **Secure patient-history export** (guide 4.1: terms confirmation, fresh 2FA, password-protected file). Needs a decision on how the second 2FA check works for an already signed-in doctor, and what "password-protected" means (encrypted zip or PDF).
- **Patient side** of follow-ups, blood tests and summaries (booking a follow-up, uploading results, reading the doctor's summary): phase 3 (patient tabs). The services are ready: `FollowUpService::markBooked`, `BloodTestService::uploadResult`, `PatientNoteService::summariesFor`.
- **Nothing calls `AuthorisationService::grant` from a signed prescription yet** (same open "Rx signed, order never advances" item as phase 1). Doctors authorise from the consultation screen for now.
- Doctor Requests do not yet raise a follow-up or blood test themselves; Operations and coaches asking for a blood-test status is only possible through a doctor today.
- Multiple-doctor/coach assignment with start/end audit (guide 8.1) beyond what the coach assignment table already does.
- Browser check on zapdev (Playwright) not done yet: everything is covered by feature tests only.

## Decisions / assumptions (provisional, to confirm with Craig)
1. Blood tests and review/feedback do NOT hold repeats (follow-ups and expired authorisations do).
2. An authorisation is valid on every day before its end date; review window = 31 days before the end; a renewal outside the window is refused.
3. An ended authorisation holds the next repeat. Guide says "renew for six months"; the hold is our reading. Orders with no authorisation record are never held, so nothing existing changes. One switch turns it off.
4. The first follow-up per order is free; a cancelled one does not use up the free one.
5. Staff notes: Operations only reads notes addressed to Operations (or written by them). A role address to doctors/coaches only reaches ones with access to that patient; a named person always reads it.
6. Blood-test menu and lab form names in `config/doctor.php` are PROVISIONAL (Dr Anke / Craig to confirm). No lab form templates exist, only the choice is recorded.
7. Time: forgotten timers are cut at 240 minutes; single entries up to 720 minutes.
