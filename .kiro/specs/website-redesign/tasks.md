# Website Redesign — Tasks (align live app to Craig's mockup)

**Project:** ZapMed · `C:\Users\zande\Documents\Zapmed\zapmed` (Laravel 12 + Livewire 3 + Tailwind).
**Source of truth (design):** `C:\Users\zande\Documents\Zapmed - Craig_New_Files\Zapmed-Complete-Website-Mockup-2026-10-30\zapmed-website\` (static mockup, 30 Oct 2026).
**Related:** charge-at-booking journey = the OTHER folder `Zapmed_End_to_End_Design_v15\...\dist\` (+ its `research.md`). Vault: `2026-11-05_mem_craig-two-design-folders.md`.

---
## ▶️ START HERE (status 2026-11-05)
## ▶️ START HERE (status 2026-11-05, part 2 — HOMEPAGE PORTED via Approach A)
Brand tokens already live. **Homepage now ported 1:1 from Craig's mockup** using Approach A (vendored
the mockup's own CSS/JS, scoped to `.zapmed-home`). Done + browser-verified (categories, weight-loss,
Prata emphasis, mega menu on top, real routes). Captain Zan to REVIEW the whole page.

**What was built this pass:**
- `resources/css/zapmed-home.css` — mockup's 11 stylesheets concatenated in cascade order, every selector
  scoped under `.zapmed-home` (`:root`/`@font-face`/`@media` preserved), `assets/`→`/mockup-assets/`.
- `resources/js/zapmed-home.js` — mockup's app.js+metrics+vertical+refinements, mojibake fixed,
  `assets/`→`/mockup-assets/`. Injects categories/benefits/team/reviews/faq + BMI & weight calculators.
- `resources/views/welcome.blade.php` — rebuilt: our head+fonts+**mega menu**+analytics/pwa, loads the
  two bundles, wraps the mockup body in `<div class="zapmed-home pt-16">`.
- `resources/views/partials/home-body.blade.php` — the mockup body (hero→footer), header/banner/skip
  stripped, mojibake fixed, `floating-start` CTA → `/treatments`.
- `public/mockup-assets/` — all 51 mockup assets copied in (used as-is).
- `vite.config.js` — added the two homepage inputs. Build green.

**REVIEW / FOLLOW-UPS (for Captain Zan):**
- Hero (#home) is the mockup hero as a PLACEHOLDER — Captain Zan is redesigning it. Swap when ready.
- `data-topic`/`data-plan`/`data-info` buttons open the mockup's concept dialogs (local only). Decide
  whether to rewire these to real routes (treatments / register / legal pages) or keep as-is.
- Footer is the mockup footer (concept legal links as buttons). Wire to real legal routes if wanted.
- Copy/claims in the mockup (7,000+ patients, 4.8★ Trustpilot, SAHPRA, prices) are UNVERIFIED design
  text — the page keeps the mockup's own "requires verification" notes + #verification block. Replace
  with approved copy before launch.
- Prices (R220/R450) are hardcoded mockup values — bind to our real plans (cents) later if wanted.
- Mega menu items (How It Works/Pricing/Our Doctors) anchor to #how-it-works etc — confirm anchors line
  up with the new section ids (#how-it-works exists; there's no #pricing/#doctors — mega menu still uses
  old anchors). MINOR: align nav anchors to new ids (membership/care-team) in a follow-up.
- Mobile pass at 390px + full QA still to do (Phase 10).
- Emails/PDFs green cleanup still outstanding (separate).

Backup before this work: `_backups\website-redesign_pre_2026-11-05_130533`.

---
## (original plan below)
Brand tokens already live (teal/ivory, Manrope+Prata, central `resources/css/app.css`). Our homepage
(`resources/views/welcome.blade.php`, ~954 lines) ALREADY has every major section. So this is an
**ALIGN-to-mockup** job (layout, copy, imagery, spacing, interactions) — NOT a rebuild.

**Sequence: FRONTEND FIRST** (Captain Zan). Charge-at-booking is a separate later spec.
**Approach:** section by section. For each: open the mockup section in the browser side-by-side with our
live page, port layout/copy/image, keep OUR data/routes/Livewire wiring, verify in browser, move on.
Work on a copy/branch-safe basis; commit per logical chunk only on "check out".

---
## Phase 0 — Groundwork
- [ ] 0.1 Serve the mockup locally (`python -m http.server 8000` in the mockup folder) for side-by-side.
- [ ] 0.2 Inventory diffs: list each mockup homepage section vs our `welcome.blade.php` equivalent
      (hero, category strip+search, how-it-works, weight-loss, calculators, health-coach, care-team,
      membership, men's, women's, reviews, FAQ, verification). Note what differs (copy/layout/image).
- [ ] 0.3 Copy the mockup's assets into the app and USE AS-IS (Captain Zan: no approval gate — staff
      portraits, AI men's portrait, women's portrait, all photos ship directly).
- [ ] 0.4 Back up `welcome.blade.php` + treatment views before edits.

## Phase 1 — Global shell (nav, footer, type scale)
- [ ] 1.1 **KEEP our existing mega menu** (Captain Zan decision) — do NOT replace nav with the mockup's
      flat header. Only restyle the mega menu to the brand if it needs it.
- [ ] 1.2 Footer → match mockup footer (columns, fine print, concept/legal lines as appropriate).
- [ ] 1.3 Type scale + spacing: confirm Prata headline usage + Manrope body matches mockup rhythm.

## Phase 2 — Hero + trust row  ⏭️ SKIPPED (Captain Zan is redesigning the hero separately — do NOT port)
- [~] 2.1 Hero — SKIP. Leave our current hero; Captain Zan owns this.
- [~] 2.2 Primary CTA style — applies elsewhere; CTAs point at OUR existing booking/assessment flow.
- [~] 2.3 Trust/stat row — revisit with the hero redesign.

## Phase 3 — Care categories + search
- [ ] 3.1 Category strip/cards to match mockup (imagery, hover, gradient scrim).
- [ ] 3.2 "What can we help you take care of today?" search box — keep OUR behaviour/routes.

## Phase 4 — How it works + Weight-loss section
- [ ] 4.1 "How it works" steps layout/copy.
- [ ] 4.2 Weight-loss section ("Weight loss is biology. Keeping it off is support.") layout + imagery.

## Phase 5 — Calculators
- [ ] 5.1 BMI calculator UI to match mockup (keep our JS/compute).
- [ ] 5.2 Weight-projection ("Imagine the possibilities") UI.

## Phase 6 — Health coach + Care/clinical team + Membership
- [ ] 6.1 Health-coach band ("The difference? Someone in your corner.") + free kickstarter card.
- [ ] 6.2 Clinical team section ("Meet the clinical team") — use mockup portraits as-is.
- [ ] 6.3 Membership / "A plan for your health" pricing cards (bind to OUR plans/fees in cents).

## Phase 7 — Men's / Women's sections
- [ ] 7.1 Men's health section — men's accent palette (navy/steel/dusty), full-bleed treatment.
- [ ] 7.2 Women's health section — women's accent palette (deep/soft/pale sage).

## Phase 8 — Reviews + FAQ + verification trust
- [ ] 8.1 Reviews scroller ("Care people come back to") — use OUR Testimonial data; mark unverified copy.
- [x] 8.2 FAQ accordion ("Your questions, answered") — keep our content/markup. x-collapse now works
      site-wide (installed @alpinejs/collapse, registered via alpine:init so it covers Livewire's Alpine too).
- [ ] 8.3 Verification / credentials trust block.

## Phase 9 — Treatment / category landing pages  ⏸️ PARKED (no mockup exists for these)
Craig's mockups have NO standalone treatment page (both folders are single-page). Captain Zan:
"leave it as is for now if there's no design indication." Our current `/weight-loss` etc. pages stay.
Revisit only when Craig supplies a treatment-page design.
- [~] 9.1 Align `treatments/show.blade.php` — PARKED (no design).
- [~] 9.2 Per-category men's/women's palette — PARKED.
- [~] 9.3 Link category pages to onboarding — PARKED (handle with charge-at-booking).

## Phase 10 — Responsive + QA
- [ ] 10.1 Mobile pass at 390px (mockup README inspected at 390px) across all sections.
- [ ] 10.2 Accessibility: contrast per moodboard pairings (primary button ≥ semibold 16px; avoid ivory on
      soft-sage/steel), focus states, alt text, heading order.
- [~] 10.3 Cross-check no old lime/stray colours; `npm run build`; browser-verify whole page.
      DONE (2026-11-06): console cleaned 81 err/7 warn → 0/0 on home, /help, /weight-loss. Brand fonts
      now SELF-HOSTED from /public/fonts (were blocked by CSP via bunny→jsdelivr). theme-color set to
      brand teal #2E767F. Still TODO: full lime-sweep of remaining pages + 390px (10.1).
- [ ] 10.4 Flag remaining approvals before launch: review excerpts/ratings, clinical claims, pricing,
      font/image licensing (per mockup README). NOTE: staff/portrait assets ship as-is per Captain Zan.

## OUT OF SCOPE (separate specs)
- Charge-at-booking payment flow (Folder 1 `dist/` + `research.md`) — AFTER frontend.
- Emails/PDFs still have hardcoded green — separate cleanup.
