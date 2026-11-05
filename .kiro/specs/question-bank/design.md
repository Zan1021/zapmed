# Question Bank & Assessment Admin — Design

**Author:** Naz · **Date:** 2026-11-03 · **Status:** Draft for Captain Zan approval
**Reads with:** requirements.md (same folder). Stack: Laravel 12 + Livewire 3 + PostgreSQL.

## 1. Approach

Promote the question data out of `config/assessment-questions.php` into database tables, add a
versioned publishing workflow, build two admin Livewire screens, and introduce an
`AssessmentService` that assembles a category's question flow. The existing patient booking UI is
re-pointed from `config()` to the service — same rendering, new source. The old config stays as a
**fallback + seed source** until Weight Management is cut over and verified, then is removed for that
journey.

Design principle (Craig's hard constraint): **the front end renders only what the service returns.**
No clinical wording or branching logic in Blade/Livewire.

## 2. Data model

New tables (all with `created_at`/`updated_at`):

### `assessment_questions` — the bank (one row per question, current/live pointer)
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| question_key | string unique | stable code, e.g. `CORE_ALLERGIES`, `WL_PREV_MED`. Never changes. |
| scope | enum(`core`,`category`) | |
| clinical | boolean | true = needs clinical approver to publish |
| current_version_id | FK → assessment_question_versions | the Live version pointer |
| status | enum(`draft`,`in_review`,`live`,`retired`) | lifecycle of the question |
| created_by / updated_by | FK users | audit |

### `assessment_question_versions` — immutable version history
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| question_id | FK → assessment_questions | |
| version | unsigned int | rises by 1 per edit |
| text | string | patient-facing question |
| helper_text | string null | grey line under the question |
| why_we_ask | string null | expandable one-liner |
| type | enum | `single`,`multi`,`text`,`textarea`,`number`,`date`,`select`,`upload` |
| options | json null | `[ {code, label, flag?} ]` — each option has a stable code |
| icon | string null | icon name per Brief 2 |
| branch_rules | json null | `show_if` equivalent: `{ depends_on_key: [option_code,...] }` |
| flag_rule | json null | which answer(s) raise a "Needs attention" flag for the doctor |
| profile_card | string null | phase-2 placement hint |
| published_at | timestamp null | |
| published_by | FK users null | |
| reason | string null | why this version was published |

> Versions are **never overwritten**. Editing a Live clinical question creates a new Draft version;
> publishing it moves the `current_version_id` pointer and records who/when/why.

### `assessment_categories` — the manifest
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| slug | string unique | matches treatment slug, e.g. `weight-loss` |
| name | string | |
| status | enum(`draft`,`in_review`,`live`,`retired`) | |
| manifest_version | unsigned int | rises on any question-list/order change |
| created_by / updated_by | FK users | |

### `assessment_category_questions` — ordered category→question links (category-specific only)
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| category_id | FK | |
| question_id | FK | |
| position | unsigned int | drag-order |
| unique(category_id, question_id) | | blocks duplicate add (US-10) |

> The CORE block is **not** stored per-category. It is resolved at assembly time from all
> `scope=core, status=live` questions in a fixed CORE order (a `core_order` int on core questions, or
> a dedicated `core_manifest` row). This guarantees CORE is identical everywhere and cannot be removed
> from a category (US-8).

### Answer storage — evolve `assessments` + new `assessment_answers`
Keep the `assessments` row (one per taken assessment) and ADD:
- `assessments.manifest_version` (int) — pinned at start (US-3).
- `assessments.category_id` (FK, nullable during transition).
- New **`assessment_answers`** table — one row per answered question (US-18):

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| assessment_id | FK | |
| question_id | FK | |
| question_version | int | the version the patient actually answered |
| answer | text (encrypted) | value or JSON for multi |
| flagged | boolean | computed from version `flag_rule` at save |
| answered_at | timestamp | per-answer timestamp |

> The legacy `assessments.answers` encrypted blob is retained read-only for historical rows; new
> assessments write `assessment_answers`. The doctor view reads answers from the new table, falling
> back to the blob for old rows. This is the phase-2 enabler with no later migration.

## 3. Services

### `AssessmentService` (new — `app/Services/Assessments/AssessmentService.php`)
- `flowFor(string $categorySlug): array` — returns the ordered, assembled flow:
  CORE (live, fixed order) → category questions (by position) → free-text last. Each entry carries
  the live version's text/type/options/branch_rules/icon. **This is the only thing the front end sees.**
- `manifestVersionFor(string $categorySlug): int` — for pinning on the assessment at start.
- `versionedFlow(Assessment $a): array` — reconstructs the exact flow a patient saw (uses pinned
  manifest + per-answer question_version) for the doctor review + resume.
- `saveAnswer(Assessment $a, string $questionKey, mixed $value): void` — writes an
  `assessment_answers` row with version + timestamp + computed `flagged`.

### `QuestionBankService` (new)
- Edit/duplicate/retire questions; create new Draft versions; move status; enforce "used in N
  categories" lookups; block delete.
- `publish(question, admin)` — admin-only. Writes new version pointer + records reason/changelog +
  audit. **No clinical-approver gate** (Captain Zan decision: Craig/admin is the single gate).

### Reuse what exists
- Branching: port `isQuestionVisible()` logic from `BookAppointment` into the service (same semantics,
  now driven by `branch_rules`).
- Audit: the app already has `Compliance\AuditTrail` (hash-chained) — log publish/retire there.

## 4. Admin UI (two Livewire components under `app/Livewire/Admin/`)

### `QuestionLibrary.php` + blade (Screen 1 — US-5..7)
- Searchable table; filters scope/status/category/clinical; columns incl. "used in N categories".
- Row actions Edit / Preview / Duplicate / Retire (no delete).
- Edit panel shows usage warning ("Used in 14 categories") before typing.
- Route: `GET /admin/question-bank` → `role:admin`.

### `CategoryBuilder.php` + blade (Screen 2 — US-8..12)
- Pick category → ordered flow; CORE block locked/greyed at top with the note.
- Add question (search bank or create new); drag reorder (Livewire + Alpine sortable).
- Duplicate guard (US-10): block + name the existing question.
- Preview flow (steps through as patient, branches included — reuses `AssessmentService::flowFor`).
- "Create category" + publish path (US-12).
- Route: `GET /admin/question-bank/categories/{slug?}` → `role:admin`.

### Publishing/review (US-13..15)
- Status control on both screens; clinical items route to an **In review** queue.
- Side-by-side old-vs-new version diff before approve.
- Approver gate: a `health_coach`/clinical role check — confirm with Captain Zan whether Dr Anke is an
  existing admin user or needs a dedicated `clinical_approver` role/permission.

## 5. Patient flow changes (minimal)

`app/Livewire/Patient/BookAppointment.php`:
- Replace `config("assessment-questions.{$slug}")` with `AssessmentService::flowFor($slug)`.
- On assessment start, create the `Assessment` with `category_id` + `manifest_version` pinned.
- On submit, write `assessment_answers` rows (keep writing the legacy blob too during transition for
  safety, behind a flag, until verified).
- Keep `show_if`/visibility semantics identical — driven by `branch_rules` now.

## 6. Doctor view changes (US-16..17)
- `Doctor\ConsultationScreen` / answer partial: group by section, show per-answer timestamp, and a
  "Needs attention" panel (flagged answers + all free-text + "not sure"). Reads `assessment_answers`,
  falls back to the blob for legacy rows.

## 7. Migration / cutover strategy
1. Build tables + services + admin screens behind the existing journey (no user-facing change yet).
2. Seed the bank from `config/assessment-questions.php` (a one-off `php artisan questionbank:import-config`
   command) as **Draft** — including a first CORE block proposal.
3. Import Craig's Drive package (334 records) as **Draft** when received, reconciling against seeded set.
4. Dr Anke reviews → CORE + Weight-Management questions go **Live**.
5. Flip Weight-Management's `BookAppointment` to the service; verify; then retire the config entry for it.
6. Load remaining categories through the admin portal (US-12 — the acceptance test).

## 8. Decisions (locked — Captain Zan, 2026-11-03)
- **Who manages the bank:** the **main system admin (Craig) only.** The question bank is admin-only;
  regular doctors have **no access** (they only view answers during consults). **No separate
  clinical-approver role** — Craig is the single publish gate. A change reason + changelog is recorded
  on every publish for audit. → remove any clinical-approver/Dr-Anke gating from the design.
- **Answer storage:** per-row encrypted `assessment_answers` (confirmed).
- **Sequencing:** question bank first, charge-at-booking second (confirmed).
- **Drive package** not yet in hand: schema accepts it; import is a later task (Phase 7), not a blocker.

## 9. Test plan
- Unit: `AssessmentService::flowFor` order (CORE→category→free-text), branch visibility parity with the
  old 42 branches, manifest-version pinning, per-answer version/timestamp + flag computation.
- Feature: admin cannot delete (only retire); duplicate-add blocked; clinical publish requires approver;
  mid-flow edit doesn't change an in-progress assessment; new-category-via-portal end-to-end (US-12).
- Regression: existing `BookingAssessmentCheckboxTest` + booking tests stay green.
