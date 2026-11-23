# Craig final handoff (26 Nov) - weight loss only - TASKS

Source: `C:\Users\zande\Documents\Zapmed - Craig_New_Files\zapmed-final-developer-handoff nov 26.zip` (extracted to `...\handoff-nov26\dist`; run `python -m http.server 8765` there to view).
Scope (Captain Zan, 23 Nov): WEIGHT LOSS ONLY. Pricing first, then his questions (through our question bank, NOT hard-coded), then home page design changes, then the weight-loss journey "exactly like he did". Staging zapdev.co.za only. Never zapmed.co.za.

Reference: Craig's journey = `dist/weight-loss/assessment/journey.js` (screens: question -> review -> details -> book -> pay -> confirmed).

## Decisions made while planning (flag to Captain Zan if wrong)
- Consultation fee for weight loss = R700 (first consult, 45 min). Overrides the doctor-profile R450 default for this treatment only.
- R450/month Health Coach membership is shown on the site (price text) but NOT charged at booking (Craig's pay screen only charges R700). Recurring billing = separate later task.
- Keep our SMS OTP step (security decision 2026-11-05) but add Craig's fields (SA ID number / no-ID option, WhatsApp-or-email preference, password + confirm).
- Questions live in the bank (category `weight-loss`), published Live. His 14 questions replace the 24 from the June docx for weight-loss. Old 24 are retired, not deleted.
- Blood-test file upload: not built (his is a mock). Question and test checklist yes; real upload = later task.
- Card form on his pay screen is a placeholder; we keep PayFast redirect.

## Phase 1 - Pricing
- [ ] 1.1 `config/treatments.php` weight-loss: price_once_off 70000, price_monthly 45000, price text "R700 first consultation, then R450/month coaching", duration 45.
- [ ] 1.2 BookingJourney: treatment price + duration override doctor-profile defaults (currently doctor R450 always wins).
- [ ] 1.3 Pay screen: show R700 + "Treatment, if prescribed, costs extra. A prescription is not guaranteed." + prescribing consent checkbox (required, stored in consent record).
- [ ] 1.4 Update FAQ/pricing copy that says R450 consult / R220 for weight loss (config/faq.php etc).
- [ ] 1.5 Tests: fee bound server-side = 70000 for weight-loss; other treatments unchanged.

## Phase 2 - Craig's 14 questions in the question bank
- [ ] 2.1 New bank question types: `medicines` (list + skip), `measurements` (weight + height sliders, stored "85 kg, 170 cm"). Emit `why_we_ask` in AssessmentService::toLegacyShape (currently dropped).
- [ ] 2.2 Artisan command `questionbank:import-craig-weight-loss` (idempotent): retire old weight-loss links, create new questions + follow-ups with branching, publish category Live (manifest version bumped).
- [ ] 2.3 Engine: "None of these" exclusive on checklists; flag rules (MTC/MEN2, pregnancy, eating disorder, pancreatitis etc) for doctor "Needs attention".
- [ ] 2.4 Doctor ConsultationScreen shows the new answers (measurements -> BMI line).
- [ ] 2.5 Tests: flow order, branching (conditions Yes -> detail; allergies Yes/Not sure -> detail; past Yes -> experience + still using -> why stopped), save + versions.

## Phase 3 - Home page design changes
- [ ] 3.1 Diff Craig's `dist/index.html` + css vs our `welcome` / `zapmed-home` (hero, nav, categories, steps, why, pricing/membership, reviews, footer).
- [ ] 3.2 Port hero ("Doctor Care. 100% Online. Convenient. Private." + coastal couple + health-data cards + "Your health in review" pause).
- [ ] 3.3 Port other changed sections; pricing section: Everyday R220/mo, Weight-loss R450/mo.
- [ ] 3.4 Compress images (16MB review photo etc), keep Lighthouse sane.
- [ ] 3.5 Remove "Website concept / Not a live clinical service" banner (do NOT ship it).

## Phase 4 - Weight-loss journey exactly like Craig's
- [ ] 4.1 Weight-loss landing page `/weight-loss` = his `weight-loss/index.html` (+ page.css/js, assets), Get started -> `/start/weight-loss`.
- [ ] 4.2 Journey shell: logo + close X, sticky header with stage labels + "Question n of 14" + progress bar, centred bold question, answer cards, Back pill, "Why we ask" strip.
- [ ] 4.3 Question screens: all types (single, follow-up textarea, medicines w/ add + SKIP, sliders, checklists, history personal+family, eating + diet text, wellbeing, final text).
- [ ] 4.4 Review screen: tappable rows with Edit + "Needs clarification", two required consents (self only + true; side effects).
- [ ] 4.5 Details screen "Almost there": name, email, +27 cell format, SA ID (13 digits) or no-ID, WhatsApp/email preference, password + confirm (min 10), T&C/privacy consent (required), marketing (optional). Then our SMS OTP.
- [ ] 4.6 Book screen: "soonest available" shortcut + choose specialist cards (photo, credentials, read more) with slot buttons, video/telephone (phone confirm), sticky bottom bar.
- [ ] 4.7 Pay screen: doctor summary + Change, R700 line, prescribing consent, PayFast.
- [ ] 4.8 Confirmed screen: "What happens next" grid, Login to my account.
- [ ] 4.9 Consent log (key, wording, version, timestamp) stored per consent.
- [ ] 4.10 Mobile + desktop check in browser, full test suite, push only on Captain Zan's go.

## Later / out of scope now
- Other 6 categories (Craig has not designed them), health-coach recurring billing, blood-test upload, WhatsApp coach channel, Crisp replacement.

---
## STATUS (end of 23 Nov session) - built and tested on the LOCAL copy, NOT committed, NOT pushed, NOT on zapdev yet

DONE
- Phase 1 pricing: weight loss = R700 first consultation (45 min, bound server-side, overrides doctor R450 default), R450/month coach shown in copy. FAQ copy updated. Tests updated.
- Phase 2 questions: Craig's 14 screens (22 bank questions) loaded as the LIVE `weight-loss` category via `php artisan questionbank:import-craig-weight-loss` (idempotent; old 24 retired). New bank types `medicines`, `measurements`; new `layout` (with_previous) + `screen_title` columns; `why_we_ask` now reaches the patient; admin edits no longer drop branching/flags.
- Phase 3 home: Craig's final home page ported (hero "Doctor Care. 100% Online. Convenient. Private.", category cards, why-zapmed, topic sections, membership). Our mega menu kept. Hero pause button NOT ported (Captain Zan's earlier decision). Images -> WebP (16MB photo is now 244KB).
- Phase 4 journey: landing page `/weight-loss` = his page; `/start/weight-loss` = his journey look and flow (14 screens, grouped follow-ups, review + 2 consents, details with SA ID / WhatsApp-or-email / password, SMS code, soonest-or-specialist booking, 45-min aware slots, pay with prescribing consent, "what happens next" on the payment success page). Consents logged with wording + version.
- Security fix: the public journey no longer logs a visitor in to an existing account by email (old behaviour could be abused).
- Bug fixed: bookings now block every 15-minute slot they overlap (a 45-minute booking used to block only its first slot).
- Tests: 567 pass. 2 failures NOT from this work: PatientJourneyTest::treatment_pages_are_accessible (looks for the words "Weight Loss"; page says "Weight-loss care", was already failing) and SparReconcilerTest::course_complete_resolves_journey_loop (SPAR).

OPEN / NEEDS CAPTAIN ZAN
- [ ] Commit + push + check on zapdev (waiting for your go). Many other uncommitted files exist from older sessions: commit only ours.
- [ ] Dietitians are NOT offered as consult doctors in the new journey (they are coaches). Confirm.
- [ ] Craig's home page FAQ/reviews/dialog text still has mockup wording ("This mockup cannot prescribe", "Supplied excerpt - verification pending", legal pop-ups "design placeholder"). Needs real copy from Craig before launch.
- [ ] Hero pause button: Craig's design has one; we left it out. Say if you want it back.
- [ ] Doctor consultation screen: show weight + height as BMI line (2.4 not done).
- [ ] Blood-test file upload (his mock only lists file names) = not built.
- [ ] Clinical flags (pancreatitis, eating disorder, MTC/MEN2, pregnancy, allergy Yes) are my proposal: Dr Anke to confirm.
- [ ] R450/month coach: still NOT charged anywhere (recurring billing = separate job).
- [ ] 17 other treatments keep the old journey look? NO: they now use the same new look (shared component). Check one other treatment visually.
- [ ] PayFast sandbox end-to-end on zapdev with R700.
