# Question Bank & Assessment Admin — Requirements

**Project:** ZapMed telehealth · **Owner:** Craig White (client), Dr Anke (clinical approver)
**Author:** Naz · **Date:** 2026-11-03 · **Status:** Draft for Captain Zan approval

## 1. Problem

ZapMed's assessment questions live in a code file (`config/assessment-questions.php` — 24 treatment
slugs, 132 questions, 42 conditional branches). To change a question a developer must edit code, and
common questions (allergies, current meds, pregnancy) are duplicated across every treatment. Craig
wants a **centrally-managed question library** that he and Dr Anke edit from an **admin portal**,
with clinical sign-off and full version history — so new treatment categories launch without code.

## 2. Goals

- One **question bank**: each question defined once, reused across categories by ID.
- A shared **CORE block** auto-added to every category (medical history, current meds, allergies,
  pregnancy, age, height, weight, smoking, alcohol, "anything else for your doctor").
- A category = a **manifest**: an ordered list of question IDs + the auto CORE block.
- An **admin portal** (two screens) to manage questions and build categories with no code.
- **Versioning + clinical sign-off**: Draft → In review → Live, with Dr Anke approving clinical
  questions; every answer stores the question version + manifest version + its own timestamp.
- First wire the **Weight Management** journey onto the bank; load the rest through the portal.

## 3. Non-goals (this phase)

- Phase-2 cross-consult patient health profile (build the fields + timestamps now, the profile view later).
- Changing the booking/payment flow (separate brief — "charge at booking").
- Category landing-page redesign (separate brief).
- Importing Craig's Drive package is gated on receiving the file; schema is built to accept it.

## 4. User stories & acceptance criteria

### 4.1 Patient — taking an assessment
- **US-1** As a patient, when I start an assessment for a category, I see the CORE questions first,
  then the category-specific questions, then the free-text "anything else" question last.
  - AC: order is CORE → category → free-text; enforced by the assessment service, not the front end.
- **US-2** As a patient, follow-up questions appear/disappear based on my coded answers (branching),
  exactly as today.
  - AC: existing `show_if` behaviour preserved; no regression on the 42 current branches.
- **US-3** As a patient who started an assessment, the questions do not change under me if an admin
  edits the bank mid-way.
  - AC: the manifest version is pinned on the assessment record at start; the assessment completes on
    that version.
- **US-4** The front end only renders what the assessment service returns.
  - AC: no clinical wording or branching logic hard-coded in Blade/Livewire views.

### 4.2 Admin — question library (Screen 1)
- **US-5** As an admin, I see a searchable table of every question (ID, text, type, scope, status,
  "used in N categories", last changed, changed by) with filters (scope, status, category, clinical).
- **US-6** As an admin, before I edit a question I am shown how many categories use it and which ones.
- **US-7** As an admin, I can Edit, Preview, Duplicate, Retire a question. I can **never delete** —
  only retire (past answers still reference it).
  - AC: no destructive delete path exists; retired questions remain readable.

### 4.3 Admin — category builder (Screen 2)
- **US-8** As an admin, I pick a category and see its flow as an ordered list; the CORE block sits at
  the top, locked and greyed, labelled "Common to every category. Edit in the question library."
- **US-9** As an admin, I add a question (search the bank or create new) and reorder category questions
  by drag.
- **US-10** As an admin, if I add a question the CORE block already covers, or one already in the list,
  the builder blocks it and names the question that already asks it.
- **US-11** As an admin, I can Preview the whole category flow as a patient sees it, branches included.
- **US-12** As an admin, I can create a brand-new category and publish it without a developer.
  - AC (the acceptance test for the whole feature): Craig loads a new category end-to-end through the
    portal — no code change, no deploy.

### 4.4 Publishing & change history
> **DECISION (Captain Zan, 2026-11-03):** the **main system admin (Craig) is the single gate.** The
> question bank is **admin-only** — regular doctors have NO access to it (doctors churn; they must not
> edit clinical screening questions). There is **no separate clinical-approver role**; Craig publishes
> everything. A change reason + changelog is still recorded on every publish for audit.
- **US-13** Only an admin can create/edit/retire/publish questions and categories. Doctors cannot
  reach the question bank at all.
- **US-14** When editing an existing question, the admin sees the current wording alongside the new
  wording before saving the new version.
- **US-15** On publish, a new version is saved (never overwrite), recording date, person and reason;
  a changelog shows on the question and the category.

### 4.5 Doctor — reviewing answers (phase 1)
- **US-16** The doctor sees answers grouped by section (Medical history, Medicines, Allergies,
  Pregnancy/reproductive, Category answers, Patient notes), each with the date/time it was given.
- **US-17** A "Needs attention" panel at the top holds: any answer the bank flags (e.g. a positive
  contraindication), every free-text answer, and anything marked "I'm not sure".

### 4.6 Data for phase 2 (build now)
- **US-18** Every answer stores its own timestamp and the question version it answered — not one
  timestamp per assessment.
  - AC: answers are individual rows (or structured with per-answer version + timestamp), so the phase-2
    cross-consult timeline is a view over existing data with no migration.

## 5. Clinical gaps to resolve with Dr Anke (blocking CORE finalisation)
- Add to CORE (missing/inconsistent in source): date of birth/age, height, weight, smoking, alcohol.
- Birth-control & menopause assessments must screen smoking + age (contraindication).
- Fix weight-loss branch: allergy questions currently gated behind a "No" answer.
- Remove intra-assessment duplicate questions (acne meds twice; WL mood/ED twice).

## 6. Compliance constraints (from research.md / HPCSA / POPIA)
- The questionnaire must **never** promise eligibility, diagnosis or a prescription (HPCSA Booklet 10).
- Health answers stay encrypted at rest; never placed in URLs, analytics, or ad pixels.
- Consent gates stay separate (privacy / telehealth care / payment / marketing-off-by-default).

## 7. Dependencies
- Craig's Google-Drive handoff package (18 manifests, CORE library, 334 Draft records, schemas,
  example payloads) — the real import source. Not yet received.
- Dr Anke's clinical review of CORE wording + the 5 missing questions.

## 8. Success criteria
- Weight-Management runs entirely off the bank (no `config/assessment-questions.php` for that journey).
- Craig adds a new category through the portal with zero developer involvement (US-12).
- No regression in existing booking/assessment behaviour; all existing tests stay green.
