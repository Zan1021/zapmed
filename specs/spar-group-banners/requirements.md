# SPAR — Group Promo Banners (mobi slider)

**Status:** Draft for review · **Author:** Naz · **Date:** 2026-09-11
**Related:** `specs/spar-standalone/`, `specs/spar-staff-patient-view/`

---

## 1. Idea

A per-group advertising slider at the top of the patient mobi tracker (under the SPAR logo,
above the meds). SPAR **group admins** upload up to 5 banner images with an optional click-through
URL; images are auto-converted to compact WebP for mobile. Shown to patients in that group AFTER
consent. Impressions + clicks are tracked so a group sees ad performance.

## 2. Decisions (locked with Captain Zan 2026-09-11)

- **Scope: per GROUP only.** A banner belongs to a `spar_pharmacy_group`. Not per-pharmacy, not
  platform-wide. A patient sees the banners of THEIR pharmacy's group.
- **Placement:** top of the mobi tracker, under the logo, **only AFTER consent** (never on the
  consent gate — no ads on a medical consent screen).
- **Tracking:** impressions + clicks recorded, surfaced to the group admin.
- **Format:** upload any JP/PNG/WebP → server converts to **WebP** (GD, confirmed available),
  resized to the banner spec, smallest reasonable size for mobile.
- **Recommended size:** 1080×420 (≈2.6:1 landscape). **Max 5 active slides per group.**

## 3. Functional Requirements

- **FR-1** `spar_banners` belongs to a group: `group_id`, `title` (internal), `image_path` (WebP),
  `link_url` (nullable), `sort_order`, `is_active`, `starts_at`/`ends_at` (nullable schedule),
  `impressions`, `clicks`.
- **FR-2** Group-admin management screen (scope-enforced to their own group): upload with the
  recommended size shown + live preview, set link URL, reorder, activate/deactivate, schedule,
  delete. Super-admin may manage any group's banners.
- **FR-3** Image pipeline: validate (type/size), resize to the banner spec (cover-fit to
  1080×420), encode WebP (~78% quality), strip metadata, store on the public disk. Enforce ≤5
  active per group.
- **FR-4** Mobi slider: render the patient's group's active, in-window banners (ordered) under the
  logo, AFTER consent. Lazy-loaded WebP. If a banner has a `link_url`, the slide is clickable.
- **FR-5** Tracking: increment `impressions` when the slider renders a banner; a click goes via a
  tracked redirect (`spar.banner.click/{banner}`) that increments `clicks` then 302s to `link_url`.
- **FR-6** Group-admin sees per-banner impressions/clicks on the management screen.

## 4. Non-Functional

- **NFR-1** Host-agnostic: lives in `spar-core`; no host user class referenced (AC-3/AC-15). Group
  scope resolved via the bound `SparIdentityProvider`.
- **NFR-2** Graceful WebP fallback: if a server lacks GD-WebP, store the validated original and log
  a warning rather than hard-failing the upload (deploy-verify item).
- **NFR-3** Banners are NOT PHI — public disk, no encryption. Keep visually distinct from meds so an
  ad is never mistaken for health advice.
- **NFR-4** Lightweight on the mobi page (lazy-load, capped at 5, small WebP) — never slows the meds view.
- **NFR-5** No regressions; suites stay green.

## 5. Acceptance Criteria

- **AC-1** A group admin uploads a JPG/PNG → it is stored as a resized WebP; a >5th active banner is rejected.
- **AC-2** Patients in group A see group A's active banners under the logo AFTER consent; not before consent; not group B's.
- **AC-3** A banner with a link_url is clickable; the click goes through the tracked redirect and increments clicks.
- **AC-4** Rendering a banner increments impressions; the group admin sees impressions/clicks.
- **AC-5** Group admin only manages their own group's banners (scope-enforced); super-admin any.
- **AC-6** Package has no host user class (grep gate); all existing SPAR suites pass; new tests cover AC-1..AC-5.
