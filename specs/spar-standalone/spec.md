# SPAR Standalone — Requirements Specification

**Status:** Draft for review
**Related:** `design.md`, `tasks.md`
**Approach:** Extract SPAR into a `spar-core` Composer package consumed by two hosts (ZapMed = integrated, SPAR Standalone = independent).

---

## 1. Scope

Extract the SPAR Chronic Medication Module so it can run as a fully standalone application, while remaining runnable inside ZapMed with the telehealth bridge intact. No change to SPAR business behaviour.

## 2. Functional Requirements

### FR-1 — Dual deployment modes
- **FR-1.1** The SPAR domain (models, services, Livewire, migrations, routes) SHALL live in a single shared package (`spar-core`).
- **FR-1.2** ZapMed SHALL consume the package and operate in **integrated** mode.
- **FR-1.3** A new slim app (`spar-standalone`) SHALL consume the same package and operate in **standalone** mode.
- **FR-1.4** Standalone mode SHALL boot and operate with **no** telehealth tables, models, or code present.

### FR-2 — Identity & auth abstraction
- **FR-2.1** All actor resolution SHALL go through a `SparIdentityProvider` contract (no direct `User` reads in SPAR code/views).
- **FR-2.2** Integrated mode SHALL bind the provider to the ZapMed `User` table + `UserRole::PharmacyStaff`.
- **FR-2.3** Standalone mode SHALL bind the provider to package-owned identity (`pharmacy_users` for staff; SPAR patient contact records for patients).
- **FR-2.4** Patient "My Meds" OTP login SHALL work in both modes; OTP delivery goes through the `SmsSender` contract.
- **FR-2.5** `EnsureSparPharmacyScope` and `SparSessionTimeout` middleware SHALL enforce identically in both modes, reading role via the identity provider.

### FR-3 — Telehealth bridge
- **FR-3.1** Renewal handoff SHALL go through a `TelehealthBridge` contract.
- **FR-3.2** Integrated mode SHALL offer both "renew with primary doctor" and "consult a ZapMed doctor online" (current behaviour).
- **FR-3.3** Standalone mode SHALL use `NullTelehealthBridge` — renewal offers only "renew with your own doctor"; no online-consult path, no `zapmed_prescription_id` written.
- **FR-3.4** `spar_prescription_journeys.zapmed_prescription_id` and `spar_patients.user_id` SHALL be nullable with no DB-level FK to telehealth tables.

### FR-4 — Feature parity (both modes)
Standalone SHALL provide, unchanged: pharmacy dashboard (order queue requested→preparing→ready→completed), patient list, CSV import (batches + logs + exceptions), prescription journeys with dispense tracking + renewals, patient My Meds portal (dashboard, history, collection/delivery requests), reminders (monthly + renewal), and admin screens (dashboard, pharmacies, imports, exceptions, reporting).

### FR-5 — Infra contracts
- **FR-5.1** All patient/staff messaging SHALL go through a `MessagingChannel` contract (WhatsApp primary, SMS fallback, in-app/email as available); **FR-5.2** audit behind `AuditLogger` (`spar_audit` channel); **FR-5.3** field encryption via shared `EncryptsSensitiveFields` from `platform-support`.

### FR-6 — Dual-mode onboarding (config-gated, mutually exclusive)
- **FR-6.1** The active onboarding mode SHALL be set by `config('spar.onboarding_mode')` with values `import` or `pharmacist_capture`. A deployment runs exactly one mode.
- **FR-6.2** In `import` mode, `SparImportService` SHALL read patient contact fields (first name, surname, cellphone, email) from the export and populate the SPAR-owned identity record; no pharmacist capture is required.
- **FR-6.3** In `pharmacist_capture` mode, the CSV SHALL seed medication history only, and a **pharmacist capture interface** SHALL be the source of contactable identity, attaching to imported history via **Profile Code**.
- **FR-6.4** Patient identity SHALL require `first_name` + `last_name`, and **at least one** of `cellphone` / `email`. Records lacking any contact channel SHALL be held in an `awaiting_contact` state and SHALL NOT be sent a link.
- **FR-6.5** There SHALL be a single identity model and a single link-send flow shared by both modes; modes differ only in who populates contact fields.

### FR-7 — Consent gate (both modes)
- **FR-7.1** The first screen a patient sees on opening the link SHALL be a consent screen.
- **FR-7.2** Without granted consent the system SHALL NOT display any PHI, SHALL NOT show medications, and SHALL NOT send further communications.
- **FR-7.3** Consent SHALL be recorded with type, version (exact wording shown), channel, `granted_at`, `revoked_at`, and source, via `ConsentRecord` + `SparPatient` consent fields.
- **FR-7.4** The patient's own action on the consent screen SHALL be the binding grant (double opt-in); any import/pharmacist-recorded intent is preliminary.

### FR-8 — Dependants roll up to the primary member
- **FR-8.1** Dependants (rows where `dependent_code != 0` / `is_primary_member = false`) SHALL NOT receive their own link or login.
- **FR-8.2** Only the primary member SHALL be contacted and served the mobile web app.
- **FR-8.3** The primary member's My Meds view SHALL aggregate the whole profile — the member's own prescriptions plus all dependants under the same Profile Code.

### FR-9 — Patient access without an account
- **FR-9.1** Patients SHALL NOT have login accounts. Access SHALL be via a signed/tokenised link tied to their identity record.
- **FR-9.2** A returning visitor MAY be re-verified via short OTP so a forwarded link cannot leak PHI; this SHALL NOT create a role/session account.
- **FR-9.3** The current `role:patient` My Meds routing SHALL be replaced by the tokenised, no-login flow.

### FR-10 — Admin consent section (shared, both hosts)
- **FR-10.1** The admin backend SHALL provide a consent **list** page with an at-a-glance status (Consented ✓ / Pending ⏳ / Declined-Opted-out ✗), filterable by status, pharmacy, and date, with summary tiles.
- **FR-10.2** The admin backend SHALL provide a per-patient consent **detail** page with a green-tick status banner, patient details, dependants, and the full consent audit trail (version, channel, timestamps, source), plus view-history / record-in-store-consent / export actions.
- **FR-10.3** These screens SHALL live in `spar-core` so the standalone admin and the ZapMed admin render an identical experience.

### FR-11 — Reminder delivery + tracker link
- **FR-11.1** Reminder and onboarding messages SHALL be sent via the `MessagingChannel` contract (not log-only), and SHALL include the tokenised tracker link.
- **FR-11.2** The renewal message content SHALL be produced via `TelehealthBridge`: integrated includes "consult a ZapMed doctor online"; standalone (`NullTelehealthBridge`) offers only "renew with your own doctor".

### FR-12 — Messaging channels (WhatsApp-primary, SMS-fallback)
- **FR-12.1** `MessagingChannel` SHALL support multiple implementations resolved by a config-driven priority list (`spar.channels`); WhatsApp is the intended primary, SMS the permanent fallback, with in-app/email available.
- **FR-12.2** The system SHALL NOT be configurable as WhatsApp-only; SMS fallback SHALL always be available so no patient becomes unreachable.
- **FR-12.3** WhatsApp is **deferred** (no provider provisioned yet); the pilot SHALL operate on in-app + email + interim SMS. Adding `WhatsAppChannel` later SHALL be a new binding, not a rewrite.
- **FR-12.4** When WhatsApp is added, all proactive (outside-24h-window) sends SHALL use pre-approved templates.

### FR-13 — Renewal → ZapMed teleconsult funnel
- **FR-13.1** When a journey reaches `renewal_due`, the mobile web app SHALL show a renewal card; integrated mode SHALL present a "Book an online consultation" CTA, standalone SHALL present only "see your own doctor".
- **FR-13.2** A renewal notification SHALL be sent via `MessagingChannel` with the same options and a deep link.
- **FR-13.3** In integrated mode, `TelehealthBridge::handoffContext()` SHALL pass patient identity, the journey's medications, pharmacy, and a signed token so ZapMed pre-fills a chronic-renewal consultation.
- **FR-13.4** After a ZapMed renewal consult issues a new script, `TelehealthBridge::returnPrescription()` SHALL create a new SPAR journey at the originating pharmacy, restarting the cycle.
- **FR-13.5** Standalone (`NullTelehealthBridge`) SHALL expose no online-consult CTA, no handoff, and no return path.

## 3. Non-Functional Requirements
- **NFR-1 (POPIA):** encryption of `profile_code`, `medical_aid_number`, `id_number`-class fields, and full audit logging preserved in both modes.
- **NFR-2 (No drift):** exactly one implementation of SPAR logic; both hosts depend on the package.
- **NFR-3 (Tests):** existing SPAR tests ported into the package and green before and after extraction; new tests for standalone-mode contract bindings.
- **NFR-4 (DB):** standalone uses its own PostgreSQL 16 database; no shared schema with ZapMed.
- **NFR-5 (Deploy):** standalone deployable as an independent Forge site.

## 4. Acceptance Criteria
- **AC-1** `composer install` on a clean `spar-standalone` app + migrate + seed → working pharmacy dashboard and My Meds portal, with zero telehealth tables in the schema.
- **AC-2** ZapMed after refactor behaves identically to today (all current SPAR + telehealth tests green; renewal still offers the ZapMed doctor option).
- **AC-3** Grep of `spar-core/src` finds **no** references to `App\Models\User`, `App\Models\Prescription`, or `App\Enums\UserRole` — only contracts.
- **AC-4** Standalone renewal reminder message contains no "ZapMed doctor" option; integrated one does.
- **AC-5** `spar_audit` entries produced in both modes for patient access, order changes, imports, consent changes.
- **AC-6** OTP/link access succeeds in both modes via the respective identity provider; no `role:patient` account is created.
- **AC-7** In `import` mode a complete export creates contactable, consent-pending patients; in `pharmacist_capture` mode the capture screen produces the same, matched to imported history by Profile Code. Records with no contact channel land in `awaiting_contact` and receive no link.
- **AC-8** A patient with no granted consent sees only the consent screen — no PHI, no meds, no comms. Consent grant is recorded with version + channel + timestamp.
- **AC-9** A dependant receives no link; the primary member's My Meds view shows self + all dependants under the Profile Code.
- **AC-10** Admin consent list + detail render identically in standalone and ZapMed, with correct green/amber/red status and a complete audit trail.

## 5. Out of Scope
Multi-tenant SaaS; rewriting SPAR business logic; migrating ZapMed's existing SPAR data out; white-label theming (design leaves room, not built now).

## 6. Dependencies / Decisions — RESOLVED (2026-09-03)
Design §7 open questions are **resolved**: monorepo; patients have no login (tokenised mobile web app + optional OTP re-verify); no telehealth in standalone (`NullTelehealthBridge`); SPAR-branded pilot; separate Forge site + separate Postgres DB. Onboarding, consent, dependants, and access model are specified in FR-6…FR-11 and design §8. No remaining blockers to start Phase 1.

Still awaited from SPAR (does not block Phase 1 code; affects Mode selection + data richness): the patient master keyed on Profile Code (name, mobile, ID/DOB, dependant relationships), data dictionary, renewal status fields, return-route confirmation, and cumulative raw-export delivery. If contact columns arrive, deployment uses Mode A; otherwise Mode B (pharmacist capture).


---

# Addendum A — Platform administration & pharmacy hierarchy (2026-09-04)

**Context:** ZapMed operates SPAR as their platform. It must run 100% standalone first; the same
administration + login then drops into the integrated ZapMed telehealth host (shared `spar-core`).
This addendum promotes the pharmacy hierarchy into scope (previously deferred in §5) — **groups are
tenants**, but as a single-install hierarchy owned/operated by ZapMed, not a self-service SaaS signup.

## Roles (4 tiers)

```
ZapMed super-admin  → everything, all groups, all pharmacies
  Pharmacy Group    → e.g. "SPAR Western Cape"
    Group admin     → manages pharmacies + staff WITHIN its group; group-wide stats
    Pharmacy        → a store; belongs to exactly ONE group
      Pharmacy admin→ manages staff WITHIN its own store; pharmacy stats
      Pharmacy staff→ operates the store (patients, capture, orders); no management
```

## FR-14 — Pharmacy groups
- **FR-14.1** A `SparPharmacyGroup` SHALL exist (name, region, logo, contact, is_active).
- **FR-14.2** Every `SparPharmacy` SHALL belong to exactly one group (`group_id`). Existing pharmacies
  are migrated into a default group.
- **FR-14.3** Only ZapMed super-admin creates/edits/deactivates groups.

## FR-15 — Role hierarchy & who-creates-who
- **FR-15.1** Roles: `super_admin`, `group_admin`, `pharmacy_admin`, `pharmacy_staff`.
- **FR-15.2** ZapMed **super-admin** can do everything: create groups, create pharmacies in any group,
  create group-admins, and create any staff/admin anywhere.
- **FR-15.3** **Group admin** (created by super-admin) creates/manages pharmacies in **its own group**
  and staff/admins in that group only.
- **FR-15.4** **Pharmacy admin** creates/manages staff in **its own store** only.
- **FR-15.5** **Pharmacy staff** perform no management.

## FR-16 — Scope enforcement (hard)
- **FR-16.1** Every actor's data visibility SHALL be scoped by role: super-admin = all; group admin =
  their group's pharmacies + patients; pharmacy admin/staff = their pharmacy only.
- **FR-16.2** All actor/scope resolution SHALL go through the existing `SparIdentityProvider` contract,
  extended with `isSuperAdmin()`, `currentGroupId()`, `currentPharmacyId()`, and
  `canManagePharmacy($id)` / `canManageGroup($id)`. No host user class named in the package (AC-3 holds).
- **FR-16.3** A group admin SHALL NOT see or act on another group; a pharmacy actor SHALL NOT see or
  act on another pharmacy — enforced at query + policy level, not just UI.

## FR-17 — Standalone-first, then integrated
- **FR-17.1** The entire hierarchy + admin + stats SHALL be delivered in `spar-core` and operate 100%
  in the standalone app (identity = `pharmacy_users` with a `role` + optional `group_id`).
- **FR-17.2** The SAME capability SHALL then bind into the integrated ZapMed host with no rewrite:
  ZapMed `User` (via its identity provider) supplies super-admin / group-admin / pharmacy scope.

## FR-18 — Statistics (platform / group / pharmacy)
- **FR-18.1** A stats surface SHALL present metrics scoped to the viewer: super-admin = platform-wide;
  group admin = their group; pharmacy = their store.
- **FR-18.2** Metrics SHALL include (at least): counts (groups/pharmacies/patients/active journeys);
  onboarding funnel (awaiting_contact→pending_consent→active→opted_out) with conversion; consent rates
  (opted-in / pending / opted-out); adherence (dispenses on-time/late/missed); renewals (due/completed/
  lapsed); messaging (sent by channel, delivery success, WhatsApp template usage); growth over time;
  and a pharmacy leaderboard (super-admin/group only).

## Acceptance criteria (additional)
- **AC-11** In the standalone app, a super-admin can create a group → a pharmacy under it → a staff
  login for that pharmacy, and that staff can log in and see only their store.
- **AC-12** A group admin can create a pharmacy + staff in their own group, and CANNOT see or manage
  another group's pharmacies/patients (query-level isolation, verified by test).
- **AC-13** A pharmacy admin can add staff to their own store only; a pharmacy staff has no management UI.
- **AC-14** The stats surface shows correctly-scoped numbers for each of the three viewer levels.
- **AC-15** `spar-core/src` still contains zero references to any host user class (AC-3 preserved) after
  the hierarchy is added — scope resolves via `SparIdentityProvider`.
- **AC-16** The same super-admin/group/stats capability, once bound in the integrated ZapMed host,
  behaves identically (parity test in both hosts).

## §5 scope change
The original §5 listed "multi-tenant SaaS" as out of scope. **Revised:** a ZapMed-operated pharmacy
hierarchy (groups → pharmacies → staff) with role-scoped access is now **IN scope**. Still out of scope:
public self-service tenant signup/billing and per-tenant separate databases (the hierarchy lives in the
one install's schema, scoped by `group_id` / `pharmacy_id`).
