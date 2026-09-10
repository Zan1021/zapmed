# SPAR Staff Patient Detail — Task List

**Status:** IN PROGRESS (2026-09-11)
**Requirements:** `requirements.md`
**Strategy:** Reuse the mobi tracker's data assembly (one source of truth). Read-only + scope-gated
+ audit-logged. Tests green at the end. Build in order.

Legend: `[ ]` todo · `[x]` done · ⚠ = risk/verify

## Phase 1 — Provenance data
- [ ] 1.1 Migration: add `onboarding_pharmacy_id`, `captured_by_id`, `captured_at` (all nullable)
      to `spar_patients` (idempotent, cross-driver). ⚠ FR-1
- [ ] 1.2 `SparPatient`: add to `$fillable` + cast `captured_at` datetime; `onboardingPharmacy()`
      relation; keep `captured_by_id` a plain id (no host class — AC-3). — 
- [ ] 1.3 Populate on write: `PharmacistCapture::save()` sets captured_by_id (auth id)/captured_at/
      onboarding_pharmacy_id (if empty); `SparImportService` sets onboarding_pharmacy_id on CREATE
      only (not on national re-attach). ⚠ FR-7/AC-6

## Phase 2 — Shared roll-up assembly (no drift)
- [ ] 2.1 Extract the household roll-up (primary resolution, profile members, journeys, dispense
      history, renewal-due) from `MyMedsTracker`/`MyMedsHistory` into a shared
      `SparPatientView` service (package). ⚠ FR-3
- [ ] 2.2 Re-point `MyMedsTracker` + `MyMedsHistory` at the shared service; confirm their tests
      still pass (no behaviour change). ⚠ NFR-4

## Phase 3 — Staff patient detail screen
- [ ] 3.1 Route `spar.patients.show/{patient}` (staff middleware) + `PatientDetail` Livewire
      (package). mount() resolves via `visibleToCurrentActor` (404 if out of scope) + writes a
      `patient_access` audit entry. ⚠ FR-2/NFR-1/NFR-2/AC-4/AC-5
- [ ] 3.2 View: TOP = read-only patient mirror (reuses shared assembly + the tracker partial,
      action buttons omitted/disabled, consent-state badge). ⚠ FR-4/FR-6/AC-1/AC-3
- [ ] 3.3 View: BELOW = staff provenance panel — dependants, onboarding pharmacy + pharmacist
      (name resolved host-side) + captured_at, consent trail (SparConsent), needs_identity_review
      badge+reason, contact, per-journey pharmacy. ⚠ FR-5/AC-2
- [ ] 3.4 `PatientList` blade: make the patient name a link to the detail route. ⚠ FR-2
- [ ] 3.5 Host name resolution: a small host-provided way to turn captured_by_id → a display name
      without the package naming the host user model (config callable or SparIdentityProvider
      helper). Standalone = PharmacyUser; integrated = User. ⚠ NFR-3

## Phase 4 — Tests + verify + docs
- [ ] 4.1 Tests: detail shows mirror data (AC-1); staff panel shows dependants/onboarding/pharmacist
      /consent trail (AC-2); no patient actions operable (AC-3); open logs patient_access (AC-4);
      out-of-scope open blocked (AC-5); provenance populated by capture + import (AC-6).
- [ ] 4.2 Full suites green (standalone + ZapMed SPAR). Grep gate: no host user class in package.
- [ ] 4.3 Docs: note the staff detail view in imports.md/DEMO.md; seed demo so a patient has an
      onboarding pharmacist + dependants for the demo.
