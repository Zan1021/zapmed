# SPAR Standalone

A slim Laravel 12 + Livewire 3 application that runs the **SPAR chronic-medication module** as an
independent product, with **no telehealth** and **no dependency on ZapMed**. It consumes the shared
`zapmed/spar-core` domain package; the same package also powers SPAR *inside* ZapMed (integrated mode).

> One domain implementation, two hosts. This app is the standalone host.
> Spec set: `../../specs/spar-standalone/{spec,design,tasks}.md`.

## What this host binds

| Contract (`Zapmed\SparCore\Contracts`) | Standalone binding | Effect |
|---|---|---|
| `SparIdentityProvider` | `App\Spar\StandaloneSparIdentityProvider` | Identity from `pharmacy_users` (staff) + SPAR-owned patient contact records. No ZapMed `User`. |
| `TelehealthBridge` | `Zapmed\SparCore\Services\Telehealth\NullTelehealthBridge` | Renewal offers only "your own doctor" — no online consult, no handoff, no return path. |
| `AuditLogger` | `App\Spar\LogAuditLogger` | POPIA events to the `spar_audit` log channel. |
| `OtpSender` | `App\Spar\StandaloneOtpSender` | Patient link re-verification OTP (pilot: log-only until a provider is provisioned). |
| `MessagingChannel` (`sms`) | `App\Spar\StandaloneSmsChannel` | SMS fallback (pilot: log-only). |

`host_mode` is fixed to `standalone` (see `.env` / `config/spar.php`), `user_model` is `null`.

## Actors

- **Admin** — logs in (`pharmacy_users`, role `admin`), sees all pharmacies/imports/exceptions/reporting/consent.
- **Pharmacy staff** — logs in (`pharmacy_users`, role `pharmacy_staff`), store-scoped (`spar.scope` + `spar.timeout`).
- **Patient** — **no login**. Reaches "My Meds" via a signed, expiring tokenised link + optional OTP
  re-verify. Consent is a hard gate before any PHI is shown. Dependants get no link; the primary
  member's view rolls up the whole Profile Code.

## Local setup

```bash
# from apps/spar-standalone
composer install                 # resolves spar-core + platform-support via path repos (symlinked)
cp .env.example .env             # if not already present
php artisan key:generate
php artisan migrate --seed       # SQLite locally; Postgres 16 in staging/prod
php artisan serve
```

### Seeded demo accounts (local only)

| Role | Email | Password |
|---|---|---|
| Pharmacy staff | `staff@sparmeds.test` | `Testing123!` |
| Admin | `admin@sparmeds.test` | `Testing123!` |

Plus a demo pharmacy and two patients (`DEMO-1001` awaiting contact, `DEMO-1002` fully consented).

## Verifying the standalone guarantees

```bash
php artisan spar:verify-schema   # AC-1: no telehealth tables; AC-4: NullTelehealthBridge; bindings OK
php artisan test                 # StandaloneSmokeTest — every screen + AC-1/AC-4 assertions
```

`spar:verify-schema` asserts the schema contains only `pharmacy_users` + the seven `spar_*` tables +
framework tables — **zero** telehealth tables — and that all SPAR contracts resolve to the standalone
/ null implementations.

## Onboarding modes (config-gated, mutually exclusive)

Set `SPAR_ONBOARDING_MODE` (`config('spar.onboarding_mode')`):

- **`import`** (Mode A) — the export file carries patient contact columns; import populates identity.
- **`pharmacist_capture`** (Mode B) — the export seeds medication history only; a pharmacist captures
  name/contact/consent, matched to history by **Profile Code**.

Records without any contact channel land in `awaiting_contact` and are never sent a link.

## Messaging

Delivery goes through `MessagingChannel` in the configured priority order (`spar.channels`). WhatsApp
is the intended primary but is **deferred** (no provider yet); the pilot runs on in-app + email +
interim SMS, with SMS as the permanent fallback. Adding `WhatsAppChannel` later is a new binding, not
a rewrite.

## Deployment

Standalone deploys as its **own Forge site with its own PostgreSQL 16 database** — no shared schema
with ZapMed (spec NFR-4/NFR-5). Provisioning is a gated, high-risk step; see `tasks.md` §5.5.
