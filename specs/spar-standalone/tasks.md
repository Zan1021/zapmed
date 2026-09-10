# SPAR Standalone — Task List

**Status:** ✅ **IMPLEMENTED** (updated 2026-09-04) — Phases 0–4 complete; Phase 5 done except 5.5 (gated staging deploy); Phase 6 WhatsApp BUILT (Meta Cloud API direct, log-driver default) — go-live only pending 6.2 Meta onboarding. Tests: ZapMed **121 pass / 1 pre-existing unrelated fail**, standalone **6 pass**. Docs: `apps/spar-standalone/README.md`, `packages/spar-core/README.md`, `INTEGRATION.md`, `whatsapp-setup.md`.
**Related:** `design.md`, `spec.md`, `INTEGRATION.md`
**Strategy:** Extract-in-place, never rewrite. Tests stay green at every phase. Phase 0 is **resolved** (design §7 answered 2026-09-03). New onboarding/consent/access requirements (FR-6…FR-11) are folded into the phases below.

Legend: `[ ]` todo · `[x]` done · est: S/M/L rough effort · ⚠ = risk/verify point
Status tags: **(new)** feature not yet built · **(refactor)** exists, must change · **(exists)** already built, leave as-is unless moved.

---

## Phase 0 — Decisions & safety net — RESOLVED
- [x] 0.1 Design §7 open questions answered (monorepo; no patient login; no telehealth in standalone; SPAR-branded; separate Forge site + Postgres).
- [x] 0.2 Onboarding/consent/dependant/access model agreed (design §8, spec FR-6…FR-11).
- [x] 0.3 Snapshot current behaviour: baseline recorded = **71 passed / 1 failed** (pre-existing unrelated Auth dashboard-302). ⚠ baseline.
- [x] 0.4 SPAR test coverage: **DONE (dedicated pass) 2026-09-04** — new `SparCoverageAuditTest` is a coverage GUARD asserting every SPAR capability area (FR-3/6/7/8/9/10/11/12, NFR-1, AC-4/5/7/8/9/10, order + dispense lifecycles) has a dedicated test class, and that no on-disk SPAR suite is unregistered. Full SPAR subset now **52 pass**. Turns "covered incrementally" into an enforced invariant.

## Phase 1 — New feature work INSIDE ZapMed (unblocks the product, low extraction risk)
These make the current module match the agreed model. All reversible, all inside the monolith, tests green throughout.

- [x] 1.1 **(new)** Add `config/spar.php` with `onboarding_mode` (`import`|`pharmacist_capture`), `host_mode`, `channels`, reminder/link settings, session-timeout, branding keys.
- [x] 1.2 **(new)** SPAR-owned patient identity: added `first_name`, `last_name`, `cellphone`, `email` (encrypted) + `onboarding_status` to `spar_patients`. Migration nullable. ✅ **Backfill DONE 2026-09-04**: `spar:backfill-identity` command (host-side, integrated-only) fills empty SPAR identity from linked `User` (first_name/last_name/phone→cellphone/email), never overwrites captured data, refreshes onboarding, `--dry-run` supported. Tested by `SparBackfillIdentityTest` (4 pass).
- [x] 1.3 **(refactor)** `SparPatient::getDisplayNameAttribute()` + `primaryPhone()`/`primaryEmail()` use own identity, `User` fallback only in integrated `host_mode`.
- [x] 1.4 **(new)** Identity rule: `hasCompleteIdentity()` (first+last name and ≥1 of cellphone/email); `refreshOnboardingStatus()` sets `awaiting_contact`/`pending_consent`/`active`/`opted_out`.
- [x] 1.5 **(refactor)** `SparImportService` Mode A reads contact columns → populates identity; Mode B seeds history only. Gated on `config('spar.onboarding_mode')`.
- [x] 1.6 **(new)** Pharmacist capture interface (`Spar\PharmacistCapture` + view, route `spar.capture`, store-scoped): lists `awaiting_contact` patients, captures name/surname/cellphone/email + consent basis (≥1 contact enforced), activates + sends tracker link via `MessagingDispatcher`. Also fixed `PatientList` search (was querying stale `user` relation + encrypted `profile_code`).
- [x] 1.7 **(new)** Consent gate mobile flow: `MyMedsTracker` consent step is a hard stop — no PHI/meds until granted. Grant/revoke recorded via new SPAR-owned `SparConsent` (version, channel, source, ip, ua). Double opt-in (pharmacist basis + patient tap).
- [x] 1.8 **(refactor)** Patient access without account: `spar.track` signed link → `SparPatientSession` (no `User` login) → OTP re-verify → consent → dashboard. `MyMedsLogin` reworked off `Auth::login` to a SPAR patient session. Old `role:patient` `my-meds` group + `MyMedsDashboard` removed. `EnsureSparPatientSession` middleware guards PHI pages. ⚠ AC-6.
- [x] 1.9 **(new)** Dependants roll-up: `MyMedsTracker`/`MyMedsHistory` aggregate self + all dependants under the Profile Code (PHP-side match — `profile_code` is encrypted). Dependants get no link/login. ⚠ AC-9.
- [x] 1.10 **(refactor)** `SparReminderService`: real send via `MessagingChannel` (in-app + email + `SmsService`; WhatsApp deferred) + signed tracker link (`spar.track`).
- [x] 1.11 **(new)** Admin consent section: `Admin\SparConsents` + view (route `admin.spar.consent`) — list with green/amber/red status + tiles + filter; per-patient detail with banner, details, dependants, full `SparConsent` audit trail. ⚠ AC-10.
- [x] 1.12 **(new)** Renewal funnel — mobile app: renewal card in `MyMedsTracker` on `renewal_due` with host-mode-gated CTA ("Book online consultation" integrated / "see your own doctor" standalone).
- [x] 1.13 **(new)** Renewal funnel — notification: `SparReminderService::sendRenewalReminder` sends via `MessagingChannel` with tracker link; copy host-mode-gated (interim until Phase 2 `TelehealthBridge`).
- [x] 1.14 Full suite green: **91 passed / 1 pre-existing unrelated fail** (Auth dashboard-302), zero regressions. +20 tests over baseline. ⚠ Phase-1 gate PASSED.

## Phase 2 — Introduce contracts inside ZapMed (no move yet)
- [x] 2.1 `SparIdentity` DTO + `SparIdentityProvider` contract + `UserSparIdentityProvider` (integrated, reads own fields then falls back to `User`). Bound in `SparServiceProvider`.
- [x] 2.2 `AuditLogger` contract + `ChannelAuditLogger` (`spar_audit`). `MessagingChannel` already formalised in Phase 1 (contract + dispatcher).
- [x] 2.3 `TelehealthBridge` contract + `ZapmedTelehealthBridge` (`offersOnlineConsult`, `renewalOptions`, `handoffContext` w/ identity+meds+signed token, `returnPrescription` loop-closer) + `NullTelehealthBridge`. Bound by `host_mode` in `SparServiceProvider`. `SparReminderService` renewal copy now driven by the bridge (not a `host_mode` check). Route `spar.renewal-handoff` (signed) added.
- [x] 2.4 Telehealth FK relaxation migration (`relax_spar_telehealth_foreign_keys`): drops DB-level FK on `zapmed_prescription_id` + `spar_patients.user_id` on pgsql/mysql; SQLite documented no-op (package migrations are source of truth for standalone). Columns stay nullable; app-level relations retained.
- [x] 2.5 Full suite green: **96 passed / 1 pre-existing unrelated fail**, zero regressions. +25 over baseline. ⚠ Phase-2 gate PASSED.

## Phase 3 — Carve out `spar-core` package — COMPLETE ✅ (verified 2026-09-04)
- [x] 3.1 Created `packages/platform-support`; moved `EncryptsSensitiveFields` (+ host shim); ZapMed depends via path repo. Encryption round-trips (SPAR tests green). — est: M
- [x] 3.2 Created `packages/spar-core` with `SparCoreServiceProvider` (defensive: registers config/migrations/views/routes/middleware only if present; auto-discovered via `extra.laravel.providers`). — est: M
- [x] 3.3 Moved SPAR models, services (host-injected channel registry), Livewire (12 components → `Zapmed\SparCore\Livewire{,\Admin}`), middleware (`EnsureSparPharmacyScope`, `SparSessionTimeout`, `EnsureSparPatientSession`), `LogsSparActivity` concern, `config/spar.php`, `routes/spar.php`, views (`spar::`) into the package. Host keeps concrete bindings (SmsChannel, UserSparIdentityProvider, ZapmedTelehealthBridge, ChannelAuditLogger, ZapmedOtpSender). — est: L
- [x] 3.4 Moved 10 `spar_*` migrations into the package with idempotent guards (`Schema::hasTable`/`hasColumn`); host FKs to `users`/`prescriptions` dropped for portability. Same filenames kept so ZapMed's migrations table still marks them run. — est: M
- [x] 3.5 ZapMed consumes `spar-core` + `platform-support` via path repos (@dev). Moved originals deleted from `app/`. Host binds `SparIdentityProvider→User`, `TelehealthBridge→Zapmed`, `AuditLogger`, `OtpSender`, sms channel. `app/Models/Spar*` = thin subclass shims re-adding telehealth relations. — est: M
- [x] 3.6 Full suite green: **96 pass / 1 pre-existing unrelated fail** (Auth dashboard-302); SPAR subset **38 pass**. ⚠ AC-3 gate PASSED — grep `spar-core/src` for `App\Models\User`/`Prescription`/`UserRole` = **zero** (`user_model` now via `config('spar.user_model')`). — est: S

## Phase 4 — Standalone host app — COMPLETE ✅ (verified 2026-09-04)
- [x] 4.1 Scaffolded `apps/spar-standalone` (fresh Laravel 12 + Livewire 3). Consumes `spar-core` + `platform-support` via path composer repos (symlink junctions). `composer install` OK. — est: M
- [x] 4.2 Standalone identity: `pharmacy_users` migration + `PharmacyUser` model + `StandaloneSparIdentityProvider` (SPAR-owned identity, no `User`). — est: M
- [x] 4.3 `SparStandaloneServiceProvider` binds `TelehealthBridge→NullTelehealthBridge` (own-doctor renewal only, no CTA/handoff/return), `SparIdentityProvider→Standalone`, `AuditLogger→LogAuditLogger`, `OtpSender→StandaloneOtpSender`, sms→`StandaloneSmsChannel` (pilot: log-only). ⚠ AC-4 PASS. — est: S
- [x] 4.4 Standalone auth: `pharmacy_users` guard + `StaffLogin` Livewire; `route_middleware` (staff = web,auth,spar.scope,spar.timeout — no `role:` enum; admin = web,auth); patient tokenised link + OTP re-verify (no account). host_mode=standalone, user_model=null. ⚠ AC-6. — est: M
- [x] 4.5 Migrate + seed standalone; `php artisan spar:verify-schema` confirms **no telehealth tables** (only `pharmacy_users` + 7 `spar_*` + framework). ⚠ AC-1 PASS. Seeder: demo pharmacy + staff/admin accounts + 2 patients. — est: S
- [x] 4.6 Smoke every screen — `StandaloneSmokeTest` **6 pass**: staff dashboard/patients/capture, all 6 admin screens, patient tracker consent gate + dependant roll-up, guest redirect, AC-1/AC-4 bindings. (Feature tests over curl smoke — more durable on old PowerShell.) — est: M

## Phase 5 — Verification & deploy
- [x] 5.1 Ran acceptance checklist AC-1…AC-10 across both hosts (recorded 2026-09-04, see matrix below). — est: M

### AC-1…AC-10 results matrix (2026-09-04)
| AC | Criterion (short) | Status | Evidence |
|----|-------------------|--------|----------|
| AC-1 | Standalone migrates/seeds, **zero telehealth tables** | ✅ PASS | `spar:verify-schema` → only `pharmacy_users` + 7 `spar_*` + framework tables |
| AC-2 | ZapMed integrated behaves as before (SPAR + renewal ZapMed-doctor option intact) | ✅ PASS | Full suite 96 pass / 1 pre-existing unrelated fail (Auth dashboard-302); SparTelehealthBridgeTest "zapmed bridge offers online consult and handoff" green |
| AC-3 | `spar-core/src` has zero `App\Models\User`/`Prescription`/`UserRole` refs | ✅ PASS | grep = no matches; `user_model` via `config('spar.user_model')` |
| AC-4 | Standalone renewal has no ZapMed-doctor option; integrated does | ✅ PASS | Standalone `NullTelehealthBridge::offersOnlineConsult()===false` (smoke test); integrated bridge offers it (SparTelehealthBridgeTest) |
| AC-5 | `spar_audit` entries produced in both modes (access, orders, imports, consent) | ✅ PASS **(gap closed 5.4)** | `SparPopiaAuditTest`: `LogsSparActivity` trait writes patient_access/order_update/data_import/consent_change to `spar_audit`; `AuditLogger` contract (ChannelAuditLogger) writes too |
| AC-6 | OTP/link access works both modes; no `role:patient` account created | ✅ PASS | SparTrackerConsentTest (signed link → session → tracker); no-login `SparPatientSession`; standalone smoke guest→staff.login |
| AC-7 | Mode A import + Mode B capture both yield contactable consent-pending patients; no-contact → `awaiting_contact` | ✅ PASS | SparOnboardingIdentityTest (mode A populates identity; mode B seeds history + awaiting_contact) + SparPharmacistCaptureTest |
| AC-8 | No consent → only consent screen, no PHI/meds/comms; grant recorded w/ version+channel+ts | ✅ PASS | SparTrackerConsentTest (consent gate blocks then grants + records evidence; history blocked without consent) |
| AC-9 | Dependant gets no link; primary view = self + all dependants under Profile Code | ✅ PASS | SparDependantRollupTest (roll-up count=2; dependant not contactable) |
| AC-10 | Admin consent list+detail render identically in both hosts, correct status + audit trail | ✅ PASS (functional) | SparAdminConsentTest (integrated) + standalone smoke renders all 6 admin screens incl consent. Screens live in `spar-core` → single implementation |

**Summary:** 10/10 PASS (AC-5 gap closed in 5.4). ZapMed integrated suite now **101 pass / 1 pre-existing unrelated fail**; SPAR subset **40 pass**.

- [x] 5.2 Reminder-parity test **(new: `SparRenewalParityTest`, 2 pass)** — drives the real `SparReminderService::processReminders()` renewal path through a spy `MessagingChannel`; integrated body offers the ZapMed-online option, standalone body omits it (own-doctor only). AC-4 now automated end-to-end. — est: S
- [x] 5.3 Consent + onboarding + dependant tests **(REDONE LITERALLY 2026-09-04 — new dedicated file `SparPhase53VerificationTest`, 7 pass)**. One file, three regions mapped 1:1 to the task text: **AC-7** Mode A import → contactable/pending_consent, Mode B capture → history seeded/awaiting_contact then matched-by-Profile-Code on capture, no-contact → awaiting_contact; **AC-8** consent gate = only-consent-screen then grant records version+channel+timestamp, PHI route blocked pre-consent; **AC-9** primary view rolls up self + dependants, dependant never contactable/linked. (Previously marked done by leaning on scattered Phase-1 tests — reopened + done to the tee.) — est: M
- [x] 5.4 POPIA check **(new: `SparPopiaAuditTest`, 3 pass)** — (a) encryption at rest verified: `profile_code`/`cellphone`/`email` are ciphertext (`eyJ…`) in the column, model decrypts transparently; (b) `spar_audit` entries verified on BOTH seams — `LogsSparActivity` trait (patient_access/order_update/data_import/consent_change) and `AuditLogger` contract. AC-5 gap closed. ⚠ NOTE: `SparPatient::$encryptedFields` lists `medical_aid_number` but the table has no such column (has `medical_aid_name`/`medical_aid_option`, plaintext) — dead entry, harmless (empty-skip), flagged for cleanup. — est: S
- [ ] 5.5 Standalone Forge site + separate Postgres DB provisioned; deploy; health check green. ⚠ high-risk (infra) — confirm with Captain Zan before provisioning. **← GATED, not started.** — est: M
- [x] 5.6 Docs **(done 2026-09-04)**: standalone README (`apps/spar-standalone/README.md`), package README (`packages/spar-core/README.md`), ZapMed integration notes (`specs/spar-standalone/INTEGRATION.md`). Spec set marked **IMPLEMENTED** (Phases 0–4 + 5.1–5.4, 5.6). Only 5.5 (gated infra deploy) + Phase 6 (WhatsApp, deferred) remain. — est: S

---

## Phase 6 — WhatsApp channel — Meta Cloud API direct (BUILT 2026-09-04; go-live gated on Meta onboarding)
Provider = **Meta Cloud API direct** (6.1). Ships a **`log` driver** so the whole flow is testable/demoable WITHOUT Meta creds; flip to `cloud_api` when the WABA is live. Slots behind `MessagingChannel`, SMS stays fallback. Setup + templates: `specs/spar-standalone/whatsapp-setup.md`.
- [x] 6.1 **Provider chosen: Meta Cloud API direct** (confirmed by Captain Zan 2026-09-04). Isolated behind the contract — swappable to a BSP later with no rewrite. — est: discussion
- [ ] 6.2 WhatsApp Business number + Meta Business verification. ⚠ **Captain Zan real-world action** — runbook in `whatsapp-setup.md` §6.2. Code ready; needs Phone Number ID + permanent token. — est: process
- [x] 6.3 Template set authored (onboarding_consent, monthly_collection, renewal_due, missed_renewal) with body/vars/URL-button, ready for Meta submission. Config keys wired (`spar.whatsapp.templates.*`). — est: M
- [x] 6.4 **`WhatsAppChannel` implemented** (`packages/spar-core/.../Channels/WhatsAppChannel.php`); registered as `whatsapp` channel factory in BOTH hosts; driver switch `log`|`cloud_api`; E.164 normalisation. Set primary via `SPAR_CHANNELS=inapp,whatsapp,email,sms` (SMS fallback). Tested `SparWhatsAppChannelTest` (9 pass, HTTP faked — never contacts Meta). — est: M
- [x] 6.5 **24h session window** honoured: free-form only inside window (tracked via `metadata.wa_last_inbound_at`); proactive outside window uses approved templates only, else **defers to SMS** (never sends disallowed free-form). Rich URL-button on renewal/collection templates. Tested. — est: M

> **Remaining for WhatsApp go-live:** only 6.2 (Captain Zan completes Meta onboarding → provides Phone Number ID + token). Then set `SPAR_WHATSAPP_DRIVER=cloud_api`, `SPAR_WHATSAPP_ENABLED=true`, the 4 approved template names, and `SPAR_CHANNELS=inapp,whatsapp,email,sms`. No further code required.

---

## Phase 7 — Platform hierarchy: groups, roles & management (STANDALONE-FIRST)
Spec: Addendum A (FR-14…FR-17, AC-11…AC-13, AC-15). All domain in `spar-core` so both hosts share it. Built + verified in the **standalone** app. **Security is built-in, not bolted-on** — scope isolation is enforced at query + policy level and tested here (not deferred to Phase 10).
- [x] 7.1 **(new) DONE 2026-09-04** — `spar_pharmacy_groups` table + `SparPharmacyGroup` model (auto-slug); `group_id` on `spar_pharmacies` (plain nullable, package migration, idempotent) with **default-group backfill** (no orphans); `group_id` on standalone `pharmacy_users`; `PharmacyUser` 4-tier role helpers (legacy `admin`→super); `SparPharmacy::group()` relation. Migrated both hosts. Tested `SparGroupDataModelTest` (4 pass); standalone 10 / SPAR subset 63 green. ⚠ FR-14.
- [x] 7.2 **(new) DONE 2026-09-04** — Extended `SparIdentityProvider` contract: `currentGroupId()`, `isSuperAdmin()`, `currentRole()`, `canManageGroup($id)`, `canManagePharmacy($id)` (+ existing `currentPharmacyId()`). Implemented in BOTH hosts (standalone reads `PharmacyUser` role/group_id/pharmacy_id; ZapMed maps `UserRole::Admin`→super_admin). Added **fail-closed** `scopeVisibleToCurrentActor()` to `SparPharmacy` + `SparPatient` (super=all, group=group's pharmacies, pharmacy=own store, else nothing) — resolves scope via the bound provider, **zero host-user refs in package** (AC-15 grep gate PASS). Tested `SparScopeResolutionTest` (5 pass, all tiers + unauth). ZapMed SPAR subset 63 green. ⚠ FR-16, AC-15.
- [x] 7.3 **(new) DONE 2026-09-04** — `Admin\SparGroups` Livewire (list/create/edit/deactivate) in `spar-core`, **super-admin-only** (hard 403 gate via `SparIdentityProvider::isSuperAdmin()` in mount + every action), audit-logged. Route `admin.spar.groups`; nav link shown only to super-admin. Tested `SparGroupManagementTest` (4 pass: super creates/toggles; group-admin + staff forbidden). ⚠ FR-14.3, FR-15.2.
- [x] 7.4 **(new) DONE 2026-09-04** — `Admin\SparPharmacies` reworked scope-enforced: `mount()` 403 unless super/group-admin; list via `visibleToCurrentActor()`; **group_id select** (super sees all groups, group-admin locked to own); `save`/`edit`/`toggleActive` gated by `canManageGroup`/`canManagePharmacy` (blocks a group-admin smuggling another group's id); audit-logged; group column added to table. Tested `SparPharmacyManagementTest` (5 pass incl. cross-group block + scoped list + staff 403). ⚠ FR-15.2/15.3, AC-12.
- [x] 7.5 **(new) DONE 2026-09-04** — `App\Livewire\Admin\StaffManagement` (standalone-side, since it manages the host-owned `PharmacyUser` — AC-15 keeps it out of the package). Tiered per FR-15: super=any role/scope, group-admin=pharmacy_admin/staff in own group, pharmacy-admin=staff in own store; **cannot create at/above own tier** (assignable-roles allowlist) or outside scope (assertAssignmentAllowed + findInScope). Route `admin.staff`, nav for `canManageUsers()`, audit-logged. Tested `SparStaffManagementTest` (5 pass incl. escalation blocked + staff 403). ⚠ FR-15.2–15.5, AC-13.
- [x] 7.6 **(new) DONE 2026-09-04** — Scope enforced on existing screens: `PatientList` now uses `visibleToCurrentActor()` (was `forPharmacy` which showed nothing to super/group); `Admin\SparConsents` list + stats + detail scoped via `visibleToCurrentActor()`. `PharmacistCapture` already store-scoped (verified, unchanged). `PharmacyDashboard` correctly empty for non-pharmacy actors (store view). Tested `SparScreenScopeTest` (3 pass). ⚠ FR-16.1/16.3.
- [x] 7.7 **(new) DONE 2026-09-04** — Scope-isolation SECURITY tests consolidated across `SparScopeResolutionTest`, `SparPharmacyManagementTest`, `SparStaffManagementTest`, `SparScreenScopeTest`, plus **`SparHierarchyE2ETest`** (AC-11: super-admin builds group→pharmacy→staff→login end-to-end; the new staff logs in and sees ONLY their store + is 403 on all management screens). AC-12 (group can't cross groups) + AC-13 (pharmacy-admin staff-only own store; staff no management) covered. ⚠ AC-11/12/13.
- [x] 7.8 **(new) DONE 2026-09-04** — Full suites green: standalone **33 pass**, ZapMed **121 pass / 1 pre-existing unrelated fail** (Auth dashboard-302, not ours). **AC-15 gate PASS** — grep `spar-core/src` for `App\Models`/`App\Enums`/`PharmacyUser` = zero. Phase 7 COMPLETE.

## Phase 8 — Statistics (platform / group / pharmacy — scope-filtered)
Spec: FR-18, AC-14. In `spar-core`; standalone views.
- [x] 8.1 **(new) DONE 2026-09-04** — `SparStatsService` (package): `scopedPharmacyIds()` resolves the actor's visible set via `SparIdentityProvider` (super=null/all, group=group's pharmacies, pharmacy=own, else fail-closed empty). `forCurrentActor()` returns counts, onboarding funnel + activation rate, consent rates, adherence (collected/upcoming/overdue), renewals (due/renewed/lapsed-30d), order queue, messaging (reminders sent), and pharmacy leaderboard (super/group only). Aggregates only — no per-patient PHI. — est: L
- [x] 8.2 **(new) DONE 2026-09-04** — `Admin\SparStats` Livewire + view: ONE component, THREE auto-scoped views (platform/group/pharmacy) driven by role; tiles + funnel + consent + adherence + renewals + leaderboard; `wire:poll.30s`. Route `admin.spar.stats`; nav link for all authed staff. — est: M
- [x] 8.3 **(new) DONE 2026-09-04** — `SparStatsTest` (5 pass): super sees platform totals + leaderboard; group-admin only their group; pharmacy-staff only their store (100% consent, no leaderboard); dashboard renders; unauth 403. Both hosts green (standalone 38, ZapMed subset 63); AC-15 still clean. ⚠ AC-14. — est: M

## Phase 9 — ZapMed super-admin via SSO into standalone — ⏸ LATER (parked 2026-09-04)
**NOT NOW.** Captain Zan: do after standalone works + tested + on staging. No shared DB → ZapMed gets FULL SPAR functionality by SSO-ing a super-admin session into the real standalone app (one SPAR brain, two front doors), NOT by rebuilding SPAR in ZapMed. Spec: FR-17.2, AC-16. Security-critical (signed short-lived tokens, tight scope, key rotation, replay protection).
- [ ] 9.1 ⏸ ZapMed mints signed short-lived SSO token → standalone verifies + establishes super-admin session; ZapMed adds a "SPAR platform" entry point. — est: M (LATER)
- [ ] 9.2 ⏸ Parity + SSO security tests. — est: M (LATER)

## Phase 10 — Security hardening & POPIA compliance (GO-LIVE GATE)
Cross-cutting; security ACs are woven into Phases 7–8 as built, and this phase is the consolidated hardening + audit gate BEFORE staging carries real patient data. ⚠ Patient PHI — highest priority.
- [x] 10.1 **Auth hardening — CODE DONE 2026-09-04** — staff login rate-limited (`throttle:20,1`), passwords hashed, session timeout (`spar.timeout`), scoped authz enforced query+policy (Phase 7). ⏳ **MFA NOT built — Captain Zan decision (TOTP recommended for admins); go-live blocker for privileged tiers.** — est: M
- [x] 10.2 **Data protection — DONE 2026-09-04** — PHI encrypted at rest (`profile_code`/`cellphone`/`email`); removed dead `medical_aid_number` entry; no PHI in stats/logs; TLS+HSTS via headers middleware. Verified `SparSecurityTest::phi_is_encrypted_at_rest`. — est: M
- [x] 10.3 **Attack surface — DONE 2026-09-04** — `SparSecurityHeaders` middleware (CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS) on all web responses; public tracker+OTP routes rate-limited (`throttle:30,1`); CSV hardening pre-existing; CSRF default. Tested. — est: M
- [x] 10.4 **Audit & breach — CODE DONE 2026-09-04** — `spar_audit` now covers access/orders/imports/consent/group/pharmacy/staff-mgmt/PHI-erasure. ⏳ **Tamper-evident storage + breach-detection alerting + incident/breach-notification process = Captain Zan owns** (POPIA Regulator + data-subject notification). — est: M
- [x] 10.5 **Data lifecycle — DONE 2026-09-04** — `spar:data-retention` command: `--erase=<id>` (right-to-erasure, anonymises PHI + keeps audit trail) + retention sweep for opted-out patients older than `spar.retention.opted_out_days` (default 365); `--dry-run`. Tested `SparSecurityTest::right_to_erasure`. Schedule sweep in cron at deploy. — est: M
- [x] 10.6 **Verify — CODE DONE 2026-09-04** — automated security tests (`SparSecurityTest` 4 pass + scope-isolation suites); standalone **42 pass**, ZapMed **121 pass / 1 pre-existing unrelated fail**; AC-15 clean. ⏳ **`composer audit` in CI + manual pen-test/security review = Captain Zan (vendor) — FINAL go-live gate before real patient data.** Full record: `specs/spar-standalone/security-popia.md`. — est: M

> **Phase 10 status:** all codeable hardening DONE + tested. Remaining are real-world decisions Captain Zan owns: **MFA method, breach-notification process/owner, pen-test vendor, compliance sign-off.** Pre-staging env revert checklist in `security-popia.md` (APP_URL, OTP on, SESSION_SECURE_COOKIE).

## Build order (near-term, staging deferred until working+tested)
1. Phase 7 (hierarchy/roles/management, standalone) — security scope built-in
2. Phase 8 (stats, 3 scope views)
3. Phase 10 (security hardening & POPIA gate)
4. Testing → **staging deploy** (the deferred 5.5 + a standalone Forge site)
5. Phase 9 (ZapMed SSO) — LATER, after standalone is live & proven

---

## Sequencing notes
- **Phase 1 is new** in this revision: it delivers the agreed product behaviour (dual-mode onboarding, consent gate, no-login patient app, dependant roll-up, real reminders via available channels, admin consent section, renewal funnel) **inside ZapMed first**, where it's low-risk and immediately testable, before any package extraction.
- Phases 2→3 are the extraction seams; both stay inside the ZapMed repo and keep ZapMed working the whole time.
- Phase 4 is the first genuinely new app. Phase 6 (WhatsApp) is deferred until a provider exists.
- Regression gates (baseline from 0.3) are checked at 1.14, 2.5, 3.6, and 5.1 — if any fails, stop and fix before moving on.

## Compliance / process flags (not code)
- Adult-dependant medication visible to the principal member (design §8.3) and the exact consent wording/version must be signed off by the compliance officer before wide rollout. Fine for the pharmacy pilot.
- WhatsApp templates require Meta approval before proactive sends (Phase 6).
- Return-path for renewal scripts into SPAR's own system (brief #4) still to be confirmed with the client; pilot uses internal handoff only.
