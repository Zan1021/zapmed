# SPAR Group Promo Banners — Task List

**Status:** IN PROGRESS (2026-09-11) · **Requirements:** `requirements.md`
**Decisions:** per-group only · after consent · impressions+clicks · GD→WebP (confirmed) · 1080×420 · max 5.
Build in order; tests green at the end.

## Phase 1 — Data
- [ ] 1.1 Migration `spar_banners` (group_id, title, image_path, link_url, sort_order, is_active,
      starts_at, ends_at, impressions default 0, clicks default 0, timestamps). Idempotent.
- [ ] 1.2 `SparBanner` model (package): fillable, casts (dates/bool/int), `group()` relation,
      scopes `active()` + `liveNow()` (is_active + within starts/ends window), `scopeForGroup`.

## Phase 2 — Image pipeline
- [ ] 2.1 `SparBannerImageService` (package): validate (mimes jpg/png/webp, ≤5MB), decode via GD,
      cover-fit resize to 1080×420, `imagewebp()` ~78%, store on 'public' disk under spar-banners/.
      Runtime capability check → graceful fallback (store validated original) if no GD-WebP (NFR-2).
- [ ] 2.2 Config `spar.banners` (width, height, quality, max_per_group, disk).

## Phase 3 — Group-admin management screen
- [ ] 3.1 `Admin\SparBanners` Livewire (package): group scope via SparIdentityProvider (super picks a
      group; group-admin locked to own; others 403). Upload (WithFileUploads) + preview + size hint,
      title, link_url, reorder (sort), activate/schedule, delete. Enforce ≤5 active. Audit-logged.
- [ ] 3.2 Route `admin.spar.banners` (admin mw) + view; nav link for super/group admin.
- [ ] 3.3 Per-banner impressions/clicks shown on the screen.

## Phase 4 — Mobi slider + tracking
- [ ] 4.1 Slider partial on `my-meds-tracker` (AFTER consent, under logo): patient's group's
      liveNow() banners, ordered, lazy WebP, Alpine/CSS carousel, capped 5. Resolve the patient's
      group via their pharmacy.
- [ ] 4.2 Impression increment when rendered (batch increment the shown ids).
- [ ] 4.3 Public tracked click route `spar.banner.click/{banner}` → increment clicks → 302 to link_url.

## Phase 5 — Tests + verify + docs
- [ ] 5.1 Tests: upload→WebP + 6th rejected (AC-1); group A sees A not B, only after consent (AC-2);
      click redirect increments (AC-3); impression increments + admin sees counts (AC-4); scope (AC-5).
- [ ] 5.2 Full suites green (standalone + ZapMed SPAR); grep gate no host class in package.
- [ ] 5.3 Docs (DEMO.md) + demo seed a sample banner for the demo group so the slider shows.
