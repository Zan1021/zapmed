# Task 7 — Parity Gap-Analysis: Mark's CRM backend vs our Laravel app

**Author:** Naz · **Date:** 2026-09-12
**Inputs:** doc `01-system-design-dossier.md` (their model) + direct read of our Laravel models in
`app/Models` (our model). This produces the **must-build-before-import** backlog.

> Scope note: this compares *domain capability*, not line count. "Our app" = `C:\Users\zande\Documents\
> Zapmed\zapmed` (Laravel 12 / PHP 8.2). Where I actually opened the model I say so; where the assessment
> is inferred from model names only I flag it **(unverified)**.

## Legend

- ✅ **Have** — exists in our app at comparable fidelity; import can target it as-is.
- 🟡 **Partial / transform** — exists but shape differs; needs extension or a mapping layer.
- 🔴 **Must-build** — no meaningful equivalent; blocks a faithful import of that entity.

## Module-by-module gap matrix

| Their module | Our Laravel equivalent | Status | Notes |
|---|---|---|---|
| identity | `User` + Laravel roles + 2FA OTP | 🟡 | We have users/roles/2FA. They have principals + RBAC scopes + MFA factors + partner API keys + DB-enforced RLS. For import we only need a `principal ≈ User` crosswalk; full RBAC/RLS parity is a later hardening item, not an import blocker. |
| patient_profile | `PatientProfile`, `PatientAllergy`, `PatientChronicCondition`, `PatientGoal` | 🟡 | Demographics/allergies/conditions exist. Need to confirm coverage of **medical-aid scheme/number/plan**, `sex_at_birth`, `home_language`, E.164 `mobile_msisdn`, geocoded addresses with labels, and a `payment_type` field. Add `upstream_id` for the userHash crosswalk. |
| catalog | `Medication`, `SubscriptionPlan` | 🔴 | We have medications + plans but **no product catalog** with NAPPI / packQty / master-bundle / requiresSubscription / time-versioned pricing / tax classes / **coupons + coupon usage** / service lines. Contro `products` + `coupons` import needs this. |
| orders | `Appointment` + `Payment` (+ `Prescription`) — **verified** | 🔴 | **Biggest gap.** `Appointment` has a flat 6-value status (pending/confirmed/in_progress/completed/cancelled/no_show). Contro has a **25-status order lifecycle** with an immutable status history and a transition-rules state machine seeded as data. There is no `Order` aggregate at all. Contro's `orders` + `order_status_history` cannot be faithfully imported without building this. |
| payments | `Payment` — **verified** (`provider='payfast'`, `amount` int cents, ZAR) | 🟡 | Ledger exists and already uses cents/ZAR. Need: `upstream_id`, `idempotency_key`, `payment_attempt`/`refund`/`webhook_event` children, and Contro fields (`retry_count`, `is_repeat_charge`, `medical_aid_claim_status`, allow zero-amount pending rows). **Gateway mismatch: we=PayFast, their enum=peach — confirm.** |
| consultations | `Consultation`, `Appointment`, `DoctorAvailability`, `DoctorBlockedDate`, `VideoSession` — **verified** | 🟡 | Richer than first assumed. `Consultation` already has ICD-10, encrypted clinical fields, and a field-level internal/patient-visible split (`INTERNAL_FIELDS` / `PATIENT_VISIBLE_FIELDS` / `patientVisibleData()`). Gaps vs theirs: materialised **slot** model with no-overlap exclusion, consult-type enum, and the richer status machine (checked_in/rescheduled). Not an import blocker for Contro (Contro exposes no consult entity). |
| clinical | `Consultation` (fields) + `Prescription` + `PrescriptionItem` — **verified** | 🟡 | We do NOT have a separate `clinical_review` aggregate with outcome enum (approved/declined/info_requested/referred_out) or an `AwaitingInformation` info-request flow. But `Consultation` already encodes decision/notes and the internal/visible split, and `Prescription` already has HPCSA number, chronic/repeats/repeats_used, valid_until, pharmacy dispatch. So clinical is **partial**, not absent. To import Contro `prescriptions` we mainly need: `upstream_id`, `repeat_cycle_days`, `next_repeat_date`, `pharmacy_script_ref`, per-item `unit/total price`. The **review/notes** entity is only needed if the Contro DB dump turns out to contain doctor notes (API does not). |
| pharmacy | `Pharmacy`, `Prescription.pharmacy_*` fields, generic `PharmacyWebhookController` (`api/pharmacy/status`) | 🔴 | We have a simple pharmacy-status webhook + dispatch fields on the prescription. Theirs is a **RxHub-specific** signed/deduped pipeline with `dispatch`/`delivery`/`claim`/`rxhub_event` and medical-aid claim rejection codes. Outbound submit is unbuilt on both sides (needs RxHub docs). For import this is lower priority (Contro exposes no pharmacy entity beyond `pharmacyScriptReference` on orders/prescriptions). |
| subscriptions | `Subscription`, `SubscriptionPlan` — **(unverified, models exist)** | 🟡 | We have subscription models. Theirs adds the **repeat lifecycle**: cycle (scheduled→attempted→placed→failed/fulfilled), 3-strike `consecutive_failures`, follow-up-at +180d, pause events. Contro `orders.isSubscription` + `nextRepeatDate` import needs at least the cycle/next-run concept. Verify our models before sizing. |
| notifications | Laravel Mail/Notifications + SPAR WhatsApp channel | 🟡 | We send email/SMS/WhatsApp but have no first-class `template`/`opt_out`/`throttle`/`bounce` schema. **Not an import blocker** — and import must NOT send anything (see doc 03). |
| partners | `Partner`, `Commission`, `Payout`, `Referral` — **(unverified, models exist)** | 🟡 | We have a partner/affiliate concept (commissions/payouts/referrals) — different flavour from their partner-API-key/webhook-subscription model. Not an import blocker. |
| compliance | `ConsentRecord`, `SparConsent` — **(partial)** | 🟡 | We record consent. Theirs is full POPIA: consent purposes, DSAR workflow, retention policies + legal hold. Compliance parity is a **go-live** requirement (POPIA), not an import blocker, but must be on the roadmap. |
| audit | (no dedicated append-only audit model found) | 🔴 | We rely on soft-deletes/timestamps. Theirs is an append-only, monthly-partitioned, 7-year audit trail + integration log. Needed for HPCSA/POPIA parity at go-live. |
| analytics | (marketing metrics via app/reporting; no funnel schema) — **(unverified)** | 🟡 | Their funnel/attribution/ad-spend/KPI snapshots are an ops feature, not import-critical. |
| alerts | (none found) | 🔴 | Their SLA/threshold alert engine (Jess's morning board) has no equivalent. Ops feature, not import-critical. |
| crm | (none — this is the Airtable-replacement core) | 🔴 | Lead funnel, R/A/G health score, flags, risk score, AI nudges. No equivalent. This is the heart of *their* Phase 1 and the main thing Craig is currently *renting*. Big build if we want parity. |
| coaching | `PatientGoal`, `ProgressLog` — **(loosely related, unverified)** | 🔴 | Their coach journey (assignment/touchpoint/offer) has no real equivalent. Ops feature. |
| ai_assist | `AiConversation`, `AiKnowledgeEntry` — **(unverified)** | 🟡 | We have AI models; theirs is summaries/risk/nudges (code-only). Feature-level, not import-critical. |
| finance_reports | (reporting exists ad hoc; no revenue/recon schema) | 🟡 | Their revenue-entry + PayFast↔pharmacy reconciliation is a finance feature. Not import-critical, but note the same PayFast/peach flag. |
| ops_console | Laravel admin (Livewire) + SPAR admin | 🟡 | Different UI stack; behavioural parity only. |
| upstream_sync | (none) | 🔴 | **We must build this to import at all** — the Contro ELT staging + reconcile. Detailed in doc 03. |

## The must-build-before-import backlog (ranked)

Ordered by what actually blocks a faithful Contro import (not by feature glamour):

1. **`upstream_sync` ingestion + staging** (🔴, import-critical). Mirror their ELT: raw JSONB staging
   tables keyed `(entity_set, upstream_id)`, watermark state, per-run audit. Nothing imports without this.
2. **`Order` aggregate + 25-status state machine + immutable status history** (🔴, import-critical).
   The spine. Required to land Contro `orders` and `order_status_history` with integrity. Seed the
   transition rules as data; add a transition validator.
3. **`catalog` (products + coupons + coupon_usage)** (🔴, import-critical). Required for Contro
   `products` and `coupons`. NAPPI, packQty, master-bundle, requiresSubscription, time-versioned price.
4. **`payments` extensions** (🟡, import-critical). `upstream_id`, `idempotency_key`, attempts/refunds,
   Contro fields, allow zero-amount. Resolve the PayFast/peach gateway question first.
5. **`patient_profile` extensions** (🟡, import-critical). Medical-aid fields, E.164, labelled/geocoded
   addresses, `payment_type`, and `upstream_id` for the userHash crosswalk.
6. **`clinical_prescription` extensions** (🟡, import-critical for `prescriptions`). `upstream_id`,
   repeat_cycle_days, next_repeat_date, pharmacy_script_ref, per-item unit/total price.
7. **`clinical_review` + `clinical_note` (is_internal)** (🟡, *conditionally* import-critical). Only if the
   Contro DB dump contains doctor notes/reviews. The Contro API exposes none. Our `Consultation` already
   has a field-level internal/visible split we can extend or promote to a row-level notes model.
8. **`upstream_id` crosswalk columns** on every table that receives Contro data (patients, orders,
   order_items, order_status_history, payments, products, coupons, coupon_usage, prescriptions,
   prescription_items) with a `UNIQUE(upstream_source, upstream_id)` index — the idempotency backbone.

**Go-live (not import) parity, lower urgency:** audit trail, POPIA consent/DSAR/retention, RLS-equivalent
authorization, pharmacy/RxHub pipeline, subscriptions cycle lifecycle, CRM health-score/alerts/coaching,
finance reconciliation.

## Honest caveat

Building all 21 modules to true parity in Laravel is a very large effort — their own stats put Phase 1 at
~86k LOC across 21 modules. The **import** needs only items 1–8 above. **Full CRM/ops parity** (crm, alerts,
coaching, analytics, audit, compliance) is a separate, much bigger program of work that feeds directly into
the strategic decision in doc 04. We should not pretend items 1–8 equal "we've replaced Contro."
