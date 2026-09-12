# Task 6 — System-Design Dossier: ZapMed_CRM (Mark's TS backend)

**Author:** Naz · **Date:** 2026-09-12
**Source of truth:** `C:\Users\zande\Documents\Zapmed\zapmed-crm` (read-only clone), verified 100% at
schema / security / integration / architecture level (44/44 migrations, 19/19 RLS, 13/13 contracts,
55/55 events, kernel core, Contro API decode).
**Purpose:** A single consolidated reference for the rebuilt CRM backend Craig purchased. This is the
canonical model our owned system must be able to represent before we import Contro's live data.

> Companion docs in this folder:
> - `02-parity-gap-analysis.md` — their model vs our Laravel app → must-build backlog
> - `03-contro-import-blueprint.md` — how we import Contro's live data
> - `04-strategic-options-for-craig.md` — decisions Craig must make

---

## 1. What this system is

Per the backend's own shipped roadmap (`ops-console/src/lib/roadmap-data.ts`):

- **Phase 1 = a CRM/ops intelligence platform to replace Airtable** for the ops team ("Jess"). It is
  **not** a patient-facing app. It surfaces a live order board, patient CRM with a R/A/G health score,
  a daily coaching list, a revenue dashboard, a conversion funnel and subscription health.
- Stated delivery stats: ~86,446 LOC, 21 domain modules, 84 migrations, 14 admin console pages.
- People: **Jess** (ops/primary user), **Amy** (coach), **Dave** (owns Contro + its write API),
  **Craig** (client/decision owner), **Mark/TMC-77** (developer).
- The backend's own roadmap already flags an **open architecture decision**: "Contro as system of record"
  vs platform-independent. That is the same decision we must resolve with Craig (see doc 04).

## 2. Architecture

- **Stack:** Node 22 / TypeScript, npm workspaces monorepo, Postgres + Redis, Paseto v4 auth.
- **Kernel** (`kernel/src`): bootstrap, dependency-ordered module loader/registry, zod-validated config
  loader (`ZAPMED_<MODULE>_<KEY>`, fail-fast), per-module Postgres pools + checkssummed migrator,
  auth (Paseto tokens, policy engine `can`/`assert`, scope guards), crypto (P-256 hybrid envelope for
  decrypting Contro responses), outbound integrations proxy (redaction + backoff), jobs scheduler,
  notifications dispatcher (stub), webhooks router (HMAC verify), OpenAPI composer, secrets provider, RLS.
- **Module shape:** each module ships `migrations/`, `rls.sql`, `src/{api,application,domain,infrastructure}`,
  `config.ts`, `index.ts` (self-registers). Modules are pluggable.
- **Cross-module references are "soft FKs":** bare UUIDs, **not** DB foreign keys. Integrity is enforced
  at write-time via **contracts** (`contracts/src/<module>/v1.ts`, versioned) and modules communicate via
  **events** (`contracts/src/events/*`, versioned envelopes).
- **Money:** always minor units (bigint cents), currency ZAR. Tax/discount in **basis points** (1500 = 15%),
  banker's rounding on the cent boundary (`money/v1`).
- **Concurrency:** `version` column + touch triggers on most tables (optimistic locking).
- **Multi-tenant ready:** `tenant_id = 'zapmed'` everywhere.
- **Apps:** `apps/api` (Fastify) + `apps/ops-console` (Next.js + Tailwind, 14 admin pages).

## 3. The 21 modules (canonical domain model)

| # | Module | Role | Key tables |
|---|--------|------|-----------|
| 1 | **identity** | AuthN/Z, RBAC | principal, credential, session, mfa_factor, role, role_scope, principal_role, api_credential, password_reset, login_attempt |
| 2 | **patient_profile** | Demographics + contact + medical aid | profile, address, allergy, medical_history, preference |
| 3 | **catalog** | Products / SKUs / pricing / coupons | category, item, bundle_item, price (time-versioned), tax_rate, coupon, coupon_usage, service_line |
| 4 | **orders** | THE SPINE — order lifecycle | order, order_item, order_status_history, order_status_transition (state machine as data), rxhub_event_map |
| 5 | **payments** | Provider-agnostic ledger | payment, payment_attempt, refund, webhook_event |
| 6 | **consultations** | Doctor calendar + bookings | doctor_calendar, availability_rule, slot, consultation |
| 7 | **clinical** | Doctor review surface | clinical_review, clinical_prescription, clinical_prescription_item, clinical_info_request, clinical_note |
| 8 | **pharmacy** | RxHub fulfilment | dispatch, delivery, claim, rxhub_event |
| 9 | **subscriptions** | Repeat/chronic scripts | subscription, cycle, followup, pause_event |
| 10 | **notifications** | Email/SMS/WhatsApp dispatch | template, notification, opt_out, bounce, throttle_window |
| 11 | **partners** | External API consumers | partner, api_key, webhook_subscription, webhook_delivery, usage_window |
| 12 | **compliance** | POPIA | consent, consent_record, dsar, dsar_artifact, retention_policy, retention_schedule |
| 13 | **audit** | Append-only trail | audit_trail (monthly partitions), audit_integration_log |
| 14 | **analytics** | Marketing/funnel metrics | funnel_event, attribution, ad_spend, kpi_snapshot |
| 15 | **alerts** | SLA/threshold alerts | definition, alert, comment |
| 16 | **crm** | Lead funnel + health score | lead, funnel_event, note, flag, risk_score, nudge |
| 17 | **coaching** | Coach (Amy) journey | assignment, touchpoint, offer |
| 18 | **ai_assist** | AI summaries/risk/nudges | (code-only, no schema) |
| 19 | **finance_reports** | Revenue + reconciliation | revenue_entry, recon_entry |
| 20 | **ops_console** | Admin UI backing | saved_view |
| 21 | **upstream_sync** | Contro ELT ingestion | ingested_row, sync_state, sync_run |

## 4. The order lifecycle (the spine)

Seeded as **data**, not code: 25 statuses (20 base + 5 restored), 26 transitions, 5 RxHub event maps.
Contro spelling preserved verbatim, including the misspelling `PendingConsulation`.

```
PendingPayment ──payment ok──▶ PendingBooking ──calendar──▶ PendingConsulation
     │ payment fail                                                │ system_timer no check-in
     ▼                                                             ▼
 PaymentFailed ──patient retry──▶ PendingPayment              NoShow ──patient rebook──▶ PendingBooking

PendingConsulation ──doctor completed──▶ InReview
   InReview ⇄ AwaitingInformation   (doctor needs info / patient supplies)
   InReview ──doctor APPROVE──▶ Processing
   InReview ──doctor DECLINE──▶ Cancelled (reason=review_declined)

Processing ──rxhub 'New'──▶ PharmacyProcessing ──'ScriptProcessed'──▶ PreparingMedication
   'ScriptRejected' ──▶ ClaimRejected (medical-aid denied; manual recovery)
   'ScriptDispatched' ──▶ Despatched ──'ScriptDelivered'──▶ Delivered
   ──system_timer──▶ Completed

Completed ──▶ PendingFollowUpBooking (6-mo email) ──▶ PendingFollowUp ──calendar──▶ PendingConsulation
Repeat charge: PendingPayment ──▶ RepeatPaymentFailed ──retry──▶ ThreeRepeatFailures ──▶ Cancelled
Wildcard admin: ──▶ Paused / Cancelled;  Paused ──▶ Processing
```

Restored statuses (orders migration 0002, Jess-confirmed live): PaymentReceived, NotDelivered,
RefundComplete, RefundNeeded, TwoRepeatFailures.

- `order_status_history` is an immutable audit (from/to/trigger_type/triggered_by/notes/payload).
- `orders_is_transition_allowed()` validates each transition against the seeded rules — the ingestion
  pipeline uses it to flag anomalous Contro data.
- Trigger types: `payment`, `calendar`, `doctor`, `system_timer`, `patient`, `rxhub`, `admin`.

## 5. Clinical model (matches the patient-notes brief natively)

- **clinical_review:** outcome `pending/approved/info_requested/declined/referred_out`; `decision_notes`
  (doctor-facing), `patient_summary` (patient-facing), `icd10_codes[]`, `referral_reason`;
  started/decided/expires timestamps.
- **clinical_prescription:** status `draft/issued/fulfilled/cancelled/expired`; refill_window
  `one_off/monthly/quarterly/biannual/annual`; refills_remaining; `doctor_hpcsa_number` (regulatory);
  plus Contro fields (repeat_cycle_days, total_medication_cost_minor, service_fee_minor, next_repeat_date,
  delivery_method, pharmacy_script_ref) and upstream link columns.
- **clinical_prescription_item:** catalog snapshot + sig (dosage/frequency/duration/quantity/unit),
  nappi_code, schedule_class, unit/total price minor.
- **clinical_info_request:** the `AwaitingInformation` flow (question/response/status).
- **clinical_note:** `review_id, author, body, is_internal DEFAULT true`. This is exactly the
  "internal vs patient-visible note" the build-spec asked for — implemented here natively, enforced at
  the DB (see §7).

## 6. Pharmacy / RxHub integration

- RxHub is the external pharmacy fulfilment + delivery provider.
- **Inbound (RxHub → ZapMed) is fully built:** signed webhook `POST /v1/webhooks/pharmacy/rxhub`,
  HMAC verification (`rxhub-verifier.ts`, tolerance 60–3600s, default 300), dedup on RxHub `eventId`,
  event→status map (New→PharmacyProcessing, ScriptProcessed→PreparingMedication, ScriptRejected→
  ClaimRejected, ScriptDispatched→Despatched, ScriptDelivered→Delivered).
- **Outbound (ZapMed → RxHub) is NOT built:** dispatch rows are created but nothing submits the script
  to RxHub's API. No base URL, submit endpoint, or outbound auth exists. **Requires RxHub API docs from
  Craig** to build.
- Carriers: paxi / dhl / courierguy (default) / self_collect / ramsay. Claims carry scheme, membership,
  rejection code/reason, amounts (minor units).

## 7. Security model (RLS — DB-enforced)

- Every secured table: `ENABLE` + `FORCE ROW LEVEL SECURITY`.
- Identity from session GUCs set per-request by the kernel: `app.principal_type`
  (patient/doctor/ops/pharmacy/system/partner) and `app.principal_id::uuid`.
- Authorization is enforced **at the database layer**, not just in app middleware — materially stronger
  than our Laravel app (app-layer only).
- Representative rules:
  - **Staff-only (ops/system):** alerts, analytics, coaching, crm_nudge, audit (SELECT).
  - **catalog:** public read on active items/prices/categories/tax; ops/system write; coupons ops-only.
  - **consultations:** calendar/rules public-read, doctor(own)/ops write; consultation = patient(own) +
    doctor(own) + ops.
  - **clinical_note:** `SELECT` allowed only if `is_internal = false` OR principal is doctor/ops/system;
    writes doctor/ops/system only. Internal-vs-patient-visible enforced at the DB.
  - **compliance/subscriptions/pharmacy:** patient self-access on own rows; ops/system all; pharmacy
    scoped to dispatch/delivery/claim; patients never see claim details.

## 8. Events (55) & contracts (13) — the integration surface

- **Event envelope:** eventId, eventType, eventVersion, aggregateId, correlationId, causationId,
  principalIdAtEmit, principalTypeAtEmit, tenantId, occurredAt, payload — full audit lineage on every event.
- 55 events across identity(3), patient_profile(5), catalog(4), orders(6), payments(4), consultations(4),
  clinical(5), pharmacy(4), subscriptions(7), notifications(3), partners(5), compliance(5).
- **Contracts** expose the inter-module API. The Contro import path lives here as the
  `upsertUpstream*` / `ensureUpstreamPrincipal` methods (see doc 03 for the full list and semantics).

## 9. Compliance & audit posture

- **POPIA first-class:** consent purposes (transactional/clinical_share/pharmacy_share/medical_aid_claim/
  marketing/research/analytics/cross_border), DSAR workflow (access/export/erasure/etc., 30-day due),
  retention policies (HPCSA 6yr / POPIA s14 seeded), legal holds.
- **Audit:** append-only, partitioned monthly, 7-year retention (`ZAPMED_AUDIT_RETENTION_YEARS=7`),
  plus an integration log for every inbound/outbound call with redaction flags.

## 10. Open flags carried forward

1. **Payment gateway inconsistency:** `payments.provider` enum lists `peach`; `finance_reports` comments
   say `PayFast`; our Laravel uses PayFast. **Confirm the real gateway with Craig/Mark.**
2. **Contro exposes NO clinical-notes entity** via its API — only commerce/logistics. Doctor notes, if
   they exist, live in Contro's DB. This is the biggest unknown for the notes brief (see doc 03 §Gaps).
3. `ai_assist` has no schema — it's code-only (OpenAI/Gemini), but it is a real feature for parity.
