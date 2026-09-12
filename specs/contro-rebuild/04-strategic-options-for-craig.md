# Task 9 — Strategic Options + Questions for Craig

**Author:** Naz · **Date:** 2026-09-12
**Context:** Craig currently *rents* his telehealth/CRM system from Contro (live, with real production
data). He has paid for (a) our Laravel telehealth app and (b) Mark/TMC-77's rebuilt TS CRM backend
(`Zapmed_CRM`). The goal is to build Craig an **owned** system so he stops renting, and to import all
Contro data before go-live. The **direction is not yet decided** — this document frames the choice.

> **CONFIRMED by Captain Zan (2026-09-13):**
> - **Ownership is settled — this is fully Craig's system. He paid for it and owns everything**
>   (our Laravel app AND Mark's `Zapmed_CRM` backend). There is **no licensing constraint**. Options B
>   and C are fully available; the earlier "confirm ownership" blocker (old Q2) is **closed**.
> - **The Contro database dump is expected in a few days.** Until it lands, the import blueprint stays
>   API-complete; clinical-notes mapping remains `⟨dump⟩`.
> - **Contro (the CRM) is the main system we work from — the source of truth.** Map ours → theirs.

> Read alongside: `01-system-design-dossier.md` (their backend), `02-parity-gap-analysis.md` (gaps),
> `03-contro-import-blueprint.md` (import).

---

## 1. The decision

**Where does Craig's owned system live?** Three viable options:

### Option A — Laravel to parity
Extend our existing Laravel app until it covers the domains Craig needs, import Contro into it.

- **Pros:** one codebase we already own and run; one stack for the team; the patient-facing app already
  exists and works; full control; no relicensing questions.
- **Cons:** the CRM/ops half (crm health-score, alerts, coaching, analytics, audit partitioning,
  compliance/DSAR, RLS) is a **large** build — their Phase 1 is ~86k LOC across 21 modules. We'd be
  re-implementing a lot of what Craig already paid Mark to build. Slowest path to *full* parity.

### Option B — Adopt Mark's TS backend, build the patient UI on top
Make `Zapmed_CRM` the system of record; our Laravel app (or a new front-end) becomes the patient face.

- **Pros:** the CRM/ops platform, Contro sync pipeline (`upstream_sync`), clinical model, RLS security,
  audit/compliance and reconcile hooks **already exist** and are the closest thing to what Craig rents
  today. Fastest path to *ops* parity. Import work is mostly finishing their phase-2 reconcilers.
- **Cons:** a second stack (Node/TS + Postgres RLS) the team must own and operate; **licensing/ownership
  of Mark's code must be confirmed** (Craig paid for a repo — clarify the terms). The patient-facing app
  is not in this repo (Craig's `Zapmed_telehealthplatform` repo is empty), so we still build/point a
  front-end at it. Two-system operational overhead unless we retire the Laravel app.

### Option C — Hybrid
Laravel stays the patient-facing app; adopt the TS backend for CRM/ops + Contro sync; integrate the two.

- **Pros:** keep the working patient app; get the ops platform without rebuilding it; play each stack to
  its strength.
- **Cons:** an integration seam between two systems (shared identity, order/payment truth, event flow) —
  real complexity and a source of drift. Highest *operational* overhead of the three.

## 2. Recommendation (for discussion, not a decision)

Sequence the work so we **de-risk the irreversible part first** regardless of A/B/C:

1. **Build `upstream_sync` staging + the reconcile step and run a read-only backfill from Contro now**
   (doc 03). This is valuable under *every* option — it gives us Craig's real data, proves the mapping,
   and surfaces the quarantine/anomaly reality before any architectural commitment. It touches nothing
   live and sends nothing.
2. **In parallel, get the remaining blocking answer from Craig:** the PayFast/peach gateway question.
   *(Ownership is already confirmed — Craig owns everything, so B/C are on the table.)*
3. **Then choose:** if Mark's code is fully Craig's to use and the team can own a TS stack → **B or C** is
   the pragmatic route to replacing what he rents (the CRM/ops platform is the expensive part and it's
   already built). If licensing is murky or we want a single stack we control end-to-end → **A**, accepting
   a longer build for full ops parity.

My honest read: the CRM/ops platform is the bulk of the value Craig is renting, and it *already exists* in
Mark's backend. Rebuilding all of it in Laravel (Option A) is a lot of work to arrive where Mark already is.
So B/C look more efficient **if** the licensing is clean and we're willing to run TS. But that's Craig's
call on ownership and yours on what the team wants to operate.

## 3. Questions for Craig (blocking)

1. **Gateway:** Is the live payment provider **PayFast** or **Peach**? Their `payments` enum says `peach`;
   `finance_reports` says PayFast; our Laravel uses PayFast. This blocks the payments mapping.
2. ~~**Ownership/licensing of Mark's `Zapmed_CRM`:**~~ **RESOLVED — Craig owns everything** (paid for
   it). We may use/modify/deploy freely. Options B and C are available.
3. **Contro DB dump:** ~~Can Craig purchase the full DB dump?~~ **Confirmed coming — expected in a few
   days.** It's the **only** source for doctor/clinical notes and any DB-only fields; notes mapping is
   blocked until it lands.
4. **Do clinical/doctor notes even exist in Contro?** The API exposes none. If they don't exist, that
   simplifies scope; if they do, we need the dump.
5. **Contro access:** service-account credentials + the **production HTTPS** base URL (Swagger lists
   `http://`), plus the login response token-field shape and whether JWE response encryption will be on.
6. **RxHub:** can Craig obtain RxHub's API docs (outbound submit-script + auth)? Needed for live pharmacy
   fulfilment (not for the historical import).
7. **Cutover expectations:** is a Contro **write-freeze** window acceptable at cutover, and how long can
   Contro stay available read-only as a fallback?
8. **Ops-team scope:** which CRM/ops features does Craig actually need on day one (order board, health
   score, coaching, alerts, finance reports)? This sizes A vs B/C materially.

## 4. Open technical flags (carry into whichever option)

- Payment gateway inconsistency (Q1).
- Contro exposes no clinical-notes entity — notes are DB-dump-only (Q3/Q4).
- Contro `userHash` is the only patient key — crosswalk on it, never guess identity.
- Money: Contro doubles(rands) → canonical minor units(cents), banker's rounding.
- Contro int64 ids arrive as strings (IEEE754Compatible header) — keep as text.
- POPIA/HPCSA: audit trail + consent/DSAR/retention are go-live requirements under any option.

## 5. Security / housekeeping reminder

- A `ghp_` GitHub PAT was pasted in chat in a prior session — **revoke it** at
  https://github.com/settings/tokens if not already done.
- `zapmed-crm` and `zapmed-client` are cloned **read-only, push disabled** — do not push to Craig's repos.
