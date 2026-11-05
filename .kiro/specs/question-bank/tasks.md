# Question Bank & Assessment Admin — Tasks

**Reads with:** requirements.md, design.md (same folder).
Work top-down. Each task ends with verification. Nothing committed/pushed until Captain Zan says checkout.

## Phase 0 — Safety & groundwork
- [x] 0.1 Back up the files this feature touches (done: `Documents\Zapmed\_backups\question-bank_<ts>\`).
- [x] 0.2 Open decisions resolved (Captain Zan, 2026-11-03): bank is **admin-only (Craig)**, no
      clinical-approver role / no Dr-Anke gate; per-row encrypted answers; question bank before
      charge-at-booking.

## Phase 1 — Data model  ✅ DONE (2026-11-03)
- [x] 1.1 Migration: `assessment_questions` table (design §2).
- [x] 1.2 Migration: `assessment_question_versions` table.
- [x] 1.3 Migration: `assessment_categories` table.
- [x] 1.4 Migration: `assessment_category_questions` table (with unique constraint).
- [x] 1.5 Migration: add `manifest_version` + `category_id` to `assessments`; create `assessment_answers`.
- [x] 1.6 Models + relations: `AssessmentQuestion`, `AssessmentQuestionVersion`, `AssessmentCategory`,
      `AssessmentCategoryQuestion`, `AssessmentAnswer`. Per-row encrypted cast on `answer`.
      Wired `category()`/`assessmentAnswers()`/`manifest_version` into existing `Assessment` model.
- [x] 1.7 `php artisan migrate` on local SQLite — all 5 tables + 2 new assessments columns verified.
      `BookingAssessmentCheckboxTest` green (3 passed, 10 assertions). No regression.

## Phase 2 — Services (the engine)  ✅ DONE (2026-11-03)
- [x] 2.1 `AssessmentService::flowFor()` — assemble CORE → category → free-text, each with live version.
- [x] 2.2 Port branching (`isQuestionVisible`) semantics into the service: `branch_rules` → `show_if`,
      emitting the LEGACY array shape so the existing patient view + validation work unchanged.
- [x] 2.3 `manifestVersionFor()` + `categoryIdFor()` for pinning on assessment start. (versionedFlow
      doctor/resume reader deferred to Phase 6 where the doctor view consumes it.)
- [x] 2.4 `saveAnswer()` — writes `assessment_answers` with version + timestamp + computed `flagged`.
- [x] 2.5 `QuestionBankService` — create/edit(new version)/publish(admin-only)/retire(not delete)/
      duplicate; add-to-category with CORE + duplicate guards; reorder; create/publish category.
- [x] 2.6 `QuestionBankEngineTest` — 9 tests: flow order, legacy-shape+show_if parity, draft-category
      hidden, manifest bump/pin, per-answer version+timestamp+flag, edit-creates-version, CORE guard,
      duplicate guard, retire-not-delete. 12 green incl. regression; 14 green across assessment/booking.
- Note: made `assessments.answers` nullable (non-sqlite) so question-bank assessments skip the legacy
  blob; historical rows keep theirs. Local sqlite tests pass `answers => []`.

## Phase 3 — Seed from existing config  ✅ DONE (2026-11-03)
- [x] 3.1 `php artisan questionbank:import-config [--fresh]` — reads config/assessment-questions.php +
      config/treatments.php, creates questions + v1 versions (Draft) + category manifests. Maps legacy
      type→bank type (radio→single, checkbox→multi, file→upload) and show_if→branch_rules. Idempotent
      (--fresh wipes only prior config-import DRAFT rows; never touches Live or admin-created rows).
- [x] 3.2 Verified: 24 categories, 116 questions, 114 links, ALL Draft, 0 Live. Weight-loss manifest
      reconstructed exactly (16 Qs, types + branching preserved).
- [x] Test: `QuestionBankConfigImportTest` (4 tests) — draft import, order preserved, show_if→branch_rules,
      --fresh idempotent. 13 green across import+engine suites.
- ⚠️ NOTE: only 2 CORE auto-detected, because the legacy config uses INCONSISTENT ids for the same
      clinical concept across treatments (e.g. `pregnant` vs `pregnancy`), so id-frequency dedup can't
      see them as equal. This is the exact duplication Craig flagged. Finalising the CORE block is
      Dr Anke's clinical review job (Phase 7) — NOT auto-guessed here. The importer deliberately does
      not fake clinical equivalence.

## Phase 4 — Patient flow cutover (Weight Management first)  ✅ DONE (2026-11-03)
- [x] 4.1 `questionsFor()` on `BookAppointment` chooses bank-vs-config; gated by `config/questionbank.php`
      (`enabled_slugs`, default `weight-loss`) AND requires a LIVE manifest, else falls back to config.
      All 4 read sites (prefill, validate, save, blade) route through it.
- [x] 4.2 Pin `category_id` + `manifest_version` on assessment start; dual-write per-answer rows
      (`saveAnswer`) + keep legacy blob during transition.
- [x] 4.3 Verified in a REAL browser (Playwright, patient login): weight-loss assessment renders from
      the DB bank, correct 16 questions, conditional branching works both directions, 0 console errors.
      Tests: `BookAppointmentQuestionBankCutoverTest` (4) + full regression 40 green / 99 assertions.
- 🐞 Browser-found + fixed during cutover (see mem note):
    1. Mis-detected CORE (`CORE_SYMPTOMS`/`CORE_DURATION`) bled rosacea Qs into weight-loss → demoted
       them out of CORE (they were generic recurring ids, not true clinical commons).
    2. Importer accumulation: `--fresh` wasn't wiping demoted rows → 3× duplicate questions. Rewrote
       `wipeConfigImported` (wipe all config-import rows except human-published) → idempotent.
    3. branch_rules dependency keys were legacy lowercase ids, not the uppercased bank keys → conditionals
       never matched. Added per-category dependency remap in the importer.
    4. PRE-EXISTING blade bug: checkbox inputs lacked the Alpine `x-on:change="toggleCheckbox(...)"`
       handler that radio/text/select had → checkbox-driven conditionals never showed client-side.
       Added the handler. Regression guard added.

## Phase 5 — Admin portal  ✅ DONE (2026-11-03)
- [x] 5.1 `Admin\QuestionLibrary` + blade: searchable table (text/key), filters (scope/status/clinical),
      "used in N" count, status badges, clinical tag; Edit(=new version)/Duplicate/Retire(no delete);
      usage warning before edit; create + save-draft + save&publish. Route `GET /admin/question-bank`.
- [x] 5.2 `Admin\CategoryBuilder` + blade: treatment picker, locked greyed CORE block, draggable flow
      (inline Alpine drag → reorder), add-from-bank search, duplicate/CORE guards surface as errors,
      preview full flow, create-new-category + publish. Route `GET /admin/question-bank/categories/{slug?}`.
- [x] 5.3 Publishing UI: Draft→live via save&publish + category publish; change-reason recorded; no
      clinical-approver gate (admin-only).
- [x] 5.4 Sidebar nav entry "Question Bank" added.
- [x] 5.5 `QuestionBankAdminTest` (7): doctors FORBIDDEN from the bank, admin OK, library creates draft,
      retire≠delete, duplicate-add shows error, mid-flow edit doesn't change in-progress assessment,
      **US-12 new-category-end-to-end-via-portal**. 42 green / 102 assertions total.
- [x] Browser-verified (admin login): Question Library renders 116 Qs paginated w/ filters + badges;
      Category Builder renders picker + 16-Q weight-loss flow w/ conditional badges + add/preview/publish;
      0 console errors. Fixed: layouts.app has no @stack('scripts') → moved drag JS to inline x-data.

## Phase 6 — Doctor view
- [ ] 6.1 Group answers by section + per-answer timestamps; "Needs attention" panel (flagged + free-text
      + "not sure"). Read `assessment_answers`, fall back to legacy blob.
- [ ] 6.2 Verify as doctor on a weight-loss assessment.

## Phase 7 — Craig's package (gated on receiving the Drive file)
- [ ] 7.1 Extract the handoff package (18 manifests, CORE library, 334 records, schemas, payloads).
- [ ] 7.2 Import command: load 334 records as **Draft**, reconcile against the config-seeded set
      (match by meaning, flag conflicts, don't silently overwrite).
- [ ] 7.3 Hand to Dr Anke for clinical review → publish CORE + the 5 missing questions + weight-management.

## Phase 8 — Retire the old path
- [ ] 8.1 Once all live categories run off the bank, remove `config/assessment-questions.php` usage and
      the dual-write blob; keep the file archived in the backup.
- [ ] 8.2 Full suite green; update `ZAPMED_SYSTEM_REFERENCE.md` to describe the bank.

## Blocked / needs client
- Dr Anke clinical review (CORE wording + 5 missing Qs + branch fix) — Phase 7.3.
- Google-Drive handoff package — Phase 7.1 (whole of Phase 7 waits on it).
