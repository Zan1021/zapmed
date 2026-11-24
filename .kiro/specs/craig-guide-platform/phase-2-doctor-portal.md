# Craig's build guide, platform phase 2: doctor portal gaps

**Date:** 2026-11-24 - **Owner:** Naz for Captain Zan - Staging only (zapdev.co.za). Source: Craig's System Build Guide sections 4, 4.1 to 4.3 and 8.1 (text copy `C:\workspace\zapmed-guide\guide.md`).
Captain Zan chose "option 1" (platform phases 2 to 4) on 2026-11-24. This file is the tickable list for phase 2.

## Rules that hold for every step
- Nothing here moves money or sends a real message by default. New sending switches are OFF (`DOCTOR_REQUEST_NOTIFY`).
- No health detail and no medicine names in any message (guide 8.3).
- Access is by assignment: a doctor sees requests they requested, are assigned to or take part in; admin (Operations) sees all request detail but NO clinical notes.
- Every state change is written to an event log (who, what, when).

## Steps
- [ ] **2.1 Doctor Requests** (guide 4.1, 8.1): shared work items between doctors, Operations and coaches. Requester, assignees, participants, several patient/order references, description, attachments, status Pending > In Review (assignee opens it) > In Progress > Submitted (response sent) > Completed (requester or Operations closes). Event log. Notification OFF by default.
- [ ] **2.2 Time tracking** (guide 4.2, 8.1): minutes per patient record and per request, start/stop and manual entry, doctor edits with a reason (old value kept in an audit trail), monthly invoice summary per doctor.
- [ ] **2.3 Follow-up requests** (guide 4.2): doctor asks for a follow-up in 7, 14, 20, 30, 60 or 90 days. Moves the order to Pending follow-up request, blocks the next repeat until the doctor reviews and submits, tells patient and Operations (OFF), prompt to book a few days before it is due.
- [ ] **2.4 Blood tests** (guide 4.1, 4.2): doctor requests selectable tests + free text + lab form, order moves to Pending blood tests, results uploaded by doctor or patient, history list.
- [ ] **2.5 Authorisations** (guide 4.1): list of patients nearing six-month renewal (review around month five), doctor renews for six months or requests a follow-up (which pauses and blocks repeats).
- [ ] **2.6 Note separation** (guide 4.3, 8.1): four note types (doctor-confidential, coach-confidential, operations, patient-visible summary), each with audience, author, time, order and action status. Operations can never read doctor-confidential.
- [ ] **2.7 Doctor dashboard sections** (guide 4.1): New / Past / Follow-up appointments filters, Patient History board (secure export needs terms, fresh 2FA, password-protected file: NOT built until Captain Zan confirms how 2FA re-check should work).

## Decisions / assumptions (provisional, to confirm with Craig)
(filled in as each step is built)
