# SPAR National Identity + Multi-Store View — Task List

**Status:** ✅ IMPLEMENTED (2026-09-11) — Phases 0–6 complete. Standalone suite 65 pass,
ZapMed SPAR subset 63 pass. AC-4 grep gate clean (no plaintext profile/phone in queries).
**Requirements:** `requirements.md` · **Design:** `design.md`
**Strategy:** Extract-in-place, tests green at every phase. Identity change is the risky
part — do it behind the blind index with a backfill, prove no regressions, THEN switch the
import lookup. The mobi pharmacy label (Phase 5) is independent + low-risk — can ship first
for the demo.

Legend: `[ ]` todo · `[x]` done · est S/M/L · ⚠ = risk/verify point

---

## Phase 0 — Decisions & safety net — RESOLVED (2026-09-11)
- [x] 0.1 Phone-mismatch policy on a profile match: **review-flag** (Captain Zan: "go with recommended").
- [x] 0.2 Duplicate-merge policy: **automated** (Captain Zan: "must be automated"). SAFETY VALVE
      (Naz): auto-merge ONLY on an unambiguous match — same `profile_code_hash` + `dependent_code`
      AND phone agrees or one side blank. A CONFLICTING phone does NOT auto-merge → drops to the
      0.1 review-flag. Prevents silently fusing two people's medical histories on a shaky match.
- [x] 0.3 Repurpose `spar_patients.spar_pharmacy_id` → nullable "home/most-recent pharmacy" (Captain Zan: "proceed").
- [x] 0.4 `SPAR_BLIND_INDEX_KEY` provisioning + per-env backup (Captain Zan: "yes").

## Phase 1 — Blind index foundation (no behaviour change yet)
- [ ] 1.1 Migration: add `profile_code_hash`, `cellphone_hash` (CHAR(64), nullable, indexed) to
      `spar_patients`, plus `needs_identity_review` (bool) + `identity_review_reason` (nullable). — S
- [ ] 1.2 Config: `spar.blind_index_key` (env `SPAR_BLIND_INDEX_KEY`); helper
      `SparPatient::blindIndex($plain, $type)` (normalise → HMAC-SHA256). — S
- [ ] 1.3 Model hooks on `SparPatient`: recompute hashes on save when profile_code/cellphone set.
      Keep plaintext encrypted (EncryptsSensitiveFields unchanged). — S
- [ ] 1.4 Backfill command `spar:backfill-blind-index` (`--dry-run`): populate hashes for all
      existing patients from decrypted values. — M ⚠ NFR-3
- [ ] 1.5 Tests: hash is deterministic + non-reversible; plaintext stays ciphertext at rest;
      backfill populates all rows. GATE: full SPAR suites still green. — S ⚠ AC-4

## Phase 2 — National de-dup on import
- [ ] 2.1 `SparImportService::processPatientGroup()`: resolve patient by
      `(profile_code_hash, dependent_code)` FIRST, independent of pharmacy. Create only when no
      match. Set/refresh `home` pharmacy. — M ⚠ FR-2/AC-1
- [ ] 2.2 Phone secondary verification: on profile match, compare `cellphone_hash`; fill if empty,
      flag `needs_identity_review` if materially different (+ import log). Per 0.1 decision. — M ⚠ FR-3/AC-3
- [ ] 2.3 Tests: same profile from TWO pharmacies → ONE patient with journeys/dispenses from both;
      phone-mismatch sets review flag; matching phone no-ops. GATE green. — M ⚠ AC-1/AC-3

## Phase 3 — Pharmacy off the patient identity, onto the prescription
- [ ] 3.1 Repurpose `spar_patients.spar_pharmacy_id` → nullable "home/most-recent pharmacy";
      remove it from any identity/uniqueness assumption. Journeys/dispenses already carry pharmacy. — S ⚠ FR-4
- [ ] 3.2 Rework `SparPatient::scopeVisibleToCurrentActor()` + `scopeForPharmacy` to scope by
      "has a journey/dispense at an in-scope pharmacy" (`whereHas`), not the flat column. — M ⚠ FR-6
- [ ] 3.3 Audit callers of `spar_pharmacy_id` on patients (PatientList, PharmacyDashboard, stats,
      capture, consents) — switch to the new scope. — M ⚠ FR-6
- [ ] 3.4 Tests: a national patient using stores A+B is visible to BOTH A and B staff, invisible to
      C; stats/lists respect it. GATE green. — M ⚠ AC-1/FR-6

## Phase 4 — Duplicate handling for existing data
- [ ] 4.1 Extend backfill: after hashing, detect rows sharing `(profile_code_hash, dependent_code)`
      across pharmacies → **report** (CSV/log), no auto-merge. — M ⚠ NFR-3/AC-5
- [ ] 4.2 (Optional, per 0.2) `--merge`: repoint journeys/dispenses/orders/consents to the survivor,
      soft-delete losers, audit-logged, `--dry-run` first. — L ⚠
- [ ] 4.3 Tests: duplicate report lists the right rows; merge (if built) preserves all history. — M

## Phase 5 — Multi-store mobi view (INDEPENDENT, low-risk — can ship first for demo)
- [ ] 5.1 `MyMedsTracker` + `MyMedsHistory` views: show **"Collected at: <pharmacy name>"** on each
      journey/dispense card. Relation already present — pure view change. — S ⚠ FR-5/AC-2
- [ ] 5.2 Test: a two-store patient's tracker shows both pharmacies labelled per med. — S ⚠ AC-2

## Phase 6 — Verify + document
- [ ] 6.1 Full suites green: standalone + ZapMed SPAR subset. Grep gate: no plaintext profile/phone
      in queries (AC-4). — S
- [ ] 6.2 Update `apps/spar-standalone/docs/imports.md` (national identity + phone verify + pharmacy
      labelling) and add a "Roll-up / national identity" section to `specs/spar-standalone/design.md`
      (this is where the earlier cross-pharmacy roll-up note finally lands). — S
- [ ] 6.3 Regenerate demo data so a household fills at TWO stores → demo shows the multi-store view. — S

---

## Sequencing notes
- **Phase 5 is independent** — the "Collected at" label needs no identity change and is safe to
  ship immediately for the demo. Everything else is the identity/data-model work.
- Phases 1→2→3 are the real change and must go in order behind green gates (1 adds the index
  harmlessly, 2 switches the import lookup, 3 fixes scope). Phase 4 (existing duplicates) can run
  anytime after Phase 1's backfill exists.
- Both hosts: the change lives in `spar-core` so ZapMed integrated + standalone get it together.
  Remember ZapMed host also needs its blind-index key set.

## Compliance flags
- Blind index is a keyed hash (no PHI); document the key-management + rotation runbook.
- Merging medical records (4.2) is a reviewed action — never silent — POPIA.
- Adult-dependant household visibility sign-off (carried from spar-standalone spec) still applies.


---

## IMPLEMENTATION OUTCOME (2026-09-11) — all phases DONE

- **Phase 1 (blind index):** migration `100014` adds `profile_code_hash`, `cellphone_hash`
  (CHAR64 indexed) + `needs_identity_review`/`identity_review_reason`. `SparPatient::blindIndex()`
  (HMAC-SHA256, key `spar.blind_index_key` / env `SPAR_BLIND_INDEX_KEY`), `booted()` saving hook keeps
  hashes in sync, plaintext stays encrypted. `spar:backfill-blind-index --dry-run`. Tests: `SparBlindIndexTest` (5).
- **Phase 2 (import de-dup):** `SparImportService::processPatientGroup` resolves by
  `(profile_code_hash, dependent_code)` independent of pharmacy; `verifyPhone()` flags review on a
  conflicting phone (no silent merge). **Bonus bug fixed:** `parseCSV` now strips the UTF-8 BOM (the
  real SPAR export has one — store names were silently blank). Tests: `SparNationalDedupTest` (2).
- **Phase 3 (pharmacy off identity + scope):** migration `100015` + create-migration edit make
  `spar_pharmacy_id` nullable and drop the old per-store unique. `scopeVisibleToCurrentActor` /
  `scopeForPharmacy` now scope by `whereHas` journeys/dispenses at in-scope pharmacies. Updated the
  three existing scope tests to seed a journey per patient. Tests: `SparNationalScopeTest` (4).
- **Phase 4 (auto-merge):** `SparDuplicateMerger` + `spar:merge-duplicates --dry-run`. Automated,
  with the agreed SAFETY VALVE — only unambiguous groups merge (phones agree/one blank); conflicting
  phones are skipped + flagged. Repoints journeys/dispenses/orders/consents, retires losers
  (`is_active=false` + `metadata.merged_into`), audit-logged. Tests: `SparMergeDuplicatesTest` (3).
- **Phase 5 (multi-store mobi view):** "Collected at: <pharmacy>" on each journey card
  (`my-meds-tracker`) and each history record (`my-meds-history`); relations eager-loaded.
  Tests: `SparMultiStoreViewTest` (1).
- **Phase 6 (verify + docs):** standalone 65 pass / ZapMed SPAR 63 pass; AC-4 grep gate clean;
  `apps/spar-standalone/docs/imports.md` updated; roll-up + national-identity note added to
  `specs/spar-standalone/design.md`.

### Deploy notes (hosts)
- Set `SPAR_BLIND_INDEX_KEY` per environment (staging/prod/standalone) + back it up.
- Run `php artisan migrate` on BOTH hosts (adds the hash columns). ZapMed host also needs
  `composer` already has phpspreadsheet (done). After deploy: `spar:backfill-blind-index` then
  `spar:merge-duplicates --dry-run` (review) → `spar:merge-duplicates`.
