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
| Order lifecycle (25-status state machine + history) | orders | 🟡 | Model + machine built; NOT wired to live flow or UI |
| Catalog (products/bundles/pricing/coupons) | catalog | 🟡 | Tables built; admin UI + pricing logic missing |
| Payments ledger (attempts/refunds/webhooks) | payments | 🟡 | Extended; reconciliation UI + finance logic missing |
| Consultations (calendar/slots/booking) | consultations | 🟡 | Laravel already has Appointment/DoctorAvailability; parity gaps: slot model, consult types |
| Clinical review + notes (is_internal) | clinical | 🟡 | Our Consultation has field-level internal/visible split; no clinical_review aggregate/info-request flow |
| Pharmacy / RxHub fulfilment | pharmacy | 🔴 | Inbound webhook mirror + outbound submit (needs RxHub docs) |
| Subscriptions repeat lifecycle | subscriptions | 🟡 | Laravel has Subscription; missing cycle/3-strike/follow-up automation |

## CRM / ops intelligence (THE rented features — mostly 🔴)

| Capability | Their module | Our status | Notes |
|---|---|---|---|
| **Lead + funnel** (14 stages: lead→…→subscribed/churned/dropped_off) | crm | 🔴 | crm_lead + funnel_event equivalents |
| **Patient health/risk score** (0–100, band low/med/high/critical) | crm + ai_assist | 🔴 | risk_score; AI rubric optional (Layer AI) |
| **Patient flags** (at_risk/vip/do_not_contact/fraud/complaint/high_value) | crm | 🔴 | crm_flag |
| **Patient notes** (pinned staff notes) | crm | 🔴 | crm_note |
| **Patient 360 view** (cross-module aggregate + search) | crm | 🔴 | patient-view aggregator + search |
| **AI nudges** (stall_signup/intake/consult/payment/cold/churn) | crm + ai_assist | 🔴 | crm_nudge draft→approve→send |
| **Coaching** (assignment, touchpoints, cross-sell offers) | coaching | 🔴 | health_coach role + coaching tables |
| **Alerts / SLA** (order stale, payments failed, no-show → morning list) | alerts | 🔴 | definitions + scanner + workflow |
| **Analytics** (funnel counts, attribution, ad-spend/CAC, KPI snapshots) | analytics | 🔴 | analytics tables + dashboards |
| **Revenue / finance reports** (revenue entries, PayFast↔pharmacy recon, CSV) | finance_reports | 🔴 | finance module |
| **Order board / Kanban** (Jess's live ops view) | ops_console | 🔴 | the headline ops screen |
| **Health-coach role** (sub-role of ops, assigned patients only) | identity 0002 | 🔴 | role + scoping |

## Cross-cutting / compliance

| Capability | Their module | Our status | Notes |
|---|---|---|---|
| POPIA consent purposes + DSAR + retention | compliance | 🟡 | Laravel has ConsentRecord; DSAR/retention/purposes missing |
| Append-only audit trail (partitioned, 7yr) | audit | 🟡 | Laravel has audit_logs; not partitioned/retention-policied |
| RBAC scopes + MFA + partner API keys | identity | 🟡 | Laravel roles + 2FA; scopes/partner keys thinner |
| Event-driven domain events (55) | contracts/events | 🔴 | Laravel events for the new domains |

## Definition of "100% parity" (acceptance)
Every 🔴 → ✅, every 🟡 → ✅, plus the document import built, plus a full backfill from the real Contro dump
passing `contro:parity` clean with spot-checks signed off. Until then we are not at parity — and we say so.
