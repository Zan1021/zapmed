# SPAR Chronic Medication — Standalone System Design

**Status:** Draft for review
**Author:** Naz (Team Lead)
**Date:** 2026-09
**Context:** ZapMed currently ships SPAR as an embedded module. Requirement (original + reaffirmed): SPAR must run **both** as part of ZapMed **and** as a fully standalone system. This document designs the standalone extraction.

---

## 1. Problem Statement

Today the SPAR Chronic Medication Module is welded into the ZapMed Laravel monolith. It shares the `User` table, the `UserRole` enum, auth, middleware, encryption, logging, SMS, and a foreign key into the telehealth `Prescription`. That is fine for "SPAR inside ZapMed", but it means SPAR cannot be deployed, sold, or operated independently — a SPAR pharmacy group cannot run it without also standing up the entire telehealth platform.

We need SPAR to be a self-contained product while preserving the integrated experience for ZapMed customers. One codebase should serve two deployment shapes.

## 2. Goals / Non-Goals

**Goals**
- SPAR runs as a standalone Laravel application with its own auth, DB, and deploy target — **zero** dependency on telehealth tables or code to boot and operate.
- SPAR continues to run inside ZapMed with the telehealth bridge (renewal → book a ZapMed doctor) fully intact.
- One shared source of truth for SPAR domain logic — no forked, drifting copies.
- POPIA-grade audit + encryption preserved in both shapes.

**Non-Goals**
- Rewriting SPAR business logic. Behaviour stays identical; only its packaging and boundaries change.
- Multi-tenant SaaS for many pharmacy groups in one install (future; the design should not *block* it, but it is out of scope now).
- Migrating existing ZapMed SPAR data out — the ZapMed install keeps its data.

## 3. Current Coupling Inventory (audited from code)

| SPAR element | Depends on (telehealth core) | Nature | Decoupling strategy |
|---|---|---|---|
| `SparPatient.user_id` | `User` (name, phone, email) | FK / identity | Introduce a SPAR-owned identity; `user_id` becomes an **optional link** |
| `SparPharmacy::staff()` | `User.spar_pharmacy_id` + `UserRole::PharmacyStaff` | FK + role | Standalone gets its own `pharmacy_users`; integrated keeps `User` link |
| `SparPrescriptionJourney.zapmed_prescription_id` | `Prescription` | FK (the real business bridge) | **Nullable, integration-only.** Null/absent in standalone |
| `MyMedsLogin` | shared `User` + `Auth::login()` | Auth | Auth via an **abstracted identity provider** (contract) |
| `role:` / `spar.scope` / `spar.timeout` middleware | `UserRole` enum, `User` | Auth/authz | Move to SPAR package; role source becomes pluggable |
| `EncryptsSensitiveFields` trait | shared trait | Infra | Move to shared core package |
| `spar_audit` log channel + `LogsSparActivity` | app logging config | Infra | Config shipped by SPAR package |
| `SmsService` (BulkSMS/Clickatell) | shared service | Infra | Behind an `SmsSender` contract |
| Renewal "consult a ZapMed doctor" reminder path | telehealth booking | Business bridge | Behind a `TelehealthBridge` contract; **no-op** in standalone |

**Key finding:** there is exactly **one** genuine business coupling — `zapmed_prescription_id` and the renewal-to-telehealth handoff. Everything else is *infrastructure* coupling (identity, auth, SMS, encryption, logging) that should be behind contracts regardless of this project.

## 4. Options Considered

### Option A — Fork into a separate repo/app
Copy SPAR code into a new Laravel app. Fast to stand up.
**Rejected:** guarantees drift. Two codebases, double maintenance, bug fixes done twice. Violates the "one source of truth" goal.

### Option B — Config-driven mode in the same monolith (`APP_MODE=standalone|integrated`)
Keep everything in the ZapMed repo, gate telehealth behind a flag.
**Rejected as the primary:** a standalone SPAR deploy still ships the entire telehealth codebase, migrations, and attack surface. "Standalone" would be a lie — it would just be ZapMed with features hidden. Does not satisfy "zero dependency to boot".

### Option C — Extract SPAR into a Composer package + thin host apps (CHOSEN)
Pull all SPAR domain code into an installable package (`zapmed/spar-core`). Two host applications consume it:
- **ZapMed** (existing app) installs the package → integrated mode, telehealth bridge wired.
- **SPAR Standalone** (new slim app) installs the package → standalone mode, bridge is a no-op.

Cross-cutting infra that both need (`EncryptsSensitiveFields`, base contracts) goes into a small `zapmed/platform-support` package.

**Why C:** one source of truth (the package), true independence (standalone app has no telehealth tables/code), and the integration bridge stays as a clean seam via a contract the host implements. More upfront work, but it is the only option that actually satisfies both requirements without drift.

## 5. Target Architecture (Option C)

```
packages/
  spar-core/                     # the product — models, services, Livewire, migrations, routes, middleware
    src/
      Models/            SparPatient, SparPharmacy, SparPrescriptionJourney, SparDispenseRecord,
                         SparOrder, SparImportBatch, SparImportLog
      Services/          SparImportService, SparOrderService, SparReminderService
      Livewire/          Spar\*, Admin\Spar*  (portal, pharmacy dashboard, admin screens)
      Http/Middleware/   EnsureSparPharmacyScope, SparSessionTimeout
      Contracts/         SparIdentity, SparIdentityProvider, SmsSender, TelehealthBridge, AuditLogger
      Support/           LogsSparActivity
      SparServiceProvider.php   # registers routes, migrations, config, views, publishes
    database/migrations/ # all spar_* tables (journey.zapmed_prescription_id is NULLABLE, no FK constraint at DB level)
    config/spar.php
    routes/spar.php
    resources/views/

apps/
  zapmed/           # existing app. Binds contracts to telehealth impls:
                    #   SparIdentityProvider -> uses User table
                    #   TelehealthBridge     -> real (renewal books a ZapMed doctor)
                    #   SmsSender/AuditLogger-> existing services
  spar-standalone/  # NEW slim Laravel app. Binds contracts to standalone impls:
                    #   SparIdentityProvider -> package's own pharmacy_users + spar patients identity
                    #   TelehealthBridge     -> NullTelehealthBridge (renewal = "see your own doctor" only)
                    #   SmsSender            -> BulkSMS (direct)
```

### 5.1 The contracts (the seams)

- **`SparIdentityProvider`** — resolves the current authenticated actor (pharmacy staff / patient) and issues OTP-login sessions. ZapMed binds it to the `User` table; standalone binds it to package-owned identity tables.
- **`TelehealthBridge`** — `renewalOptions()`, `startTelehealthRenewal($journey)`, `handoffContext($journey)` (name/contact + medications + pharmacy + signed token for a pre-filled ZapMed consult), `returnPrescription(...)` (new script → new SPAR journey). Real impl in ZapMed books a doctor and closes the loop; `NullTelehealthBridge` in standalone returns only "renew with your own doctor" and no online-consult option.
- **`MessagingChannel`** (formerly `SmsSender`) — `send($to, $templateOrMessage, $vars)`, `sendOtp(...)`. Provider-agnostic. Implementations: `WhatsAppChannel` (primary, template-based, rich buttons/graphics — future), `SmsChannel` (fallback, ZapMed `SmsService`/BulkSMS — available now), plus an in-app notification path. A priority resolver picks WhatsApp when available, falls back to SMS on failure/absence. Channel set + order is config-driven (`spar.channels`).
- **`AuditLogger`** — wraps the `spar_audit` channel so both hosts satisfy POPIA logging.

### 5.3 Messaging strategy (WhatsApp-primary, SMS-fallback)

- **Intended primary:** WhatsApp Business (approved templates, tappable buttons, branded graphics) — cheaper at volume, higher engagement, matches the brief's "WhatsApp consent → opt-in". **Not yet provisioned** (no provider/number as of 2026-09-03), so it is designed-for-but-deferred.
- **Pilot channels (available now):** in-app renewal/reminder cards + email + the existing `SmsService` as interim proactive sender.
- **Fallback (permanent):** SMS fires only when WhatsApp is unavailable or a send fails, so no chronic patient goes dark. WhatsApp-only is never the config because of the deliverability floor.
- **WhatsApp constraints to honour when added:** the 24-hour session window means all proactive sends (onboarding, monthly reminder, renewal-due, missed-renewal) must be **pre-approved Meta templates**. Provider choice (Meta Cloud API direct vs a BSP like Twilio/360dialog/Clickatell) is deferred; BSP favoured for pilot speed.
- Because everything routes through `MessagingChannel`, adding `WhatsAppChannel` later is a new binding, **not** a rewrite.

### 5.4 Renewal → ZapMed teleconsult funnel

The strategic core: when a 6-month recurring script ends, the patient needs a new prescription — and ZapMed can supply it online. Flow:
1. Journey reaches final dispense → `status = renewal_due` (already implemented) → optional pre-emptive nudge on the last repeat.
2. **Mobile web app:** a renewal card appears with a primary CTA **"Book an online consultation"** (integrated) or "See your own doctor" (standalone), driven by `TelehealthBridge`.
3. **Notification:** renewal message via `MessagingChannel` (WhatsApp when available, else SMS/email) with the same options + a deep link.
4. **Deep link into ZapMed:** `TelehealthBridge::handoffContext()` hands ZapMed the patient identity + the SPAR journey's medications + pharmacy + a signed token, so `zapmed.co.za` pre-fills a "chronic renewal" consultation. Patient pays (PayFast) and consults.
5. **Loop-closer (return path):** the ZapMed doctor issues the new script → `TelehealthBridge::returnPrescription()` writes a **new SPAR journey** back to the originating pharmacy → the 6-month cycle restarts. (Matches brief step 6.)
6. **Standalone:** `NullTelehealthBridge` — no online-consult CTA, no handoff, no return path; renewal offers only "see your own doctor". Satisfies AC-4.

⚠ **Return-path decision (Craig brief #4, still open):** for the pilot, integrated mode writes the renewal journey directly (internal handoff). A file/PDF-drop or API return path is a future option if SPAR needs the script in their own system too.

### 5.2 Data model changes

- `spar_prescription_journeys.zapmed_prescription_id` → **nullable, no DB-level FK** (application-level relation only when integrated). Standalone never populates it.
- `spar_patients.user_id` → **nullable**. Standalone stores patient identity (name, phone, encrypted profile_code/medical_aid) directly on/beside `spar_patients` via the identity provider, not on a telehealth `User`.
- New (standalone-only, shipped by package, unused by ZapMed): `pharmacy_users` for staff auth when there is no `User` table. ZapMed keeps using `User` + `UserRole::PharmacyStaff`.

### 5.3 Auth

- **Integrated (ZapMed):** unchanged — pharmacy staff are `User`s with `PharmacyStaff` role; patients log into "My Meds" via OTP against `User.phone`.
- **Standalone:** package ships its own guard. Staff authenticate against `pharmacy_users`; patients via OTP against SPAR-owned patient contact records. `spar.scope` + `spar.timeout` middleware move into the package and read role from the identity provider, not the ZapMed enum directly.

## 6. Risks & Mitigations

- **Behaviour drift during extraction.** → Move code, don't rewrite. Port the existing tests into the package first; they must stay green.
- **`EncryptsSensitiveFields` divergence.** → Extract once into `platform-support`; both hosts depend on it.
- **Migration collisions in ZapMed** (tables already exist). → Package migrations are idempotent / guarded; for ZapMed we mark them as already-run rather than re-creating.
- **Hidden `User` reads** in SPAR views (`patient.user->first_name`). → Route all identity access through `SparIdentity` DTO so views never touch `User` directly.
- **Scope creep into multi-tenant.** → Explicitly out of scope; design leaves room but we don't build it now.

## 7. Open Questions — RESOLVED (Captain Zan, 2026-09-03)

1. **Repo shape:** **Monorepo** (`packages/` + `apps/` via path Composer repositories). Simpler for a 2-person team; revisit only if SPAR is sold as a separately-hosted product.
2. **Standalone patient identity:** **Patients have NO login account.** They receive a tokenised link (SMS/WhatsApp), consent, and are served a rendered mobile web app. No password, no self-registration. A short OTP may re-verify a returning visitor so a forwarded link cannot leak PHI, but there is no account. Identity/contact lives on a **SPAR-owned patient identity record**, not on a telehealth `User`.
3. **Renewal in standalone:** confirmed — **no telehealth option in standalone.** `NullTelehealthBridge` offers only "renew with your own doctor". Integrated (ZapMed) additionally offers "consult a ZapMed doctor online".
4. **Branding:** SPAR-branded for the pilot. White-label left possible by design (theming via package config) but not built now.
5. **Deploy target for standalone:** separate Forge site on the shared box, **separate PostgreSQL 16 database**. Confirmed.

---

## 8. Onboarding, Consent & Access Model (agreed 2026-09-03)

### 8.1 Dual-mode onboarding — MUTUALLY EXCLUSIVE, config-gated
A deployment runs in **exactly one** onboarding mode, selected by `config('spar.onboarding_mode')`. The mode is chosen by whether the SPAR export carries patient contact details.

- **Mode A — `import`:** The export file is complete — Profile Code **plus** first name, surname, cellphone and/or email (and consent basis). `SparImportService` creates the patient **and** populates contactable identity in one step. No pharmacist data-entry required.
- **Mode B — `pharmacist_capture`:** The export is a sales/transaction extract with **no** contact details (the current reality — see brief). The CSV import still seeds medication history keyed on Profile Code, but a **pharmacist capture interface** is the source of contactable identity: the pharmacist enters name, surname, cellphone/email and records consent basis. Contact attaches to the imported history via **Profile Code**.

**Design consequence:** Mode A is effectively Mode B with the identity fields pre-filled by the file. Build **one** identity model + **one** link-send flow; the two modes differ only in *who populates the contact fields* (file vs. pharmacist). No divergent code paths.

**Identity rule (both modes):** `first_name` + `last_name` are required; **at least one** of `cellphone` / `email` is required (otherwise the tracker link is undeliverable and the patient cannot be onboarded). Patients lacking a contact channel sit in an **`awaiting_contact`** state and never receive a link.

### 8.2 Consent gate — hard stop, first interaction
- The first thing a patient sees when they open the SMS/WhatsApp link is a **consent screen**.
- **No consent → no access.** No PHI is displayed, no meds shown, and no further comms are sent until consent is granted.
- Consent is recorded with type, version (the exact wording shown), channel, `granted_at`, `revoked_at`, and source — backed by the existing `ConsentRecord` model plus the `SparPatient` consent fields. This is the POPIA-defensible evidence trail.
- Recommended: **double opt-in** — pharmacist/import may record an intent-to-consent, but the patient's own tap on the consent screen is the binding grant before any PHI is revealed.

### 8.3 Dependants — no separate identity, roll up to the primary member
- The export gives dependants **no unique, contactable identifier** (Dependent Code echoes the profile / is `0`; no separate phone). Confirmed against the sample file.
- Therefore **only the primary member** (the profile holder, `is_primary_member = true`) is contacted and receives the mobile web app.
- The primary member's My Meds view **rolls up the whole profile** — their own prescriptions plus all dependants' — because every dependant row shares the Profile Code.
- Dependants are **data rows under the primary member**, never independent logins or link recipients.
- ⚠ **POPIA flag (not a code task):** an adult dependant's medication being visible to the principal member is normal for a medical-aid principal, but should be blessed by the compliance officer before wide rollout. Fine for the pilot.

### 8.4 Actors & access
| Actor | Login? | Surface | Scope of visibility |
|---|---|---|---|
| **Main / Ops admin** | ✅ Yes | Admin backend | All pharmacies, all patients, all consent records |
| **Pharmacy staff** | ✅ Yes (store-scoped via `EnsureSparPharmacyScope`) | Admin backend | Only their own store's patients |
| **Patient (primary member)** | ❌ **No account** | Tokenised, rendered mobile web app | Only their own profile (self + dependants) |

Patient access is via a **signed/tokenised link** tied to their identity record — not a role/session account. This supersedes the current `role:patient` My Meds routing, which must be replaced during extraction.

### 8.5 Admin consent section
- **Consent list page:** all patients with an at-a-glance status column — green ✓ *Consented*, amber ⏳ *Pending*, red ✗ *Declined / Opted-out* — filterable by status / pharmacy / date, with summary tiles.
- **Per-patient consent detail page:** green-tick status banner; patient details (name, surname, contact, profile code, pharmacy, dependants); full consent audit trail (wording/version, channel, granted/revoked timestamps, source); actions to view history, record an in-store consent, and export the record.

### 8.6 One package → identical admin in both hosts
The consent section, pharmacy dashboard, patient mobile app, and all admin screens live in **`spar-core`**. Both the standalone app and ZapMed install the same package, so **both admins see the identical SPAR experience by construction** — no forked screens, no drift. Hosts differ only behind the contracts (`SparIdentityProvider`, `TelehealthBridge`, `SmsSender`, `AuditLogger`).

---

## 9. Current Build State vs. This Design (audited 2026-09-03)

Substantial SPAR functionality already exists in the ZapMed monolith; the extraction is refactor-heavy, not greenfield.

**Already built:** 8 `spar_*` migrations; 7 models; `SparImportService` / `SparOrderService` / `SparReminderService`; patient + pharmacy Livewire (`MyMeds*`, `PatientList`, `PharmacyDashboard`); admin Livewire (`SparDashboard`, `SparPharmacies`, `SparImports`, `SparExceptions`, `SparReporting`); `EnsureSparPharmacyScope` + `SparSessionTimeout` middleware; SPAR views + `spar-meds` layout; `SparJourneyDispenseTest`, `SparOrderLifecycleTest`, `SparReminderTest`; routes in `routes/web.php`.

**Gaps this design introduces (must be built/changed):**
1. **Identity decoupling** — `SparPatient` currently derives name via `->user->name` and reminders read `->user->phone`. Must hold its own `first_name`/`last_name`/`cellphone`/`email` (encrypted where PII) behind `SparIdentityProvider`, so standalone works with no `User`.
2. **Reminders are log-only** — `SparReminderService` has `// TODO: Send via WhatsApp/Crisp`; no real send, no link generation. Must send via `SmsSender` and generate the tokenised tracker link.
3. **Patient access** — current `my-meds/*` uses `role:patient` (an account). Must become a **tokenised, no-login** mobile web app with a consent gate first.
4. **Onboarding mode switch** — no `config/spar.php`, no `onboarding_mode`; Mode-A contact columns not read by import; no pharmacist capture screen.
5. **Consent section** — consent fields exist but there is no admin consent list/detail UI and no consent-first mobile flow.
6. **Dependants roll-up** — My Meds view does not yet aggregate dependant rows under the primary member.
7. **Contracts + packages** — no `SparIdentityProvider` / `TelehealthBridge` / `SmsSender` / `AuditLogger`, no `packages/spar-core`; renewal message hardcodes the ZapMed-doctor option instead of routing through `TelehealthBridge`.
8. **`config/spar.php` + `routes/spar.php`** do not exist yet (referenced by the target architecture).



---

## 12. Household roll-up & national patient identity (added 2026-09-11)

The patient mobi tracker rolls up the whole household — the principal plus all dependants
under the same Profile Code — onto the principal's view (`MyMedsTracker` / `MyMedsHistory`).

**Earlier limitation (now resolved):** the roll-up + patient identity were originally scoped
per pharmacy. `profile_code` is encrypted at rest, so it can't be matched with a plain SQL
`WHERE`; the old code loaded a single store's patients and matched in PHP, and the import keyed
a patient on `(profile_code, dependent_code, spar_pharmacy_id)`. A patient who filled at a
second SPAR store was therefore **duplicated**, and their household view was incomplete.

**Resolution — see `specs/spar-national-identity/`:** a **blind index** (keyed HMAC of the
profile code + phone) makes identity matchable across stores without decrypting. Import now
de-duplicates nationally on `profile_code_hash`; pharmacy moved off patient identity onto each
journey/dispense; staff scope is now "has a journey/dispense at an in-scope pharmacy"; and the
mobi tracker labels each medication with its originating pharmacy. Phone is a secondary
verification signal (conflict → review flag, never a silent merge). Duplicate historical rows
are collapsed by `spar:merge-duplicates` (unambiguous matches only).
