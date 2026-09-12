# Task 10 — Contro Import Cutover Runbook

**Author:** Naz · **Date:** 2026-09-13
**Audience:** whoever runs the Contro → ZapMed data migration (probably you, possibly at 2am).
**Reflects the code as actually built** (Tasks 1–9). Command names, tables, and quarantine reasons below
are real and current.

> Golden rule, enforced in code: the import is **read-only against Contro**, **idempotent**, and has
> **zero side-effects** — it never sends email/SMS/WhatsApp, never charges/refunds, never submits to
> RxHub, never fires outbound webhooks. You can run it as many times as you like.

---

## 0. Prerequisites (BLOCKERS — from Craig)

The pipeline is built and fully tested against fixtures, but a **live** run needs:

1. **Contro DB dump** — the only source of doctor/clinical notes + any DB-only fields (the API exposes
   none). Expected in a few days. Until it lands, clinical-notes import stays out of scope (blueprint §1).
2. **Contro production HTTPS base URL** (Swagger lists `http://` — do not use that).
3. **Contro service-account credentials** (email + password for `POST /api/crm/auth/login`).
4. Confirm the **login response token field** shape (code accepts `token`/`accessToken`/`access_token`/
   `data.token`; verify which one) and whether **JWE response encryption** will be enabled.
5. **PayFast** confirmed as the owned-system gateway (done — Contro `peach` maps to `payfast`).

Set these in the environment before running:

```
CONTRO_API_BASE_URL=https://<contro-prod-host>
CONTRO_API_EMAIL=<service account>
CONTRO_API_PASSWORD=<service account password>
CONTRO_ENVELOPE_ENABLED=false   # flip to true only if Contro enables JWE (needs keypair)
```

## 1. What the import consists of (built + tested)

| Step | Command | What it does |
|---|---|---|
| Seed state machine | `php artisan db:seed --class=OrderStatusTransitionSeeder` | Loads the 25-status / transition rules + RxHub map as data (idempotent). |
| **Extract + Load** | `php artisan contro:pull [entity] [--backfill]` | Pulls Contro rows into `upstream_ingested_rows` (raw staging). Watermarked, paged, idempotent. NO canonical writes. |
| **Reconcile** | `php artisan contro:reconcile` | Maps staging → canonical tables (idempotent upsert on `upstream_id`, dependency order, stub principals, quarantine). ZERO side-effects. |
| **Verify** | `php artisan contro:parity` | Per-entity staged vs reconciled vs quarantined; fails (non-zero exit) if anything is unaccounted. |

Entities (dependency order, handled automatically): patients → products → coupons → orders →
order_status_history → payments → prescriptions.

## 2. Cutover procedure

### Phase A — Dry run on staging (do this the moment creds arrive)
1. Point env at Contro **prod** (read-only creds) on the **staging** ZapMed DB.
2. `php artisan migrate` (applies the Contro schema: crosswalk, orders, catalog, payments, patient/rx
   extensions, quarantine).
3. `php artisan db:seed --class=OrderStatusTransitionSeeder`.
4. **Backfill:** `php artisan contro:pull --backfill` (all entities, from the beginning).
5. **Reconcile:** `php artisan contro:reconcile`.
6. **Verify:** `php artisan contro:parity`. Expect `Parity clean`. If it reports **unaccounted** rows,
   stop and investigate before going further.
7. Review the **quarantine** queue with Craig/Dave:
   `select entity_set, reason, count(*) from import_quarantine where status='open' group by 1,2;`
   Reason codes you may see: `unknown_status`, `unmatched_order`, `unresolved_patient`, `bad_payload`.
8. Spot-check a handful of records against Contro (order totals in cents, patient by userHash, a
   prescription's medication lines). Sign off the dry run with Craig.

### Phase B — Incremental deltas (while Contro stays live)
- Run `php artisan contro:pull` (no `--backfill`) on a schedule; it uses the stored watermark per entity
  and only pulls changed rows. Follow each pull with `php artisan contro:reconcile`.
- Deltas are safe and idempotent — re-running never duplicates.

### Phase C — Cutover (the irreversible bit — do WITH Craig)
1. Announce a maintenance window.
2. **Freeze Contro writes** (Craig/Dave stop the live system accepting new writes).
3. Final delta: `php artisan contro:pull` then `php artisan contro:reconcile`.
4. Final verify: `php artisan contro:parity` → must be **clean**; quarantine reviewed and accepted.
5. Flip ZapMed to **system of record** (point the app/users at ZapMed).
6. Keep Contro **read-only** as a fallback for a defined window (e.g. 30 days) before decommissioning.

### Rollback (if Phase C goes wrong)
- Because the import is read-only against Contro and Contro stays read-only-available, rollback =
  point users back at Contro. No ZapMed data is pushed to Contro, so nothing upstream is corrupted.
- On the ZapMed side, imported rows are all tagged `upstream_source='contro'` and can be identified/
  removed per entity if a re-import is needed:
  `delete from <table> where upstream_source='contro';` (then re-run pull+reconcile).

## 3. Safety properties (why this is low-risk)

- **Read-only upstream:** we only ever GET from Contro. Craig's live data is never written by us.
- **Idempotent:** every canonical row keyed on `(upstream_source, upstream_id)`; re-runs upsert, never
  duplicate. Proven by `ControEndToEndImportTest::test_pipeline_is_idempotent_when_run_twice`.
- **Zero side-effects:** reconcile uses `withoutEvents()` throughout — no observers, mail, jobs, payments,
  pharmacy calls, or webhooks fire. Proven by `test_full_import_fires_no_side_effects`.
- **Quarantine, never drop:** anomalies are flagged in `import_quarantine` with the raw payload + reason.
  Nothing is silently discarded; `contro:parity` fails if anything is unaccounted.
- **Money integrity:** Contro rands (doubles) → minor units (cents), banker's rounding.
- **Identity integrity:** patients keyed by `userHash`; referenced-but-unsynced patients/doctors are
  stubbed (`is_active=false`, not login-capable) then filled when their own record syncs. Never guess
  identity from name/email.

## 4. Known limitations / still-open

- **Clinical notes:** the Contro **API** exposes none. If the DB dump contains doctor notes/reviews,
  a follow-up importer maps them into `clinical_review`/`clinical_note` (not built — awaiting the dump).
- **RxHub outbound:** live pharmacy submit is not built (needs RxHub API docs). Not required for the
  historical import.
- **Watermark field casing** is per-entity and deliberately inconsistent (Contro's, not ours) — encoded
  verbatim in `config/contro.php`. Do not "normalise" it.
- Pre-existing unrelated test failure `AuthenticationTest::navigation menu can be rendered` (`/dashboard`
  302) is **not** part of this work and does not affect the import.

## 5. Quick reference

```bash
# one-time
php artisan migrate
php artisan db:seed --class=OrderStatusTransitionSeeder

# backfill (all entities from the start)
php artisan contro:pull --backfill
php artisan contro:reconcile
php artisan contro:parity          # must be clean

# ongoing delta
php artisan contro:pull            # watermarked
php artisan contro:reconcile
php artisan contro:parity
```
