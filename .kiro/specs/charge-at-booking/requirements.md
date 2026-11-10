# Charge-at-Booking Onboarding — Requirements

**Project:** ZapMed telehealth · `C:\Users\zande\Documents\Zapmed\zapmed` (Laravel 12 + Livewire 3 + Tailwind).
**Design source (1:1 look-and-feel):** Craig's End-to-End Design — Folder 1
`C:\Users\zande\Documents\Zapmed - Craig_New_Files\Zapmed_End_to_End_Design_v15\zapmed\dist`
(index.html + landing.css + checkout.css + journeys.js/journey-v8.js/checkout.js) + `research.md`
(the authoritative payment/UX research).
**Related vault notes:** 2026-11-05_mem_booking-flow-ours-vs-craig, _mem_craig-onboarding-design-exists,
_mem_booking-flow… ; question-bank spec (`.kiro/specs/question-bank`).

---
## 1. GOAL
Replace our stubbed booking (which never charges) with Craig's designed onboarding journey that takes
the consultation fee **at booking confirmation**. Reuse what we already have: the question-bank engine
(AssessmentService) for the clinical questions, and PayFastService (already wired for appointments).

## 2. THE JOURNEY (Craig's order — fee disclosed up front, charged last)
**Assessment → Review → Your details → Book → Pay → Confirmation.**
A persistent progress header shows the 5 stages + a "Question N of M" bar during Assessment.

## 3. USER STORIES / ACCEPTANCE CRITERIA

### US-1 Entry
- From a treatment/category CTA ("Start my assessment"/"Check if you qualify"/"Get Started"), the patient
  enters the journey for that treatment slug. Fee is disclosed before the questionnaire
  ("Consultation: R[fee]. Pay when you confirm your booking…").
- No account is required to START (Craig). Sample/guest state held server-side per draft.

### US-2 Assessment (one question at a time)
- Questions come from the question bank (AssessmentService.flowFor(slug)); CORE block first, then
  category questions, then free-text — exactly as the engine already returns.
- One question per screen, large heading, correct input per type (number/single/multi/text/measurements),
  a "Why we ask" helper line, Back/Continue, progress bar + percent. Conditional branching honoured.
- Answers persist to a DRAFT so Back never loses data; the assessment is pinned to the manifest version.

### US-3 Review (editable)
- Before continuing, show all answers grouped, each editable (jump back to that question). NEW vs today.

### US-4 Your details + verification
- Collect patient details + contact (cell/email). Verify the contact channel with an OTP
  (reuse existing BulkSMS OTP / 2FA plumbing). On success, create or attach the patient account and
  bind the draft assessment to it. Keep privacy/telehealth consent SEPARATE from marketing (research.md).

### US-5 Book (choose doctor + slot)  [DEFAULT — confirm with Captain Zan]
- Patient picks a doctor and an available slot (Craig's design shows doctor cards + day/time slots).
  Our existing availability (DoctorProfile slots, 14-day window) feeds the list.
- FALLBACK: if the patient doesn't choose, use our existing auto-assign (continuity → load-balance).
- Consultation mode (video/telephone) selectable, as today.

### US-6 Pay (the core change) — charge at confirmation
- Show a booking summary + line item "Consultation fee R[fee]". Button: "Confirm booking & pay R[fee]".
- Bind fee + currency SERVER-SIDE (never from client). Create ONE Payment (pending) + reserve the slot
  with an honest expiry.
- Redirect to PayFast (reuse PayFastService::generatePaymentData(Appointment,Payment) +
  /payment/notify ITN). Appointment is created but **is_paid=false** until payment confirmed.
- Confirm the booking ONLY when payment success AND slot commitment are both verified
  (ITN VALID + reconciled). Then is_paid=true, status progresses, success screen shows receipt/reference.

### US-7 Payment exceptions (from research.md — must handle)
- Card declined → explain, let them retry another method; preserve answers + slot if its hold is valid.
- Payment pending/unknown → "We're checking your payment. Please don't pay again." recoverable status page.
  Do NOT turn an uncertain/cancelled checkout straight into "pay again" (PayFast ITN order not guaranteed).
- Slot expired before payment → return to times; release reservation; no capture for an unavailable slot.
- Payment succeeded but booking failed → show truthful status + support/refund reference; compensating refund.
- Double-tap/refresh → idempotent; reopen same result. One booking ↔ one payment association.

### US-8 Copy / compliance
- Never say "pay later", "card only to secure booking", or "charged after consultation".
- The questionnaire must NOT promise eligibility/diagnosis/prescription (HPCSA). It routes a consult.
- No health answers in URLs, analytics, or gateway metadata (POPIA). Marketing opt-in unchecked by default.

### US-9 Look & feel
- Match Craig's design (teal/ivory, Manrope + Prata emphasis, white card on ivory, teal pill buttons,
  stage header, progress bar, "Why we ask"). Keep our mega menu as the site chrome. Drop the
  "CONCEPT ONLY" banner and any fictional doctors/claims.

## 4. OPEN DECISIONS (defaults chosen; Captain Zan to confirm)
- D1 Doctor: **patient picks** (default, matches Craig) vs keep auto-assign. Fallback = auto-assign.
- D2 Account: **OTP-verify + account created at "Your details"** (default, matches Craig) vs require login first.
- D3 Payment: **PayFast redirect** (default, already integrated) — fine, or prefer onsite/iframe later.
- D4 Fee source: use the doctor/treatment consultation fee already in config/profiles (cents). Confirm R450.

## 5. OUT OF SCOPE
- Medication/subscription billing (separate; keep distinct unless the offer includes it).
- Rewriting the question content (that's the question-bank/Dr-Anke CORE work; we consume the engine).
- The marketing homepage (done separately).
