# Notifications / WhatsApp Parity — Scope & Gap Analysis (UAT Task 8)

**Status:** DECISION REQUIRED — no code written yet.
**Author:** Naz · **Date:** 2026-09-15
**Compared systems:** ours = `C:\Users\zande\Documents\Zapmed\zapmed` (Laravel 12 / PHP 8.2, the repo Craig
UAT-tests) · reference = Craig's Contro/Zapmed_CRM `notifications` module (Node/TS, read-only reference).

> Same pattern that worked for the Stats module (Task 7): scope doc → Captain Zan decision → task list →
> execute to the tee. Do **not** skip to building.

---

## 0. TL;DR

We send email and SMS today, and — contrary to the shorthand in the resume note — we already have a POPIA
opt-out gate. What we **don't** have: WhatsApp, a template registry, bounce handling, throttle windows,
push, and a single dispatcher. Our sends are **scattered** across controllers, Livewire components and
console commands with inline bodies. Craig's reference module has the full table shape (`template`,
`notification`, `opt_out`, `bounce`, `throttle_window`) and 4 channels — **but its dispatcher is a stub**
(per `contro-rebuild/01-system-design-dossier.md` line 37), so there is no live behaviour to match, only a
schema and an intent.

**My recommendation: Option B (Consolidate + log + opt-out enforcement everywhere), WhatsApp deferred behind
a channel interface.** Full parity (Option C) is not justified for UAT and is blocked on Meta creds anyway.

---

## 1. What WE have today (verified — files opened)

### Channels
| Channel | Implementation | Notes |
|---|---|---|
| Email | Laravel Mail (Brevo SMTP / SES configured in `config/services.php`) | 6 Mailables, all `ShouldQueue`. |
| SMS | `app/Services/SmsService.php` | BulkSMS **or** Clickatell via `SMS_PROVIDER`; E.164 formatter; `sendOtp()`; dev-mode **logs** instead of sending when unconfigured. |
| WhatsApp | **none** | Zero matches for `WhatsApp` in the entire `Documents/Zapmed` tree. |
| Push | **none** | PWA manifest exists (icons fixed in Defect #1) but no push pipeline. |

### Mailables (`app/Mail/`)
`AppointmentConfirmed`, `AppointmentReminder`, `NewAppointmentForDoctor`, `PaymentReceived`,
`PrescriptionReady`, `PrescriptionRefillReminder`.

### Notifications (`app/Notifications/`)
`AppointmentReminderNotification` (mail-only `via()`; 24h/1h/15m timings; SMS for 1h/15m is sent **separately
and directly** inside `SendAppointmentReminders`, not through the notification).

### Preference / opt-out (WE ALREADY HAVE THIS)
`app/Services/CommunicationPreferenceService.php` — POPIA direct-marketing rule: `canReceive(user, category)`
where **transactional/clinical is always allowed** and **marketing is blocked on explicit opt-out**
(`ConsentRecord`). `NewsletterSubscriber` has `unsubscribe_token` + `unsubscribe()`.

### SPAR standalone (adjacent, NOT core telehealth)
`apps/spar-standalone` + `App\Services\Spar\Channels\SmsChannel` implement a `MessagingChannel` abstraction
(integrated vs standalone host modes) wrapping our `SmsService`. This is the closest existing thing to a
channel interface and is a useful design precedent — but it lives in the SPAR app, not the telehealth core.

### The real problem: dispatch is scattered + un-gated
| Where | How it sends | Gaps |
|---|---|---|
| `PaymentController` | `Mail::to($p)->queue(new PaymentReceived/...)` | No preference check; no delivery record. |
| `Livewire\Admin\DoctorApplications` | `Mail::raw(...)` inline approval/rejection bodies | Content hardcoded in a UI component; not templated; not logged. |
| `Console\SendPrescriptionReminders` | `Mail::raw(...)` + `SmsService` | Body built inline; no throttle/dedupe beyond the `_sent_at` columns. |
| `Console\SendAppointmentReminders` | `->notify()` (email) + `SmsService` (SMS) directly | Two code paths for one event; no unified record. |

Net: **no single place** decides "should this go, on which channel, using which template, and did it land."
`CommunicationPreferenceService` exists but **is not consulted** by these send sites (it's wired for
marketing/newsletter, not the transactional sends — which is arguably correct, but there's no audit trail).

## 2. What CRAIG's reference module has

From `contro-rebuild/01-system-design-dossier.md`:
- **Module 10 `notifications`** — tables: `template`, `notification`, `opt_out`, `bounce`, `throttle_window`.
- Channels: **email / SMS / WhatsApp / push**.
- Provider abstraction: sandbox / SES / Twilio.
- **BUT** the platform-level "notifications dispatcher" is explicitly a **(stub)** (dossier line 37). So the
  reference gives us a **schema + channel list + intent**, not a proven runtime to replicate 1:1.

## 3. Gap table (capability, not line count)

| Capability | Ours | Craig ref | Verdict |
|---|---|---|---|
| Email send | ✅ Mailables (queued) | ✅ | parity |
| SMS send | ✅ BulkSMS/Clickatell | ✅ (Twilio) | parity (different provider) |
| WhatsApp | 🔴 none | 🟡 listed (stub) | **gap** — blocked on Meta/Twilio-WA creds |
| Push | 🔴 none | 🟡 listed (stub) | gap — low UAT value |
| Template registry | 🔴 inline bodies | ✅ `template` | **gap** — worth closing for UAT polish |
| Delivery/notification log | 🔴 only `_sent_at` cols | ✅ `notification` | **gap** — worth closing (auditability) |
| Opt-out enforcement | 🟡 exists, not consulted on transactional sends | ✅ `opt_out` | **partial** — wire the existing service in |
| Bounce handling | 🔴 none | 🟡 `bounce` (stub) | gap — needs Brevo/SES webhook |
| Throttle window | 🟡 ad-hoc `_sent_at` guards | 🟡 `throttle_window` (stub) | partial |
| Single dispatcher | 🔴 scattered | 🟡 stub | **gap** — the highest-value fix |

## 4. Options for Captain Zan

### Option A — Do nothing for UAT (document only)
Email+SMS already work; WhatsApp is a post-launch line item. Ship the gap doc, revisit later.
- **Pro:** zero risk, zero effort. **Con:** sends stay scattered/un-audited; no WhatsApp story for Craig.

### Option B — Consolidate + log + enforce opt-out; WhatsApp behind an interface *(RECOMMENDED)*
Build a single `NotificationDispatcher` + a `notification_log` table; route every existing send through it;
consult `CommunicationPreferenceService` on the way; move inline `Mail::raw` bodies into Mailables/Blade
templates; define a `NotificationChannel` interface (reuse the SPAR `MessagingChannel` precedent) with
Email + SMS implementations now and a **`WhatsAppChannel` stub** that no-ops/logs until creds arrive.
- **Pro:** real auditability + one choke point for preferences/throttling; WhatsApp drops in later with no
  refactor; all buildable **without Craig / without Meta creds**; testable + Chrome-verifiable for UAT.
- **Con:** ~1 focused session; touches 4 existing send sites (regression-tested).

### Option C — Full parity port (all 5 tables + 4 channels + providers)
Port `template`/`notification`/`opt_out`/`bounce`/`throttle_window` + WhatsApp + push + SES/Twilio drivers.
- **Pro:** 1:1 with Craig's schema. **Con:** large; **blocked on Meta/WhatsApp + Twilio + SES creds**;
  replicates a module that is itself a **stub** on their side. Not justified for UAT.

## 5. Recommendation

**Option B.** It closes the gaps that actually matter for a clean UAT (auditable delivery log, one enforced
preference/throttle choke point, templated content) and de-risks WhatsApp by putting it behind an interface,
so it becomes a creds-only follow-up rather than a refactor. Full parity (C) is premature against a
reference dispatcher that is itself unimplemented.

## 6. Dependencies / blockers
- **WhatsApp (any option that ships it live):** Meta WhatsApp Business (or Twilio WA) credentials +
  approved message templates. **BLOCKED ON Captain Zan / provider.** Option B ships the stub without them.
- **Bounce handling:** Brevo (or SES) bounce webhook endpoint + verification secret. Out of scope for B
  unless requested.
- No dependency on Craig's DB dump or repo for any option.

## 7. Proposed task list if Option B approved (recreate in todo tool)
1. `notification_log` migration + `NotificationLog` model (channel, template_key, recipient, category,
   status, provider_ref, error, timestamps).
2. `NotificationChannel` interface + `EmailChannel`, `SmsChannel` (wrap existing), `WhatsAppChannel` (stub/log).
3. `NotificationDispatcher` service: resolve channel → consult `CommunicationPreferenceService` → send →
   write `NotificationLog` → catch/record failures.
4. Route the 4 existing send sites through the dispatcher; move `Mail::raw` bodies into Mailables/Blade.
5. Tests: preference gating (transactional always / marketing blocked), log written on success+failure,
   WhatsApp stub no-ops cleanly, each existing flow still sends.
6. Chrome E2E: trigger a real send path (e.g. payment confirm) → verify log row + `laravel.log` (MAIL flip
   pattern) → restore. Full-suite regression.

---
*Awaiting decision: A, B, or C. My vote is B.*
