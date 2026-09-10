# Design — SPAR National Patient Identity + Multi-Store Medication View

**Status:** Draft for review · **Author:** Naz · **Date:** 2026-09-11
**Requirements:** `requirements.md` (this folder)

---

## 1. The core idea

A patient is identified **nationally by Profile Code**, not per-store. Because Profile Code
and phone are encrypted at rest, we add a **blind index** — a keyed one-way hash — so we can
match on them in SQL without decrypting and without leaking PHI. Pharmacy stops being part of
patient identity and instead lives on each prescription/dispense.

```
BEFORE:  patient identity = (profile_code, dependent_code, spar_pharmacy_id)   ← per store
AFTER:   patient identity = (profile_code_hash, dependent_code)                ← national
         each journey / dispense carries its own spar_pharmacy_id              ← multi-store
```

## 2. Blind index (keyed HMAC)

- New nullable columns on `spar_patients`: `profile_code_hash`, `cellphone_hash`
  (CHAR(64), indexed). Value = `hash_hmac('sha256', normalise($plain), $key)`.
- `$key` = `config('spar.blind_index_key')` (env `SPAR_BLIND_INDEX_KEY`), separate from
  `APP_KEY`, rotatable (rotation = recompute all hashes via the backfill command).
- Normalisation before hashing: profile code trimmed/upper; phone → E.164 digits only.
  Deterministic, so the same input always yields the same hash → matchable.
- Hashes are **not reversible** and carry no PHI — safe to index and query. Plaintext stays
  encrypted via the existing `EncryptsSensitiveFields` trait (NFR-1 preserved).
- Maintained automatically: a model hook on `SparPatient` recomputes the hash whenever
  `profile_code` / `cellphone` is set, so app-created patients get indexes too (not just import).

## 3. Import matching (the de-dup)

`SparImportService::processPatientGroup()` changes its lookup:

1. Compute `profile_code_hash` for the row's profile code.
2. `SparPatient::where('profile_code_hash', $hash)->where('dependent_code', $dep)->first()`.
   - **Found** → this is the existing patient. Attach the new journey/dispense. Update the
     patient's *home* pharmacy to the most recent store (informational only).
   - **Not found** → create the patient (now WITHOUT pharmacy in the identity key; set home
     pharmacy = this store).
3. **Phone verification (FR-3):** if found and the row carries a phone whose hash differs from
   the stored `cellphone_hash` AND the stored phone is non-empty → set `needs_identity_review`
   + write an import log entry. Do not overwrite the stored phone. If the stored phone is empty,
   fill it (and its hash). Matching phone → confidence, no action.

Grouping key in `importFile()` stays `profile|dependent|store` at the FILE level (a single
upload still groups a member's rows), but the DB-level identity resolution is now national.

## 4. Pharmacy on the prescription, not the patient (FR-4)

- `spar_journeys.spar_pharmacy_id` and `spar_dispense_records` already carry pharmacy — no
  schema change there. Good.
- `spar_patients.spar_pharmacy_id` is **repurposed** to "home / most-recent pharmacy"
  (nullable, informational). It is removed from the identity key. Staff-scope queries switch
  from "patients where spar_pharmacy_id = mine" to "patients who have a journey/dispense at a
  pharmacy in my scope" (see §6).

## 5. Multi-store mobi view (FR-5)

- `MyMedsTracker` / `MyMedsHistory` already roll up journeys across household members. Add the
  pharmacy name to each journey/dispense card: `journey.pharmacy.name` →
  **"Collected at: SPAR Pharmacy — Knysna"**. Pure view change; relation already present.
- Household roll-up itself now correct automatically: because identity is national, a family
  member who filled at another store is the SAME patient row, so their meds already appear.

## 6. Scope integrity (FR-6) — the careful bit

Today `SparPatient::scopeVisibleToCurrentActor()` filters patients by their own
`spar_pharmacy_id`. Once that column is just "home pharmacy", a store must see a national
patient **if that patient has any journey/dispense at the store**. New scope logic:

- Super-admin: all (unchanged).
- Group-admin: patients with a journey/dispense at any pharmacy in the group.
- Pharmacy actor: patients with a journey/dispense at their store.

Implemented via a `whereHas('journeys', fn => in-scope pharmacy ids)` (+ dispenses) rather than
the flat `spar_pharmacy_id` column. Keeps AC per Phase 7 intact; add tests to prove a national
patient is visible to BOTH stores they use, and invisible to a third.

## 7. Duplicate handling for existing data (NFR-3)

- Backfill command computes `profile_code_hash` / `cellphone_hash` for every existing patient.
- After backfill, detect rows sharing `(profile_code_hash, dependent_code)` across pharmacies
  → **report** them (do not auto-merge; merging medical records is a reviewed action). A later
  `--merge` option can fold duplicates: repoint journeys/dispenses/orders/consents to the
  surviving row, then soft-delete the losers, audit-logged.

## 8. Risks / decisions to confirm

- **Adult-dependant visibility** (already flagged): a national account makes the household view
  richer — same compliance sign-off applies.
- **Key management:** `SPAR_BLIND_INDEX_KEY` must be set per environment and backed up; losing it
  means re-backfilling from decrypted values (possible, since we still hold ciphertext).
- **Phone-mismatch policy:** confirm the desired behaviour — review-flag (recommended) vs reject
  vs auto-update. Design assumes review-flag.
- **Merge policy:** confirm whether we ever auto-merge duplicates or always human-review.
