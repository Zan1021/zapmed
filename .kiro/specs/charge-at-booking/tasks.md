# Charge-at-Booking Onboarding — Tasks

Reads with requirements.md + design.md (same folder). ZapMed · Laravel 12 + Livewire 3 + Tailwind.
Design source: Craig's Folder 1 journey + research.md. Reuses AssessmentService + PayFastService (both
already built — verified 2026-11-05).

---
## ▶️ START HERE (status 2026-11-06 — BUILT + LOCALLY BROWSER-TESTED END-TO-END; NOT committed)
The journey is BUILT and was driven through a real browser (Chrome via Playwright) on :8002 all the way
to the PayFast SANDBOX. D1–D4 confirmed by Captain Zan (patient picks doctor w/ auto-assign fallback;
OTP+account at details; PayFast redirect; fee bound server-side, R450). Weight-loss enabled behind the
flag. **3 real bugs were found during the test and FIXED (all would hit every patient):**
  1. 419 "page expired" mid-journey — SessionSecurity 15-min inactivity logout regenerated the CSRF
     token. FIX: /start/{slug} (+ its livewire/update) exempt from the idle logout.
  2. New patient bounced to /verify-email instead of PayFast — payment route is ['auth','verified'] but
     the journey verifies by SMS OTP. FIX (Option A): set email_verified_at at account creation.
  3. 500 on patient create — User member_number generator ignored soft-deleted rows (unique index
     doesn't). FIX: withTrashed() when finding the last number.
Also cleaned 18 question-bank label artifacts ("TEXT"/"IF SELECTED:") via `questionbank:clean-labels`
+ fixed the importer clean() at source. Full suite 496 pass (1 pre-existing /dashboard auth fail, not ours).
NOT COMMITTED. See vault 2026-11-06_mem_charge-at-booking-journey-tested-3-bugs-fixed.md.

**NEXT:** commit the fixes → deploy to staging (manual: push master → SSH forge@zapdev.co.za → pull →
composer install → migrate --force [add_reserved_until] → config/route:clear) → test PayFast RETURN/ITN
leg on the public URL (can't be done on localhost). Then 9.2 mobile 390px, 9.3 redirect /book→/start.
⚠ FIRST next session: Captain Zan says MAHALA has problems to check — handle that before/with this.

## Phase 0 — Groundwork & confirms
- [x] 0.1 Captain Zan confirmed D1–D4 (doctor pick vs auto, OTP/account, PayFast redirect, fee=R450).
- [x] 0.2 Backup touched files; branch-safe. PayFast SANDBOX creds in .env (test_mode=true) confirmed.
- [x] 0.3 Add feature flag `config/onboarding.php` enabled_slugs (default weight-loss only).

## Phase 1 — Styling vendored from Craig's journey (match 1:1)
- [x] 1.1 Concatenate Folder-1 landing.css + checkout.css (+ journey parts of style.css), scope under
      `.zapmed-onboarding`, rewrite assets/→/mockup-assets/. Write `resources/css/zapmed-onboarding.css`.
- [x] 1.2 Copy any journey-only assets into `public/mockup-assets/` (reuse shared ones).
- [x] 1.3 Add `resources/css/zapmed-onboarding.css` to vite.config inputs; build green.
- [x] 1.4 `layouts/onboarding.blade.php` (full-screen card chrome; mega menu optional). Drop CONCEPT banner.

## Phase 2 — Onboarding component skeleton + routing
- [ ] 2.1 `App\Livewire\Patient\Onboarding` with `stage` state machine (assessment→review→details→book→
      pay→confirm) + stage header/progress partial.
- [ ] 2.2 Route `GET /start/{slug}` name `onboarding.start` (public). Resolve treatment + disclose fee.
- [ ] 2.3 Draft persistence (guest-safe): store answers/stage so Back/refresh never loses data.

## Phase 3 — Assessment stage (reuse engine)
- [ ] 3.1 Load `AssessmentService::flowFor(slug)`; render ONE visible question at a time (number/single/
      multi/text/measurements), heading + "Why we ask" + Back/Continue + progress bar/percent.
- [ ] 3.2 Port `isQuestionVisible` branching; cursor walks only visible questions.
- [ ] 3.3 Autosave answers to draft each step.
- [ ] 3.4 Tests: advance, branching skips hidden, required validation, resume.

## Phase 4 — Review stage (editable)
- [ ] 4.1 Grouped answer review; each "Edit" jumps back to that question (sets cursor + stage=assessment).
- [ ] 4.2 Test: edit round-trips and preserves other answers.

## Phase 5 — Your details + OTP verification
- [ ] 5.1 Details form (name/cell/email) + SEPARATE consent checkboxes (privacy/telehealth required,
      marketing unchecked). 
- [ ] 5.2 `sendOtp()`/`verifyOtp()` reusing existing BulkSMS OTP/2FA plumbing.
- [ ] 5.3 On verify: create-or-attach patient User; create Assessment (pin category_id + manifest_version);
      write per-answer rows via `saveAnswer`; attach draft.
- [ ] 5.4 Tests: OTP gate; new-user create; existing-user attach; answers persisted with version.

## Phase 6 — Book stage (doctor + slot)
- [x] 6.1 Doctor/slot picker from availability (getAvailableDoctorsForSlot / getAvailableSlotsForDate,
      14-day window); mode video/telephone. (D1 default: patient picks.) — browser-verified.
- [x] 6.2 Fallback auto-assign (`autoAssignDoctor`) if none chosen.
- [x] 6.3 Tests: slot list correct; selection stored; fallback works; no-availability handled.

## Phase 7 — Pay stage (THE CORE — real charge at confirmation)
- [x] 7.1 Booking summary + line item "Consultation fee R[fee]"; button "Confirm booking & pay R[fee]".
- [x] 7.2 `confirmAndPay()`: re-validate slot free; BIND fee+currency server-side (ignore client value);
      create Appointment (is_paid=false, status=pending_payment) + reservation (`reserved_until`);
      create Payment(pending, reference); link assessment→appointment.
- [x] 7.3 Redirect to PayFast — VERIFIED: reached https://sandbox.payfast.co.za/eng/process in browser.
- [~] 7.4 ITN `payment/notify`: reuses existing webhook (flips is_paid+confirmed). ⚠ The ITN RETURN leg
      needs a PUBLIC url to test fully — do on STAGING, not localhost.
- [x] 7.5 return_url/pending/cancel pages wired (success/cancel routes exist).
- [x] 7.6 Appointment statuses pending_payment + reserved_until (migration add_reserved_until ran).
- [x] 7.7 Scheduled sweep: `onboarding:release-expired-reservations` (every 5 min in routes/console.php).
- [x] 7.8 Tests (PayFast testMode/stub ITN): pending→VALID flips is_paid+confirmed; tampered client fee
      ignored; duplicate ITN idempotent; declined/cancel preserves answers + releases slot; expiry swept.

## Phase 8 — Confirmation + copy/compliance
- [x] 8.1 Confirmation screen: doctor, mode, full date, SAST time, fee PAID, reference/receipt.
- [ ] 8.2 Copy audit: no "pay later/secure booking/charged after consult"; no eligibility/diagnosis
      promise (HPCSA); fee disclosed up front; marketing opt-in unchecked (POPIA).

## Phase 9 — Rollout
- [x] 9.1 Enable weight-loss behind the flag; browser-verify full journey end-to-end. DONE on :8002 →
      PayFast sandbox. (3 bugs found+fixed: 419 inactivity logout, email-verified bounce, member_number 500.)
- [ ] 9.2 Mobile pass (390px) — the journey must work one-handed.
- [ ] 9.3 When proven ON STAGING (ITN leg): redirect `/book` → `/start/{slug}`; retire BookAppointment stub.
- [ ] 9.4 Full test suite green (DONE: 496 pass / 1 pre-existing unrelated fail).

## OUT OF SCOPE
- Medication/subscription recurring billing (keep separate). Question CONTENT (question-bank/Dr-Anke).
- Non-weight-loss treatments until each has a live manifest + is enabled.
