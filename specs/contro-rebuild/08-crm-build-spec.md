# CRM Build Spec & Design — owned Laravel system (Option A)

**Author:** Naz · **Date:** 2026-09-13
**Scope:** the Layer 2 (CRM behaviour) + Layer 3 (interface) build, re-implemented in our Laravel app to
100% functional parity with Mark's `Zapmed_CRM`. Reference only — we read their code, we build ours.
**Companion:** 06-state-of-play, 07-parity-checklist. Foundation (Layer 1) already built + committed.

---

## 1. Design principles (how their model maps to Laravel)

- **Their `principal` (uuid, multi-type) → our `User`** (role: patient/doctor/ops/health_coach/partner).
  We already have Users + roles; we add the missing roles + finer scopes, not a new identity system.
- **Their per-module Postgres schemas → our single Laravel DB** with clear table prefixes (`crm_`,
  `coaching_`, `alerts_`, `analytics_`, `finance_`). One app, one migration set.
- **Their DB-enforced RLS → Laravel Policies + query scopes** (app-layer). We document the intended access
  rule per table (from their rls.sql) and enforce it via Policies + global scopes. (RLS parity is a
  hardening item; app-layer first.)
- **Their contracts/events → Laravel domain events + service classes.** Cross-module calls become service
  method calls; the 55 events become Laravel events where behaviour depends on them.
- **Their Next.js ops-console (14 pages) → Livewire admin components**, matching our existing admin stack
  (Livewire is already how Admin/Doctor/Patient screens are built).
- **Money stays minor units (cents), ZAR** — already consistent across the app.
- **AI features (risk score, nudges, suggest-action)** map onto our existing AiConversation/AiKnowledge
  infrastructure; AI is OPTIONAL per feature (graceful no-AI fallback), never a hard dependency.

## 2. Feature designs

### 2.1 CRM core — lead, funnel, patient 360
- **`crm_leads`**: one per patient principal; `current_stage` (14-stage enum: lead, signed_up,
  intake_started, intake_complete, consult_booked, consult_complete, script_issued, paid, dispatched,
  delivered, coached, subscribed, churned, dropped_off), source/channel/campaign/UTM, service_line,
  assigned_to, stage_entered_at, last_activity_at.
- **`crm_funnel_events`**: immutable stage-transition history (from/to/aggregate ref/notes/actor).
- **`crm_flags`**: at_risk/vip/do_not_contact/fraud_suspected/complaint_open/high_value/follow_up_required.
- **`crm_notes`**: pinned staff notes on a patient.
- **`crm_risk_scores`**: score 0–100 + band (low/medium/high/critical) + factors[]. Computed by a rules
  engine (payment failures, days inactive, stalled stage, no-shows, open flags); AI-assisted scoring is an
  optional enhancement using the same rubric.
- **Patient-360 read aggregator**: a query service that assembles principal + profile + addresses + lead +
  risk + flags + notes + recent orders/payments/consults/notifications + subscriptions + coach + LTV. Plus
  a cross-field patient **search** (identifier/email/msisdn/name/SA-ID/order-number).

### 2.2 Order board / Kanban (the headline ops screen)
- Livewire board grouping `orders` by `status` (the 25-status machine), swimlanes per status group, filters
  (service line, assignee, date), click-through to order detail (items, status history timeline, linked
  payment/prescription). Read-first; status changes go through `Order::transitionTo()` (already built,
  guarded + audited).

### 2.3 Alerts / SLA
- **`alerts_definitions`** (seeded: order stale/stale_critical, pending_booking, abandoned_cart,
  payment failed/repeat_failed, consult no_show/awaiting_info, subscription churn_risk, cold_signup) with
  threshold config.
- **`alerts_alerts`** (instance: severity, status open/ack/resolved/snoozed/auto_closed, dedupe_key,
  re_raise_count) + comments.
- **Scanner**: a scheduled Laravel job (Console\Kernel schedule) running detectors; auto-resolves cleared
  conditions. (Their in-process setInterval → our scheduler.)

### 2.4 Coaching
- **`coaching_assignments`** (one active coach per patient), **`coaching_touchpoints`** (kind/channel/
  direction/sentiment/summary), **`coaching_offers`** (cross-sell: open/accepted/declined/expired).
- **`health_coach`** role (sub-role of ops, sees assigned patients only).

### 2.5 Subscriptions repeat lifecycle
- Extend existing Subscription with **cycles** (scheduled→attempted→placed→payment_failed/fulfilled),
  3-strike `consecutive_failures`, follow-up-at (+180d), pause events. Scheduled runner for due cycles.
- NOTE: import must NOT trigger real charges — repeat automation only applies to post-cutover live flow.

### 2.6 Analytics + finance
- **Analytics**: funnel counts, attribution (first/last touch UTM), ad-spend/CAC, daily KPI snapshots.
- **Finance**: revenue entries (cash/recognised/pipeline/refund/chargeback), PayFast↔pharmacy
  reconciliation (matched/unmatched/partial/disputed), CSV export. (Mirrors their finance_reports;
  gateway = PayFast.)

### 2.7 AI assist (optional layer)
- Risk scoring, patient summary, alert suggest-next-action, CRM nudges (draft→ops approve→send via
  notifications). Uses existing AI infra; every feature degrades gracefully with no AI key.

### 2.8 Compliance / audit (parity hardening)
- POPIA: consent purposes, DSAR workflow (access/export/erasure…, 30-day), retention policies + legal hold.
- Audit: append-only trail for the new domains; retention policy.

## 3. Interface (Layer 3) — Livewire admin, matching existing stack
- New admin nav group "CRM": Order Board, Patients (360 + search), Leads/Funnel, Alerts, Coaching,
  Analytics, Finance, plus an **Import Ops** page (run/monitor contro:pull, parity report, quarantine review).
- Health-coach console (assigned patients, touchpoints, offers).
- Surface imported data in existing admin where natural (orders, catalog, payments).

## 4. Non-negotiables carried in
- 100% functional parity (every checklist item) + 100% import fidelity (incl. documents once source arrives).
- Import stays read-only + zero-side-effects; live automation (charges, nudges, pharmacy) only post-cutover.
- PayFast gateway. Craig owns everything; Craig's repos read-only reference.

## 5. Suggested build order (see tasklist)
Import Ops UI → Order Board (uses built Order model) → CRM core (leads/flags/notes/patient-360) → risk score
→ alerts → coaching → subscriptions lifecycle → analytics → finance → AI assist → compliance/audit →
document import (when Craig delivers file store) → live write-path adoption (flagged) → full backfill + parity.
