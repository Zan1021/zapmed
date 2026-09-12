# State of Play & Roadmap — what's done vs what's left

**Author:** Naz · **Date:** 2026-09-13
**Purpose:** An honest map of where the ZapMed owned-system / Contro rebuild actually stands, so we stop
conflating "the import works" with "we have a working, visible CRM." Grounded in the actual repo.

---

## The mental model: three layers

```
┌─────────────────────────────────────────────────────────────┐
│ LAYER 3 — INTERFACE (what users see: admin / doctor / patient)│
├─────────────────────────────────────────────────────────────┤
│ LAYER 2 — CRM BEHAVIOUR (order board, health score, coaching,│
│           alerts, dashboards, funnel — the rented features)   │
├─────────────────────────────────────────────────────────────┤
│ LAYER 1 — DATA + IMPORT (tables + pull/reconcile/parity)      │  ← built today
└─────────────────────────────────────────────────────────────┘
```

We built **Layer 1** today. Layers 2 and 3 are largely still ahead. Below is the detail.

---

## ✅ DONE (this build — Layer 1)

**Data foundation (migrations + models):**
- Contro crosswalk on every recipient table (idempotency backbone).
- Order aggregate + 25-status state machine (rules as data) + immutable status history.
- Catalog: items + time-versioned prices + coupons + coupon usage.
- Payments extensions: idempotency, attempts, refunds, webhook dedup, Contro fields, zero-amount.
- Patient extensions (medical-aid, E.164, labelled/geocoded addresses) + prescription Contro fields.
- upstream_sync staging + import_quarantine.

**Import pipeline (CLI, tested, zero-side-effect, idempotent):**
- `contro:pull` (watermarked ELT into staging), `contro:reconcile` (staging→canonical), `contro:parity`
  (verification).
- 60 tests incl. full end-to-end fixture run.

**What Layer 1 gives you:** Contro's data can land, correctly and safely, in owned tables. That's it —
it's the on-ramp, not the destination.

---

## ⚠️ PARTIALLY DONE (already existed in the Laravel app, but NOT connected to the new import data)

The app already has a real interface (Livewire) that predates this work:
- **Admin:** Dashboard, Appointments, Payments, FailedPayments, PharmacyOrders, MedicationCatalog,
  SubscriptionPlans, UserManagement, Partners, Analytics, AuditLog, Reviews, DoctorApplications, etc.
- **Doctor:** Dashboard, ConsultationScreen, PrescriptionBuilder, MyPatients, MyPrescriptions, availability.
- **Patient:** BookAppointment, MyAppointments, MyPrescriptions, MySubscription, Onboarding, VideoCall, etc.

**The gap:** these screens read the ORIGINAL models (Appointment, Payment, Prescription, Medication). They
do **not** read the NEW Contro-import models (Order, CatalogItem, OrderStatusHistory, imported patients/
payments). So imported data is invisible in the UI today, and the app's live write-paths don't use the new
Order aggregate/state machine yet.

---

## ❌ NOT DONE

**Layer 1 remainder (needs Craig, not code):**
- Live import run — blocked on Contro DB dump (few days), prod HTTPS URL, service creds.
- Clinical-notes import — only if the DB dump contains notes (API exposes none).

**Layer 2 — CRM behaviour (the features Craig currently RENTS from Contro — none built):**
- Live **order board / Kanban** by status (Jess's ops view).
- Patient **health score** (R/A/G) + risk scoring.
- **Coaching** tracker (Amy's journey: touchpoints, cross-sell, retention).
- **Alerts / SLA** engine (stale orders, failed payments, no-shows → morning list).
- **Revenue dashboard** + **conversion funnel** + finance reconciliation.
- Subscription **repeat lifecycle** automation (cycle due/placed, 3-strike, follow-up).
- AI assist (summaries / nudges).
- POPIA compliance surface (consent/DSAR/retention), audit trail.

**Layer 3 — Interface for the above (none built):**
- **Import ops UI** — trigger/monitor pulls, view parity report, work the quarantine queue. (Smallest,
  safest, most clearly-needed next screen. Currently all CLI.)
- Admin views that surface **imported** orders/catalog/coupons/payments.
- Wiring the new Order aggregate into the **live checkout/appointment write-path** (architectural — see below).

---

## The fork that gates most of Layer 2/3 (decide with Craig)

Per `04-strategic-options-for-craig.md`, the owned system is still one of:
- **(A) Laravel to parity** — build Layers 2 & 3 in this app; today's tables become the real backend.
- **(B) Adopt Mark's TS backend** — it already HAS most of Layer 2; today's Laravel tables may be
  staging/parallel, and the interface points at the TS backend instead.
- **(C) Hybrid.**

This decision changes what "wire it into our interface" even means. Under A, we build the CRM features here.
Under B, we mostly don't — we point the UI at Mark's platform and today's import may be redundant or a bridge.
**This is the single most important open decision** and it's Craig's + yours, not mine.

---

## Recommended sequencing (once direction is set)

**Safe now, under ANY option (additive, no live-flow risk):**
1. **Import ops UI** — admin page: run/monitor pulls, parity report, quarantine review (approve/resolve).
   Turns the CLI tools into something you can actually watch and trust during a real import.
2. **Read-only admin views** of imported orders + status history + catalog (so imported data is visible).

**Only after the A/B/C decision:**
3. If **A**: build Layer 2 features (order board, health score, alerts, dashboards…) + wire the live
   write-path onto the Order aggregate. Large program of work.
4. If **B/C**: integration plan against Mark's TS backend instead; today's import becomes a bridge/one-off.

---

## One-line honest summary

Today we built the **data foundation and the import on-ramp** (Layer 1). The **CRM features** Craig rents
(Layer 2) and the **screens** to see/use them (Layer 3) are still ahead — and how much of that we build in
Laravel vs adopt from Mark's backend depends on a strategic decision that's still open with Craig.
