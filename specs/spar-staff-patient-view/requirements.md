# SPAR — Staff Patient Detail (mobi mirror + provenance panel)

**Status:** Draft for review
**Author:** Naz (Team Lead)
**Date:** 2026-09-11
**Related:** `specs/spar-standalone/`, `specs/spar-national-identity/`

---

## 1. Problem / Idea

From the staff Patient List, clicking a patient's name currently does nothing. Staff should be
able to open a patient and see **exactly what that patient sees on their mobi tracker** (household
roll-up, medications, "Collected at" pharmacy, renewal state, collection history) — read-only —
so a pharmacist on a support call sees the patient's actual screen. Alongside that mirror, show a
**staff-only provenance panel**: dependants, the pharmacy the patient was onboarded at, the
pharmacist who onboarded them, consent audit trail, review flags, and contact details.

## 2. Goals

- **G1** Clicking a patient name opens a detail page that renders the SAME data the patient sees
  on their mobi tracker (one source of truth — reuse the tracker's roll-up assembly, no drift).
- **G2** The mirror is **read-only**: staff cannot act AS the patient (no consent grant/decline,
  no delivery request, no OTP). It is a view, not an impersonation.
- **G3** A staff-only panel shows: dependants under the profile, onboarding pharmacy, onboarding
  pharmacist, consent audit trail, `needs_identity_review` flag + reason, contact details, home
  /most-recent pharmacy, and per-journey pharmacy.
- **G4** Access is **scope-gated** (pharmacy/group/super, national-aware) and **audit-logged**
  (a `patient_access` PHI-read event per view).
- **G5** Clicking a dependant shows the household view (roll-up) with that member highlighted.

## 3. Non-Goals

- No "login as patient" / session impersonation.
- No new patient-facing behaviour (the mobi tracker itself is unchanged).
- Editing patient identity stays in PharmacistCapture, not here (this view is read + provenance).

## 4. Data gaps to close (audited from code)

Today the system does NOT cleanly store some of what the steer asks for:
- **Onboarding pharmacist:** only inferable from the consent `source` string (`pharmacist:<id>`)
  and the `patient_captured` audit log — no first-class field.
- **Onboarding pharmacy:** `spar_patients.spar_pharmacy_id` is now the "home/most-recent" pharmacy
  (national identity, Phase 3), which may differ from where the patient was first onboarded.

**FR-1** Add first-class provenance columns to `spar_patients`:
  `onboarding_pharmacy_id` (nullable FK-ish, set once at first capture/import),
  `captured_by_id` (nullable — the PharmacyUser/User id who captured), `captured_at` (nullable).

## 5. Functional Requirements

- **FR-2** `PatientList` rows link the patient name to `spar.patients.show/{patient}` (scope-gated).
- **FR-3** `PatientDetail` Livewire component renders the patient-mirror using a SHARED roll-up
  assembly extracted from `MyMedsTracker`/`MyMedsHistory` (a `SparPatientView` service or trait),
  so staff and patient views cannot drift.
- **FR-4** Read-only: patient-action controls are omitted/disabled with a "patient-only" note.
- **FR-5** Staff provenance panel: dependants list, onboarding pharmacy + pharmacist + date,
  consent status + full `SparConsent` trail, `needs_identity_review` badge + reason, contact
  details, journeys each labelled with their pharmacy.
- **FR-6** Consent-state honesty: badge whether the patient has consented / is at the consent gate,
  so staff know what the patient currently sees vs. what staff can see.
- **FR-7** Populate the new provenance fields: `PharmacistCapture` sets `captured_by_id`/`captured_at`
  /`onboarding_pharmacy_id`; the importer sets `onboarding_pharmacy_id` on patient creation.

## 6. Non-Functional

- **NFR-1** Every open logs a `patient_access` audit entry (who/what/when) via `LogsSparActivity`.
- **NFR-2** Scope isolation preserved (national-aware `visibleToCurrentActor`) — 404/redirect if
  a staffer opens a patient outside their scope.
- **NFR-3** No host-user class in the package (AC-3/AC-15): `captured_by_id` is a plain id;
  resolving the pharmacist's *name* is done via the host (SparIdentityProvider or a host lookup),
  not by the package naming `PharmacyUser`/`User`.
- **NFR-4** No regressions; existing suites stay green.

## 7. Acceptance Criteria

- **AC-1** Clicking a patient name opens a detail page showing the same meds/roll-up/"Collected at"
  /renewal/history the patient sees on their tracker.
- **AC-2** The staff panel shows dependants, onboarding pharmacy, onboarding pharmacist + date,
  consent trail, review flag, and contact.
- **AC-3** No patient-action buttons are operable for staff (read-only mirror).
- **AC-4** Opening a patient writes a `patient_access` audit entry.
- **AC-5** A staffer cannot open a patient outside their scope (national-aware).
- **AC-6** New provenance fields are populated by both capture and import paths.
- **AC-7** All existing SPAR suites still pass; new tests cover AC-1..AC-6.
