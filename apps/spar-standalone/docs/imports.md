# SPAR Data Imports — runbook

SPAR chronic-medication data arrives in **two files** that must be linked:

| File | Format | Carries | Notes |
|---|---|---|---|
| **Sales Extract** | pipe-delimited CSV (`.csv`) | dispense transactions — meds, dates, repeats, schedule, value; **Profile Code + Dependent Code**; NO name/contact | pseudonymised on Profile Code |
| **Drug Usage report** | Excel (`.xlsx`) | patient **identity + contact** — surname, firstname, mobile, address, medical aid; **Profile Code + Dependent Code** | report preamble rows above the header |

The two are joined on **`Profile Code` + `Dependent Code`**. SPAR is adding those two
columns to the Drug Usage report so patient identity can be linked to dispense
history. Dependent codes: `00` principal, `01` spouse, `02+` children.

## Two ways in (both supported)

### 1. Manual upload (available now)
Admin → **SPAR Data Imports**. Upload the Sales Extract (required) and, optionally,
the Drug Usage report. When both are supplied, patients are created **with** contact
details; sales-only still imports dispense history (identity captured later by a
pharmacist).

Files containing patient data are **deleted from the server immediately after
processing** (POPIA).

### 2. Automated FTP drop (`spar:ingest-drop`)
SPAR / their vendor drop the two files into a directory exposed over FTP. A scheduled
command pairs and imports them via the **same** import service as the manual upload.

Config (`config/spar.php` → `import`, all env-overridable):

| Key | Env | Default | Meaning |
|---|---|---|---|
| `drop_disk` | `SPAR_IMPORT_DROP_DISK` | `local` | filesystem disk the FTP account writes to |
| `drop_path` | `SPAR_IMPORT_DROP_PATH` | `spar-drop` | relative directory on that disk |
| `sales_match` | `SPAR_IMPORT_SALES_MATCH` | `salesextract` | filename substring identifying the sales file |
| `drug_usage_match` | `SPAR_IMPORT_DRUG_MATCH` | `drug usage` | filename substring identifying the report |
| `archive_processed` | `SPAR_IMPORT_ARCHIVE` | `false` | keep processed files (non-prod only; PHI otherwise deleted) |

Run manually:
```
php artisan spar:ingest-drop            # uses configured drop dir
php artisan spar:ingest-drop --path="D:\some\drop\dir"
```

Schedule (add to the scheduler once SPAR confirm cadence):
```php
$schedule->command('spar:ingest-drop')->hourly()->withoutOverlapping();
```

> **Infra note:** the FTP/SFTP account itself is provisioned at deploy — its home
> directory IS the drop directory. The app never speaks FTP; it consumes the local
> directory the transport delivers into. This keeps the transport swappable
> (FTP → SFTP → S3 drop → mounted share) with zero app changes.

## Behaviour details
- **One patient per household member.** Rows are grouped by
  `profile_code + dependent_code + store`, so the principal and each dependant each
  become their own `SparPatient` (dependants roll up under the principal in the
  patient tracker view — see the roll-up note in the design spec).
- **Identity never clobbered.** Imported identity only fills **empty** fields, so a
  pharmacist-captured contact always wins over a re-import.
- **Dependants are not given the principal's identity.** A dependant with no own row
  in the Drug Usage report stays contactless (they're reachable via the principal).
- **Hard requirement:** the Drug Usage file MUST contain `Profile Code` +
  `Dependent Code` columns, else the paired import is rejected with a clear error.

## Demo files
`C:\Users\zande\Documents\Zapmed\Spar\demo\` — `Demo SalesExtract072026.csv` +
`Demo Drug Usage 01 Sept 2026.xlsx`. Three households (NAIDOO 990001 ×3, VAN WYK
990002, MBEKI 990003 ×2) that reconcile on profile+dependent. Safe to hand to the
client to test the whole system.

## Tests
- `tests/Feature/SparDualFileImportTest.php` — paired import links identity ↔ history.
- `tests/Feature/SparDropIngestTest.php` — FTP-drop ingestion + skip-when-no-sales.


## National patient identity (2026-09-11)

Patients are identified **nationally by Profile Code**, not per store. On import a patient is
matched via a blind index (keyed hash of the encrypted profile code) independent of the
uploading pharmacy, so filling at a second SPAR store **attaches** to the existing patient
rather than creating a duplicate. Phone number is a **secondary confirmation** — a profile
match with a conflicting phone attaches the dispense but flags the patient for review (never a
silent merge). Each medication on the patient's mobi page is labelled **"Collected at:
<pharmacy>"** so a multi-store patient sees where each script came from.

Ops commands:
- `php artisan spar:backfill-blind-index [--dry-run]` — populate hashes + report duplicates.
- `php artisan spar:merge-duplicates [--dry-run]` — collapse unambiguous cross-store duplicates.

Set `SPAR_BLIND_INDEX_KEY` per environment (and back it up).
