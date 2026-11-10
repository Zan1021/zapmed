# Charge-at-Booking Onboarding — Design

Reads with requirements.md (same folder). Grounded in the ACTUAL codebase (verified 2026-11-05).

## 1. WHAT ALREADY EXISTS (reuse, don't rebuild)
- **AssessmentService** (`app/Services/Assessments`) — `flowFor(slug)` returns CORE→category→free-text
  questions with type/options/helper(why_we_ask)/branch_rules/required; `categoryIdFor`,
  `manifestVersionFor`, `saveAnswer` (per-answer rows, versioned + timestamped + flagged). This is the
  assessment engine — the new UI just renders what it returns.
- **PayFastService** (`app/Services/PayFastService.php`) — `generatePaymentData(Appointment,Payment)`,
  `generateSignature`, `validateItn` (signature + source-IP + server verify), `getProcessUrl`.
- **PaymentController** + routes: `payment/checkout/{reference}`, `payment/success`, `payment/cancel`,
  POST `payment/notify` (ITN webhook, no-auth). `Payment` model (reference/appointment_id/amount/status).
- **Appointment** model with `is_paid`, fee fields, doctor assignment; `DoctorProfile` availability
  (`getAvailableSlotsForDate`, `getAvailableDoctorsForSlot`, 14-day window).
- **BookAppointment** (current 7-step Livewire) — reference for logic to port; its payment step is a stub
  (`proceedToDateTime()` just sets isPaid=true). CRM bridges (LiveOrderBridge, JourneyCapture) fire on book.
- **OTP**: existing 2FA OTP via BulkSMS (reuse for contact verification).

## 2. NEW COMPONENT: `App\Livewire\Patient\Onboarding`
A single Livewire component driving the 5 stages via a `stage` enum + per-stage sub-state. Route
`GET /start/{slug}` (name `onboarding.start`) — public (no auth to start). Uses a new minimal layout
`layouts/onboarding.blade.php` (our mega menu optional/hidden chrome per Craig's full-screen card) that
loads the vendored journey styles (see §5). Mirrors the proven logic in BookAppointment but reorders to
Assessment→Review→Details→Book→Pay and swaps the stub for real payment.

### State machine
`stage ∈ {assessment, review, details, book, pay, confirm}`
- assessment: `questions[]` (from flowFor), `answers[]`, `cursor` (current question index among VISIBLE
  questions), branching via existing `isQuestionVisible` logic. Autosave answers to a Draft.
- review: render grouped answers; "Edit" sets cursor + stage=assessment.
- details: name/cell/email + consent checkboxes; `sendOtp()` / `verifyOtp()`; on verify →
  create-or-attach User (patient role), create/attach the Assessment (pin category_id+manifest_version),
  write per-answer rows (saveAnswer), attach draft to the user.
- book: list doctors + slots (availability service); `selectDoctor/selectSlot`; mode (video/telephone);
  fallback auto-assign if none chosen.
- pay: build summary; `confirmAndPay()` → see §3.
- confirm: receipt + reference (reached after ITN confirms, or on return_url with pending state).

## 3. PAYMENT FLOW (the core)
1. `confirmAndPay()` (server): re-validate the chosen slot is still free; bind fee+currency server-side
   from the doctor/treatment consultation fee (cents). Create the Appointment (is_paid=false,
   status=pending_payment) + a SLOT RESERVATION with honest expiry. Create one Payment (status=pending,
   reference). Link assessment→appointment.
2. Redirect to PayFast using `PayFastService::generatePaymentData($appointment,$payment)` +
   `getProcessUrl()`. (Already returns correct signed data incl. return/cancel/notify urls.)
3. ITN webhook `payment/notify` → `PaymentController::notify` validates via `validateItn`, reconciles,
   marks Payment=complete + Appointment.is_paid=true + status=confirmed; fire LiveOrderBridge /
   JourneyCapture (as BookAppointment does today). IDEMPOTENT on reference (ignore duplicate ITNs).
4. `return_url` (/payment/success?reference=): show confirmation if Payment=complete; else "we're
   checking your payment…" recoverable status (do NOT re-charge). `cancel_url`: release reservation,
   return to pay/book with answers intact.
5. Exceptions per research.md (US-7) encoded as explicit Payment/Appointment states:
   pending_payment, payment_failed, confirmed, expired. A scheduled sweep releases expired reservations.

### Minimal data changes
- Appointment.status add values: `pending_payment`, `expired` (if not present) — check enum/migration.
- A lightweight slot reservation: either a `reserved_until` on Appointment or a small
  `appointment_slot_holds` table (decide in task 2). Prefer `reserved_until` + status to avoid a table.
- Reuse Payment as-is.

## 4. DOCTOR SELECTION (D1 default = patient picks)
- book stage lists `DoctorProfile::getAvailableDoctorsForSlot` per chosen day/time, or a doctor-first
  then slot UI (match Craig's cards). Store `assignedDoctorId`. If skipped → `findBestDoctor()` (ported
  from BookAppointment: continuity → load-balance).

## 5. STYLING (match Craig 1:1)
- Vendor Folder-1 `landing.css` + `checkout.css` (+ the journey bits of style.css) the SAME way we did
  the homepage: concatenate, scope under `.zapmed-onboarding`, rewrite `assets/`→`/mockup-assets/`
  (reuse already-copied assets where shared; copy any journey-only assets). Add as a Vite input
  `resources/css/zapmed-onboarding.css`. Brand hex already matches our tokens.
- Blade renders the journey card markup (stage header, progress bar, question card, "Why we ask",
  review list, details form, doctor/slot picker, pay summary) bound to Livewire state. Prata emphasis,
  teal pills, ivory bg. Drop CONCEPT banner + fictional doctors/claims.

## 6. TESTING
- Feature tests (RefreshDatabase): journey advances assessment→…→pay; branching skips hidden Qs; draft
  autosave + resume; OTP gate creates/attaches user; fee bound server-side (ignore tampered client fee);
  Payment created pending → ITN VALID flips is_paid + status=confirmed (use testMode/stub ITN);
  duplicate ITN idempotent; declined/cancel leaves answers + releases slot; expired reservation swept.
- Reuse PayFast sandbox (testMode true) — no real charges. Keep health answers out of PayFast fields.

## 7. ROLLOUT
- Feature-flag the new journey per slug (like questionbank.enabled_slugs). Start with weight-loss
  (the only treatment with a live manifest + Craig's designed flow). Old BookAppointment stays until
  the new journey is proven, then redirect `/book` → `/start/{slug}`.
