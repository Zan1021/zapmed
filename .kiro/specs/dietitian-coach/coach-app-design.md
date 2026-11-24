# Zapmed Coach App: scope, design and tasklist

**Date:** 2026-11-23 - **Owner:** Naz for Captain Zan - **Status (2026-11-24): Phase 0, Phase 1 and v2 steps 2.1 to 2.3 are BUILT and DEPLOYED to zapdev (release 83847679). Next: 2.4 activity, 2.5 cycle tracker, 2.6 insights, then Phase 3. Waiting list is in section 6.**
**Master spec:** Craig's System Build Guide (19 Nov), Appendix C (25 screens). Gap analysis: vault `2026-11-23_mem_craig-build-guide-gap-analysis-and-plan.md`. Supersedes the older dietitian-coach `requirements/design/tasks.md` where they differ (R450 not R250, web app, assignment-based access).
**Mockup:** `design/index.html` (open in a browser; left panel jumps to any of the 24 screens + coach sheet). All 24 screens render with no errors; key flows tested (onboarding, water, side-effect doctor flag, cycle switch hides all cycle items).

## 1. Decisions that shape the build
- kg ONLY (South Africa). The kg/lb switch from Craig's guide is built but OFF (`config coach.units.allow_lb`, env COACH_ALLOW_LB).`n- Web app inside the Zapmed platform (Laravel + Livewire + Alpine), mobile first, works from 360 px. Same login as the patient account. Installable to the home screen (PWA) later.
- Brand = website brand: dark teal #203E3E, teal #2E767F, ivory #F4F0E7, Manrope body, Prata headings, white cards 20 px radius, 52 px pill buttons. Women's sage palette (#526E60 / #729D94 / #DCE5DA) used for cycle items. Craig's prototype colours (Comfortaa) NOT used.
- Coach add-on R450/month. Starts after the doctor prescribes (guide: a doctor can also add it). Repeat scripts continue if coach payment stops.
- Never show a medicine name on any app screen, reminder or message ("your medication"). Insights always say "general information, not medical advice".
- Clinical numbers (alert levels 7 / 9, targets 1,450 kcal / 92 g / 28 g / 130 g / 50 g / 2,200 mL, cycle wording) are PROVISIONAL, approved by Captain Zan only. Keep them in config, not code, so Dr Anke can change them.
- Data on AWS (Cape Town planned). Health data encrypted (existing `EncryptsSensitiveFields` pattern).

## 2. Scope
### In v1 (MVP, launchable)
Onboarding (worry question, plan reveal, cycle switch, welcome call). Track home. Weight tracker + log + goal. Medication tracker + log dose. Side-effect log + doctor escalation. Water. Reminders (in-app + WhatsApp/email). Coach sheet (message Amy, book a call, "something feels wrong"). Call booking. Profile. Milestone. Coach/doctor oversight view of the same data. Coach portal views are part of the Health Coach portal (see section 5).
### In v2 (needs content or third-party services)
Recipes + favourites, 7-day meal plan engine, personalise plan, nutrition + meal logging (search/quick add), cycle tracker, insights + weekly insight, activity + workouts.
### In v3 (real external services)
Food scan, menu scan, voice log, Apple Health / Google Fit sync, AI quick-question assistant, push notifications.
### Out of scope for now
Native iOS/Android, oz units for water, community build (decision pending: moderated WhatsApp group first), medicine pricing in the app.

## 3. Design (what the mockup shows)
Navigation: 5 tabs (Track, Plans, Insight, Coach, Profile) + quick-log "+" button (weight, water, meal, dose, side effect, activity, cycle). Every inner screen has a back arrow with history.

| # | Screen | Key behaviour in the design |
|---|---|---|
| 1 | Worry question | 4 choices, Amy's reply changes, step 3 of 7 |
| 2 | Plan reveal | 3 targets, cycle switch (on for women), welcome call |
| 3 | Tracking home | Cards: reminder, weight (+sparkline, predicted date), medication, cycle (women), meals, water (+250 button), activity |
| 4 | Weight | 14/30/90 chart, goal line, luteal shading, tip box, BMI sheet, history, goal |
| 5 | Log weight | +/- 0.1, kg/lb, cycle tag |
| 6 | Medication | Level curve (14 days past, 7 ahead dashed, "estimate only"), next/last dose |
| 7 | Log dose | 6 sites, next-site star, pain 0-10 |
| 8 | Log side effect | 9 defaults, sliders 0-10, add own, hide, restore; save needs a score above 0 |
| 9 | Saved | Normal: reassurance. Flag (abdominal pain 7+ or any 9+): red "needs a doctor's eyes", "Reach Dr Anke now" |
| 10 | Water | Filling drop, 4 quick adds, exact amount, last 5 + undo |
| 11 | Reminders | Hydration (window, smart skip, channel), weigh-in, dose day, meals, cycle heads-up; preview banner |
| 12-13 | Activity / log | 7-day bars, steps, health-sync switch, workout library with search |
| 14 | Cycle | Phase wheel, 4 cards, tip, mood, symptom chips, "period started today" |
| 15 | Nutrition | Protein/fibre/carb bars, calories, fat, 4 meal slots |
| 16 | Recipes | Rows by slot, hearts, favourites tab, recipe sheet with servings |
| 17-18 | Meal plan / personalise | 7-day strip, targets box, eaten circles, swap/trade/lock/remove, 4 templates, diet/allergy/avoid |
| 19-20 | Log meal / scan | Slot picker, 4 tiles, planned meal one-tap; scan modes (simulated) |
| 21 | Insight | Daily headline, level card, check-in, today's goal, feedback, weekly sheet, disclaimer |
| 22 | Profile | Programme card, rewards link, goals, details, lifestyle, cycle switch, kg/lb |
| 23-24 | Coach sheet / book | 4 options incl. red "something feels wrong"; 5 days, slots (full greyed), 15/30 min |
| 25 | Milestone | % lost, 3 stats, Amy note, maintenance call |

Design notes: all text at 14 px or above; buttons 52 px; red used only for safety; every estimate is labelled. Known mockup gaps: calories/steps and recipe data are demo values; no empty/error states yet; no desktop coach view yet.

## 4. Data model (new tables, all scoped by patient, encrypted where health data)
`coach_profiles` (patient settings: goal, height, units, diet, allergies, avoids, activity level, cycle on/off, cycle length/period length/last period) - `weight_entries` (date, kg, cycle phase) - `dose_entries` (date, site, pain) - `side_effect_entries` (date, symptom, score) + `custom_symptoms` (name, hidden) - `water_entries` (time, ml) - `meal_entries` (slot, name, kcal, protein, fibre, carbs, fat, source) + `foods` (created/saved) - `recipes` (+ nutrition, approved_by) + `favourite_recipes` - `meal_plans` / `meal_plan_items` (day, slot, recipe, locked) - `activity_entries` + `workouts` library (MET) - `cycle_entries` (mood, symptoms) - `reminder_settings` (type, on/off, window, channel) - `checkins` + `insight_feedback` - `coach_calls` (slot, length, status) - `coach_flags` (escalations: who, level, handled by, time).
Existing `progress_logs` / `patient_goals` stay for old data; new tables replace them for the coach app.

## 5. Build tasklist (in order; estimates are guesses)
**Phase 0 Foundations (2-3 d)**
- [x] 0.1 (done 2026-11-23, `config/coach.php`) Move targets/alert levels/templates to `config/coach.php` (provisional flag).
- [x] 0.2 (done: `layouts/coach.blade.php`, `resources/css/zapmed-coach.css`, tab bar, toast; sheet styles ready, sheet component comes with first sheet in Phase 1) Coach layout + design tokens (Tailwind theme from site brand), tab bar, sheets, toast, back history; 360 px test.
- [x] 0.3 (done: migration 2026_11_24_000001 + 9 models; access rules in `App\Services\Coach\CoachAccess`, tested) Migrations for MVP tables + models + policies (patient sees own; assigned coach/doctor sees by assignment; ops no coach notes).
- [x] 0.4 (done 2026-11-24, local, not committed: doctor offers the add-on from the consultation screen (CoachAddon::attachByDoctor, creates an UNPAID pending membership, nothing starts until paid); member pays at /coach/join (Livewire\Coach\Join) through the normal PayFast checkout (new shared SubscriptionCheckout, also used by My Subscription); first PayFast payment auto-assigns the coach with the fewest members (CoachAssigner, cap COACH_MAX_MEMBERS_PER_COACH, 0 = none) and hourly coach:assign-pending retries members still waiting; offer message OFF until COACH_ADDON_NOTIFY=true; 26 tests in CoachAddonTest, full suite 845 pass + the same 3 old failures. NOT built: ending the coach when the add-on lapses, a banner for the member.) Coach add-on: link to order/subscription (R450, doctor can attach, PayFast subscription), `coach_assignment` to a dietitian, access middleware.
**Phase 1 MVP (about 3 weeks)**
- [x] 1.1 (done 2026-11-23: `Livewire\Coach\Onboarding`, `Services\Coach\CoachOnboarding`, `/coach/start`; 10 tests + checked in a real browser at 390 px) Onboarding screens 1-2 (+ profile save, cycle switch default by gender). Welcome-call button waits for 1.9 booking.
- [x] 1.2 (done 2026-11-23: `Livewire\Coach\Today`, services `WeightStats`, `MedicationLevel`, `CycleCalendar`, `NextReminder`, `CoachClock` (SAST); 21 tests + real-browser check at 390 px) Track home (screen 3) with live cards. Cards: next reminder, weight, medication, cycle (when on), water (+250 live). Meals + activity cards wait for v2. Cards become links as 1.3 / 1.4 / 1.6 screens land.
- [x] 1.3 (done 2026-11-23: `Livewire\Coach\{Weight,LogWeight}` at `/coach/weight` and `/coach/weight/log`; services `WeightLogger`, `WeightChart`, `WeightTip`, `CoachUnits`; 30 tests + real-browser flow check. Deviation: goal steps by 1 in the member's own unit, not always 1 kg.) Weight: log, chart (14/30/90, luteal shading), tips, BMI sheet, goal rules (min 45 kg, below current), predicted date, kg/lb
- [x] 1.4 (done 2026-11-23: `Livewire\Coach\{Medication,LogDose}` at `/coach/medication` and `/coach/medication/log`; services `DoseLogger`, `MedicationChart`; 17 tests + real-browser flow. One dose per local day: logging again corrects today's.) Medication: log dose (6 sites + rotation + pain), level curve (illustrative), next dose = +7 days
- [x] 1.5 (done 2026-11-23: `Livewire\Coach\{LogSideEffect,FlagsInbox}`, `/coach/side-effects` and staff `/coach-flags`; services `SideEffectLogger`, `CoachEscalation`; 27 tests + real-browser flow. Flag = saved first, then a board alert (critical) and email+SMS to staff with NO health detail; set COACH_FLAG_NOTIFY on every server.) Side effects: list, custom/hide, save rules, flag -> `coach_flags` -> alert Dr Anke + red screen + "no queue" route; saved screens
- [x] 1.6 (done 2026-11-23, local, not yet deployed: `Livewire\Coach\Water` at `/coach/water`, service `WaterLogger`; 17 tests + real-browser flow. Exact amount 10-3000 mL whole numbers; undo only touches today; mL only (no oz). Hydration-reminders link arrives with 1.7.) Water: drop, quick adds, exact, undo
- [x] 1.7 (done 2026-11-23, local, not deployed: `Livewire\Coach\Reminders` at `/coach/reminders`; services `ReminderSettings`, `ReminderScheduler`, `ReminderSender`; command `coach:send-reminders` every 5 min; 27 tests + real-browser check. SENDING IS OFF until COACH_REMINDERS_SEND=true. Channels: WhatsApp (email until Meta is configured), email, SMS; no push yet. Meal nudges off until meal logging exists.) Reminders: settings + scheduler (hydration window/smart skip, weigh-in, dose eve + day), sent via NotificationDispatcher (WhatsApp then email), no health detail; preview
- [x] 1.8 (done 2026-11-23, local, not deployed: coach menu `CoachSheet` from the Coach tab, member thread `/coach/messages`, staff `/coach-inbox` + `/coach-inbox/{patient}`; service `CoachMessaging`; table `coach_messages` (migration 2026_11_24_000002); 25 tests + real two-user browser flow. Quick question (AI) and Book a call are shown as "coming soon" (1.9 booking, AI = phase 3). Notifications about new messages are OFF until COACH_MESSAGE_NOTIFY=true and never carry the text.) Coach sheet + messaging with Amy (in-app thread + WhatsApp mirror), "something feels wrong" -> doctor alert
- [x] 1.9 (done 2026-11-23, local, not deployed: `Livewire\Coach\BookCall` at `/coach/book`, service `CoachCalls`, migration 2026_11_24_000003 (slot_key unique, purpose, reminded_at); 32 tests + real-browser flow. Next 5 working days, 15/30 min, slots on the half hour 08:30-16:30 minus 12-13 break, 2 h notice, one upcoming call per member, cancel, welcome call from onboarding, coach sees upcoming calls in /coach-inbox. Confirmation + 1 h reminder OFF until COACH_CALL_NOTIFY=true.) Call booking (15/30 min, Amy's availability, confirmation by WhatsApp/email)
- [x] 1.10 (done 2026-11-24, local, not committed: `Livewire\Coach\{Profile,Milestone}` at `/coach/profile` and `/coach/milestone`; services `ProfileSettings`, `MilestoneStats`; config `coach.profile` + `coach.milestone`; 14 tests + real-browser check at 360 px. Every choice saves at once and must be a known key. "Share Zapmed, earn rewards" shown as coming soon (no member share link exists yet). Check-ins = completed calls + booked calls already in the past.) Profile + milestone screens
- [x] 1.11 (done 2026-11-24, local, not committed: `Livewire\Coach\{Board,PatientView}` at staff `/coach-board` and `/coach-board/{patient}`; service `CoachPatientBoard`; model `CoachNote` + migration 2026_11_24_000004 (body encrypted); real-browser check at 360 px. Board: flags first, then unread, then quietest (7 days no weigh-in = "quiet"). Member view: progress, goals/lifestyle, weight, dose log, side effects (30 days), flags, check-ins, notes. Private notes = author only (a new coach never sees the old coach's private notes); shared = coach + treating doctor. Only the assigned coach writes notes. Access re-checked on every render.) Coach portal: assigned patient board, patient view (weight, dose history, side effects, goals), check-ins log, flags inbox, private coach notes vs shared notes
- [x] 1.12 (done 2026-11-24, local, not committed: "Coaching summary" card on the doctor consultation screen via `CoachPatientBoard::doctorSummary`, link to the full view. Treating doctor only; shows start/now/goal, 7-day change, last dose date, open flags, side effects (7 days), coach, shared notes. Never private notes.) Doctor view: concise coaching summary on the patient record
- [x] 1.13 (done 2026-11-24: 31 new tests (CoachProfileMilestoneTest, CoachStaffBoardTest) cover access control, notes privacy, validation, cycle switch hides for men, kg-only; full suite 819 pass, same 3 pre-existing failures; real-browser checks at 360 px for board, member view, profile, milestone (no horizontal overflow). A separate Dusk journey test was NOT written: the browser checks were done by hand with Playwright.) Tests: access control, flag thresholds, goal rules, cycle switch hides, 360 px; Dusk smoke test of the journey
- [x] 1.14 (done 2026-11-23 to 2026-11-25: every step above is deployed to zapdev, last release 83847679 / commit 2bc4dab. Real-browser checks by Naz at 360 to 390 px; Captain Zan has NOT yet clicked through it himself.) Deploy to zapdev; test with Captain Zan
**Phase 2 Nutrition + insights (3-4 weeks, needs Craig's content)**
- [x] 2.1 (done 2026-11-25, deployed zapdev bd5ff72: tables 
ecipes, avourite_recipes; 20 SAMPLE recipes via CoachDemoRecipeSeeder (shown as 'Sample recipe', replace with Amy's); member screens /coach/recipes + /coach/recipes/{id} (servings 1-4 scale, Recipe/Nutrition tabs, heart, add to plan); admin editor /admin/coach-recipes (admin + health coach edit, only a health coach approves, any edit clears approval); members only see approved recipes that suit their diet/allergies/avoids. NOT built: photos (colour tile + emoji), 'Log as eaten now' (waits for 2.3).) Recipes + admin editor (Amy approves), nutrition values
- [x] 2.2 (done 2026-11-25, deployed zapdev: MealPlanner, /coach/plan, /coach/plan/personalise; 4 templates (limits are PROVISIONAL in config coach.meal_plan), favourites first, 1-2 'new for you' a day, no repeat of yesterday's slot, protein boost snack, swap/trade/lock/remove/favourite/add, regenerate keeps locks, diet/allergy/avoid change removes banned meals even locked; 33 + 7 tests; eaten tick stored but not yet in nutrition (2.3)) Favourites, meal-plan engine (4 templates, favourites first, 1-2 new meals, no repeat slot, protein boost, diet/allergy/avoid filter, locks, regenerate)
- [x] 2.3 (done 2026-11-25, deployed 2bc4dab: table meal_entries, MealLogger; screens /coach/nutrition and /coach/nutrition/log (quick add, planned meal one-tap, Recent), Meals card on Track home, ticking a plan meal logs it (unticking or swapping removes it), 'Log as eaten now' on the recipe page, recipe photo upload in the admin editor (public disk, safe on zapdev). NOT built: food search database, food scan, menu scan, voice log (need real services; shown as 'coming later').) Nutrition + meal logging (search, quick add, planned meal one-tap), plan eaten -> nutrition
- [ ] 2.4 Activity + workouts library (MET calories)
- [ ] 2.5 Cycle tracker (women, switch hides everything, phase tips, predictions marked estimates)
- [ ] 2.6 Insights (rule-based text from data, daily + weekly, check-in, feedback, disclaimer), rules reviewed by Amy/Dr Anke later
**Phase 3 Services (later)**
- [ ] 3.1 Food/menu scan, voice log, 3.2 Apple Health/Google Fit (needs native wrapper or alternative), 3.3 AI quick-question, 3.4 PWA install + push, 3.5 community (decision), 3.6 partner discounts/rewards.

## 6. Waiting on
Craig: Amy + Angelique registration numbers/emails, capacity and cover, community format, real recipes/exercises/articles, WhatsApp number approved by Meta (Captain Zan). Dr Anke: later clinical sign-off of alert levels, targets and wording.

## 7. Risks
Meta WhatsApp approval can take weeks (fall back to email + in-app until then). Clinical content is provisional until Dr Anke signs off. Health sync and scans need third-party services or a native wrapper. Meal-plan engine and recipe content are the biggest effort after the MVP.
