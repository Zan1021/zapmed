# Parity Checklist — Contro/Mark's backend → our Laravel build

**Author:** Naz · **Date:** 2026-09-13
**Standard:** 100% functional parity (governing standard mem 2026-09-13). Option A — build it all in Laravel.
**How to use:** every module/feature Mark's `Zapmed_CRM` has is listed. Status is tracked here so "100% like
Contro" is provable, not asserted. Update status as we ship.

Legend: ✅ done · 🟡 partial (exists but not to parity / not wired to import data) · 🔴 not started

## Data + import layer (foundation)

| Capability | Their module | Our status | Notes |
|---|---|---|---|
| Canonical schema for all entities | (all migrations) | ✅ | Tasks 1–5 this session |
| Contro ELT import | upstream_sync | ✅ | pull/reconcile/parity, tested |
| Quarantine + parity | (ops) | ✅ | import_quarantine + contro:parity |
| **Document/file import** | (DB/file store) | 🔴 | Contro API exposes none; needs dump + file store from Craig. Net-new. |

## Clinical / commerce (behaviour + UI)

| Capability | Their module | Our status | Notes |
|---|---|---|---|
| Order lifecycle (25-status state machine + history) | orders | ✅ | Model + machine built (Task 2); wired to LIVE flow (Task 11) via LiveOrderBridge — booking/subscribe/PayFast-ITN create + advance Orders (PendingPayment→PaymentReceived), idempotent, no gateway calls. Pre-launch/sandbox. |
| Catalog (products/bundles/pricing/coupons) | catalog | 🟡 | Tables built; admin UI + pricing logic missing |
| Payments ledger (attempts/refunds/webhooks) | payments | 🟡 | Extended; PayFast↔pharmacy reconciliation UI built (Task 7); live capture now mirrored to Order + finance ledger on ITN (Task 11 LiveOrderBridge). Pre-launch (PAYFAST_TEST_MODE=true). |
| Consultations (calendar/slots/booking) | consultations | 🟡 | Laravel already has Appointment/DoctorAvailability; parity gaps: slot model, consult types |
| Clinical review + notes (is_internal) | clinical | 🟡 | Our Consultation has field-level internal/visible split; no clinical_review aggregate/info-request flow |
| Pharmacy / RxHub fulfilment | pharmacy | 🔴 | Inbound webhook mirror + outbound submit (needs RxHub docs) |
| Subscriptions repeat lifecycle | subscriptions | ✅ | subscription_cycles (scheduled→attempted→placed→fulfilled/payment_failed) + 3-strike auto-cancel + followups (+180d) + pause_events + SubscriptionLifecycle + subscriptions:run-due scheduled. Import-safe (records outcomes, never charges). Task 6. |

## CRM / ops intelligence (THE rented features — mostly 🔴)

| Capability | Their module | Our status | Notes |
|---|---|---|---|
| **Lead + funnel** (14 stages: lead→…→subscribed/churned/dropped_off) | crm | ✅ | crm_leads + crm_funnel_events (immutable); LeadFunnel service. Task 3. |
| **Patient health/risk score** (0–100, band low/med/high/critical) | crm + ai_assist | ✅ | crm_risk_scores + RiskScorer (rules engine, rubric parity); AI scoring built as the optional layer (CrmAiService::scoreRisk → computed_by='ai', falls back to rules). Task 3 + Task 8. |
| **Patient flags** (at_risk/vip/do_not_contact/fraud/complaint/high_value) | crm | ✅ | crm_flags + CrmFlagKind enum; raise/clear via LeadFunnel. Task 3. |
| **Patient notes** (pinned staff notes) | crm | ✅ | crm_notes; pinned-first ordering. Task 3. |
| **Patient 360 view** (cross-module aggregate + search) | crm | ✅ | Patient360 aggregator + cross-field search (member no./email/phone/name/order no.); SA-ID encrypted → not substring-searchable (flagged). Task 3. |
| **AI nudges** (stall_signup/intake/consult/payment/cold/churn) | crm + ai_assist | 🔴 | crm_nudge draft→approve→send |
| **Coaching** (assignment, touchpoints, cross-sell offers) | coaching | ✅ | coaching_assignments (one-active-per-patient) + touchpoints + offers + CoachingService + coach console (role-scoped). Task 5. |
| **Alerts / SLA** (order stale, payments failed, no-show → morning list) | alerts | ✅ | alerts_definitions (10 seeded) + alerts_alerts (dedupe/re-raise/auto-close) + comments + AlertScanner (8 detectors) + alerts:scan scheduled + morning-list UI. Task 4. |
| **Analytics** (funnel counts, attribution, ad-spend/CAC, KPI snapshots) | analytics | ✅ | analytics_funnel_events + attribution (first/last-touch) + ad_spend + kpi_snapshots; AnalyticsService (funnel/conversion/CAC/kpiSummary) + analytics:snapshot nightly + CRM Analytics dashboard. Task 7. |
| **Revenue / finance reports** (revenue entries, PayFast↔pharmacy recon, CSV) | finance_reports | ✅ | finance_revenue_entries (6 kinds, signed) + finance_recon_entries (5 statuses, computed delta) + FinanceService (revenue summary/series, recon match/dispute/write-off) + Finance UI + revenue CSV export. Task 7. |
| **Order board / Kanban** (Jess's live ops view) | ops_console | ✅ | admin.order-board (Task 2) — swimlanes, filters, guarded transitions. |
| **Health-coach role** (sub-role of ops, assigned patients only) | identity 0002 | ✅ | UserRole::HealthCoach + isHealthCoach(); coach console scoped to assigned patients only. Task 5. |
| **AI assist** (risk, patient summary, alert next-action, nudges) | ai_assist | ✅ | CrmAiService over existing OpenAI (config services.openai); 4 features each with deterministic no-key fallback; crm_nudges draft→approve→send (AI never sends) + AI Nudges console. Task 8. |

## Cross-cutting / compliance

| Capability | Their module | Our status | Notes |
|---|---|---|---|
| POPIA consent purposes + DSAR + retention | compliance | ✅ | compliance_consents (8 purposes, versioned) + immutable consent_records + compliance_dsars (6 kinds, guarded status machine, 30-day SLA + overdue) + dsar_artifacts + retention_policies (10 seeded) + retention_schedule (legal hold) + ComplianceService + compliance:scan + Compliance console. Task 9. |
| Append-only audit trail (partitioned, 7yr) | audit | ✅ | crm_audit_events (append-only: model blocks update/delete) + tamper-evident sha256 hash chain + AuditTrail::verifyChain; complements ClinicalAuditLogger (clinical reads). 7yr retention policy seeded. Partitioning is a Postgres-prod deployment detail. Task 9. |
| RBAC scopes + MFA + partner API keys | identity | 🟡 | Laravel roles + 2FA; scopes/partner keys thinner |
| Event-driven domain events (55) | contracts/events | 🔴 | Laravel events for the new domains |

## Definition of "100% parity" (acceptance)
Every 🔴 → ✅, every 🟡 → ✅, plus the document import built, plus a full backfill from the real Contro dump
passing `contro:parity` clean with spot-checks signed off. Until then we are not at parity — and we say so.
