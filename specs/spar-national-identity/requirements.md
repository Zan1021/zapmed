# SPAR — National Patient Identity + Multi-Store Medication View

**Status:** Draft for review
**Author:** Naz (Team Lead)
**Date:** 2026-09-11
**Related:** `specs/spar-standalone/{design,spec,tasks}.md`, `apps/spar-standalone/docs/imports.md`

---

## 1. Problem

A SPAR patient is **one person nationally**, identified by their SPAR **Profile Code**
(+ Dependent Code for the specific household member). But today the import keys a
patient on `(profile_code, dependent_code, spar_pharmacy_id)` — pharmacy is PART of
the identity. So if the same patient fills a script at a **second SPAR store**, the
importer creates a **duplicate patient record**, and their medication history is split
across two accounts. The patient's mobi tracker then shows an incomplete picture.

Root cause of the earlier-flagged "cross-pharmacy roll-up limitation": it's not just a
display bug — it starts at import. Fix identity at the source and the household roll-up,
journeys, and history all become correct automatically.

Complication: `profile_code`, `cellphone`, `email` are **encrypted at rest**, so they
cannot be matched with a plain SQL `WHERE`. We need a way to match across stores WITHOUT
weakening encryption or leaking PHI.

## 2. Goals

- A patient exists **once** across all SPAR stores, keyed on Profile Code (+ Dependent Code).
- On import (manual upload OR FTP drop), an existing patient is **recognised** and the new
  prescription/dispense is **attached to their existing account** — never a duplicate.
- **Phone number** is used as a **secondary confirmation** signal, not the primary key.
- A patient's medications from **multiple pharmacies** appear together on their mobi page,
  **each med labelled with the pharmacy it came from**.
- Matching works while keeping Profile Code / phone **encrypted at rest** (POPIA/NFR-1).

## 3. Non-Goals

- Merging historical duplicates created before this change is a one-off migration concern,
  handled in tasks (not an ongoing feature).
- Changing SPAR's file formats — we consume Profile Code + Dependent Code as delivered.
- Cross-*group* identity policy questions (a national patient spanning pharmacy groups) —
  out of scope; identity is national, group is an admin/reporting scope only.

## 4. Functional Requirements

- **FR-1 — Blind-indexed identity.** Store a deterministic, non-reversible **blind index**
  (keyed HMAC-SHA256) of `profile_code` (and one for `cellphone`) in dedicated columns so
  they are matchable in SQL without decryption.
- **FR-2 — National de-dup on import.** Import matches an existing patient by
  `profile_code_hash` (+ `dependent_code`) FIRST, regardless of uploading pharmacy. Only
  creates a new patient when no match exists.
- **FR-3 — Phone as secondary verification.** When the profile+dependent matches but the
  incoming phone differs materially from the stored phone, DO NOT silently merge — attach
  the dispense but **flag the patient for review** (import log + review status). When phone
  matches, it raises match confidence (and can fill an empty stored phone).
- **FR-4 — Pharmacy belongs to the prescription, not the patient.** A patient is
  pharmacy-independent; each **journey / dispense** records its originating pharmacy.
  `spar_patients.spar_pharmacy_id` becomes the patient's *home/most-recent* pharmacy
  (nullable, informational), NOT part of identity.
- **FR-5 — Multi-store mobi view.** The patient tracker shows every medication/journey with
  a **"Collected at: <pharmacy name>"** label, so a patient who fills at more than one SPAR
  sees which store each med came from. Household roll-up (principal + dependants) unchanged.
- **FR-6 — Scope integrity preserved.** Staff scope (pharmacy/group/super) is unchanged: a
  pharmacy actor still only sees patients/journeys tied to their store; a national patient is
  visible to a store only through the journeys/dispenses at that store.

## 5. Non-Functional Requirements

- **NFR-1 — Encryption at rest preserved.** Blind-index columns are keyed hashes, never
  plaintext; the HMAC key lives in app config/secrets, rotatable. No PHI in the hash output.
- **NFR-2 — No regressions.** Existing SPAR suites (standalone + ZapMed) stay green; the
  dual-file import behaviour is preserved, only the identity key changes.
- **NFR-3 — Backfill safe.** Existing patient rows get their blind indexes populated by a
  backfill command; duplicates already in the DB are detected + reported (merge is reviewed).

## 6. Acceptance Criteria

- **AC-1** Importing the same Profile Code from two different pharmacies results in **ONE**
  patient with journeys/dispenses from **both** stores (no duplicate patient row).
- **AC-2** The mobi tracker shows each medication with its originating pharmacy name; a
  two-store patient sees both stores' meds on one page.
- **AC-3** A profile match with a materially different phone attaches the dispense but sets
  a **review flag** (surfaced on the admin exceptions/consent screen), not a silent merge.
- **AC-4** `profile_code` and `cellphone` remain ciphertext at rest; matching uses the hash
  columns only. Grep confirms no plaintext profile/phone in queries.
- **AC-5** Backfill populates hashes for all existing patients and produces a duplicate report.
- **AC-6** All existing SPAR tests still pass; new tests cover AC-1..AC-5.
