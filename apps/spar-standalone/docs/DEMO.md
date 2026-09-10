# SPAR Standalone — Demo Runbook

Everything needed to demo the full system end-to-end.

## 1. Reset to a clean full-system state
```
php artisan migrate:fresh --seed
```
This wipes the demo DB and seeds:
- **4-tier logins** (see below)
- **1 group** (SPAR Western Cape) with **2 pharmacies** (Plettenberg Bay, Knysna)
- **The real demo import** (`Demo SalesExtract072026.csv` + `Demo Drug Usage 01 Sept 2026.xlsx`)
  → 6 patients across 3 households (NAIDOO ×3, VAN WYK, MBEKI ×2), each with identity + dispense history
- **Lifecycle states** so every screen has content: consent grants, orders in all
  four statuses (requested/preparing/ready/completed), an overdue dispense, an
  upcoming dispense, and a renewal-due journey.

## 2. Start the app
```
php artisan serve --port=8000
```
Then open http://127.0.0.1:8000

## 3. Staff / admin logins (all password: `Testing123!`)
| Tier | Email | What they see |
|---|---|---|
| Super admin | `superadmin@sparmeds.test` | everything — all groups, all pharmacies, group + staff management |
| Group admin | `groupadmin@sparmeds.test` | only SPAR Western Cape group + its pharmacies |
| Pharmacy admin | `pharmadmin@sparmeds.test` | only Plettenberg Bay; can manage its staff |
| Pharmacy staff | `staff@sparmeds.test` | only Plettenberg Bay; order queue + patients (no management) |

`admin@sparmeds.test` also exists (legacy super-admin alias).

## 4. Screens to show
- **Admin dashboard** (`/admin/spar`) — KPI tiles, recent orders, renewals due, per-pharmacy summary
- **Statistics** (`/admin/spar/stats`) — scope-filtered per role (super = platform, group = group, pharmacy = store)
- **Pharmacies / Groups / Staff** (`/admin/spar/pharmacies`, `/groups`, `/admin/staff`) — hierarchy management, scope-enforced
- **Data Imports** (`/admin/spar/imports`) — upload the two demo files live (Sales CSV + Drug Usage xlsx)
- **Consent** (`/admin/spar/consent`) — consent status + full audit trail
- **Exceptions** (`/admin/spar/exceptions`) — overdue dispenses, missing renewals, unresponsive patients
- **Pharmacy staff dashboard** (`/spar/dashboard`) — order queue (prepare → ready → complete)
- **Pharmacist capture** (`/spar/capture`) — onboard an awaiting-contact patient

## 5. Patient phone app (no-login tracker)
Patients have NO account — they open a signed link. To demo the patient side:
```
php artisan spar:dev-tracker-link --host=http://127.0.0.1:8000
```
Prints a signed link (opens the "My Meds" tracker: consent gate → dashboard → history,
with the household roll-up: a principal sees their own + dependants' meds).

For a richer single-patient journey (full 6-month script + renewal card):
```
php artisan spar:seed-demo-prescription --renewal --host=http://127.0.0.1:8000
```
> OTP re-verify is ON by default — the code is written to `storage/logs/laravel.log`
> (log SMS driver). Set `SPAR_LINK_REQUIRE_OTP=false` in `.env` to skip it while presenting.

## 6. FTP-drop ingestion (automated import)
```
php artisan spar:ingest-drop --path="<drop dir>"
```
Drop the two files into the configured directory and this pairs + imports them —
same result as the manual upload. See `docs/imports.md`.

## Notes
- All messaging (SMS/WhatsApp) runs on the **log driver** — nothing is sent externally;
  payloads are written to the log so the flow is fully demoable without provider creds.
- `NullTelehealthBridge` is bound, so renewal offers "see your own doctor" only (no ZapMed
  online-consult CTA) — that's the standalone behaviour.
