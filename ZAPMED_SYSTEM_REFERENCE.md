# ZapMed — System Reference (as-built)

> **Purpose:** The single source of truth Naz references while working on ZapMed.
> Describes what is **actually in the code** on disk, not the investor spec.
> **Last verified:** 2026-11-03 against repo `Zan1021/zapmed` branch `master` (commit `2afb28b`).
> Keep this updated when the architecture changes.

---

## 1. What ZapMed Is

A full-stack **telehealth platform for South Africa** — patients book and pay for online
doctor consultations (video/audio/text), get AI-assisted prescriptions, order medication,
and track their health progress. Four roles: **patient, doctor, admin, health_coach**
(partner exists as a model but is lighter). It is a single Laravel application (a "monolith"),
not a separate frontend + backend.

Beyond the core telehealth flow, the codebase also contains three large subsystems that the
investor spec does **not** mention and which are very much real in the code:

- **CRM layer** — lead funnel, risk scoring, Patient-360, AI nudges, alerts.
- **Contro integration** — pulls/reconciles data from the legacy Contro CRM (the system the
  live `zapmed.co.za` currently links out to).
- **SPAR pharmacy integration** — a pharmacy-staff portal, Health Coach inbox, order/renewal
  handling, and a telehealth bridge for prescription renewals.

Plus finance, analytics, compliance (POPIA/DSAR), subscriptions lifecycle, and a help centre.

---

## 2. The Stack (verified from `composer.json`)

| Layer | Technology | Notes |
|---|---|---|
| Framework | **Laravel 12** (PHP ^8.2) | Spec says Laravel 11 — spec is stale. Code is 12. |
| UI | **Livewire 3.6** + **Volt 1.7** + Alpine.js + Blade + Tailwind | No React/Vue. 59 Livewire components. |
| DB (local) | SQLite (`database/database.sqlite`) | |
| DB (staging/prod) | PostgreSQL 16 | |
| Cache/session | File cache on staging (Redis planned) | |
| Video | Daily.co (`DailyService`) | |
| Payments | PayFast (`PayFastService`) — sandbox on staging | |
| Email | Brevo HTTP API (`MAIL_MAILER=brevo`, juanparati/brevosuite) | Port 443, SMTP ports blocked on Forge |
| SMS/OTP | `SmsService` — BulkSMS **or** Clickatell drivers (not yet configured live) | |
| AI | OpenAI (`gpt-4o-mini`) | ⚠️ staging key may be a file path, not a real key — AI degrades to fallback |
| PDF | DomPDF | |
| Monitoring | Sentry (installed, needs DSN), custom `/health` endpoint | |

---

## 3. How It's Organised (the mental map)

Everything lives in `C:\Users\zande\Documents\Zapmed\zapmed`.

```
app/
  Models/        ~90 Eloquent models (see §4)
  Livewire/      59 components, grouped by role (see §6)
  Services/      ~50 service classes — the real business logic (see §5)
  Http/          Controllers (payments, PDF, webhooks, sitemap, health, WP plugin)
  Helpers/       country.php — currency()/provinces()/payment_gateway() multi-country helpers
  Traits/        EncryptsSensitiveFields (POPIA field encryption), LogsSparActivity
  Support/       LegacyRedirects (301 map for old WP URLs → SEO preservation)
routes/web.php   ~249 lines — ALL routes (no separate api.php story; API routes live here too)
resources/views/ Blade templates (public site + role dashboards)
tests/           76 test files
```

**Rule of thumb:** interactive UI = a Livewire component. Heavy logic = a Service. Models stay thin-ish.

---

## 4. The Data Model (what the ~90 models cover)

Grouped by what they do:

- **Identity & profiles:** `User` (roles, member_number `ZM-000001`, soft deletes, 2FA codes),
  `PatientProfile`, `DoctorProfile`, `DoctorAvailability`, `DoctorBlockedDate`, `DoctorApplication`,
  `Partner`, `PatientAllergy`, `PatientChronicCondition`, `PatientAddress`, `PatientGoal`.
- **Clinical flow:** `Appointment`, `Assessment`, `Consultation`, `VideoSession`,
  `Prescription` + `PrescriptionItem`, `Medication`, `Pharmacy`, `ProgressLog`.
- **Commerce:** `Order` + `OrderItem` + `OrderStatusHistory`, `Payment` (+ `PaymentAttempt`,
  `PaymentWebhookEvent`), `Refund`, `Payout`, `SubscriptionPlan`, `Subscription` (+ cycles,
  pause events, followups), `CatalogItem`/`CatalogPrice`/`CatalogCoupon`.
- **CRM:** `CrmLead`, `CrmNote`, `CrmFlag`, `CrmFunnelEvent`, `CrmNudge`, `CrmRiskScore`,
  `CrmAuditEvent`.
- **Analytics/Finance:** `AnalyticsFunnelEvent`, `AnalyticsAttribution`, `AnalyticsAdSpend`,
  `AnalyticsKpiSnapshot`, `FinanceRevenueEntry`, `FinanceReconEntry`.
- **Compliance (POPIA):** `ConsentRecord`, `ComplianceConsent` + records, `ComplianceDsar`
  (data subject access requests) + artifacts, `RetentionPolicy` + schedule items, `AuditTrail`.
- **Contro sync:** `UpstreamSyncRun`, `UpstreamSyncState`, `UpstreamIngestedRow`, `ImportQuarantine`.
- **SPAR:** `SparPharmacy`, `SparPatient`, `SparOrder`, `SparConsent`, `SparDispenseRecord`,
  `SparImportBatch`/`SparImportLog`, `SparPrescriptionJourney`.
- **Coaching:** `CoachingAssignment`, `CoachingOffer`, `CoachingTouchpoint`.
- **Content/comms:** `BlogPost`/`BlogCategory`, `HelpArticle`/`HelpCategory`/feedback,
  `Testimonial`, `Newsletter`/`NewsletterSubscriber`, `NotificationLog`, `AiConversation`,
  `AiKnowledgeEntry`, `Alert`/`AlertDefinition`/`AlertComment`, `Setting`.

**Conventions baked in:** fees stored in **cents** (R450 = 45000); `EncryptsSensitiveFields`
trait encrypts POPIA fields at rest; most "number" fields (ZAP-…, ZM-…) auto-generate in model
`boot()`; soft deletes on `User` (and relations use `withTrashed()` so deleted users still render
in audit views — that was an actual bug fix).

---

## 5. The Services (where the real work happens)

The ~50 service classes are the engine room. The ones that matter most:

- **Payments:** `PayFastService` — builds payment/subscription data, signs requests, validates
  ITN webhooks server-to-server. `PayoutService` — splits revenue between platform/doctor/
  pharmacy/partner. `ReferralService` — attributes signups and commission.
- **Clinical/AI:** `AiPrescriptionService` (dosage suggestions + full-prescription-from-notes,
  doctor must approve), `AiAssistantService` (public health chatbot with fallback),
  `DailyService` (create video rooms + join tokens), `ClinicalAuditLogger`.
- **Pharmacy:** `PharmacyService` + `PharmacyDispatchService` (dispatch via API / email / fax).
- **Subscriptions:** `Subscriptions\SubscriptionLifecycle` — cycles, retries, pause/resume,
  followups. The real recurring-billing brain.
- **CRM:** `Crm\LeadFunnel`, `Crm\RiskScorer`, `Crm\Patient360`, `Crm\JourneyCapture`,
  `CrmAiService` (risk scoring, patient summaries, nudge drafting).
- **Contro:** `Contro\ControClient` (auth + paged fetch), `ControPullService`,
  `ControReconciler` (410 lines — maps Contro patients/products/orders/payments/prescriptions
  into our tables, quarantines mismatches), `ControParityReport`.
- **SPAR:** `Spar\Telehealth\ZapmedTelehealthBridge` (renewal handoff to telehealth),
  `Spar\Identity\UserSparIdentityProvider` (pharmacy/group scoping), SPAR SMS channel + OTP.
- **Finance/Analytics:** `Finance\FinanceService` (revenue, reconciliation, disputes, write-offs),
  two `AnalyticsService` classes (one legacy dashboard-oriented, one event/attribution/CAC),
  `Stats\StatsService`, `Alerts\AlertScanner` (stale orders, abandoned carts, failed payments…).
- **Compliance:** `Compliance\ComplianceService` (consent, DSAR lifecycle, retention, legal hold),
  `Compliance\AuditTrail` (hash-chained audit log with `verifyChain()`), `PopiaService`
  (export/delete user data), `RetentionService`.
- **Notifications:** `Notifications\NotificationDispatcher` with pluggable Email / SMS /
  WhatsApp channels.
- **Commerce bridge:** `Commerce\LiveOrderBridge` — turns bookings/subscriptions/payments into
  unified Orders.

---

## 6. The Screens (Livewire components, by role)

Routes live in `routes/web.php`. Public pages are resolved last by a catch-all
`PublicSlugController` (so `/weight-loss` etc. resolve dynamically), with `LegacyRedirects`
handling old WordPress URLs via 301 for SEO.

**Public (no login):** landing/welcome page, treatment pages (`/{slug}`), `assessment/{slug}`,
blog (`/blog`, `/blog/{slug}`, `/category/{category}`), help centre (`/help`, `/help/{slug}`,
`/faq`→help), public doctor profiles (`/doctors/{doctor}`), doctor application (`/doctors/apply`),
`sitemap.xml`, newsletter unsubscribe, AI assistant endpoint (`POST /api/ai-assistant`).

**Patient** (`auth`+`verified`): `onboarding`, `book` (BookAppointment — auto-assigns best doctor),
`appointments`, `video/{appointment}`, `subscription` + checkout/success/cancel,
`prescriptions`, `review/{consultation}`, `progress` (ProgressTracker), PDF downloads
(prescription / sick-note / certificate), payment checkout/success/cancel.

**Doctor** (`role:doctor`): `doctor/dashboard`, `doctor/patients`, `doctor/prescriptions`,
`doctor/messages`, `doctor/consultation/{appointment}` (the clinical workspace),
`doctor/prescription/{consultation}` (AI-assisted builder), `doctor/availability`.

**Admin** (`role:admin`, prefix `/admin`): Dashboard, UserManagement, Appointments, Payments,
SubscriptionPlans, MedicationCatalog, AiManagement, Partners, Analytics, Stats, Newsletters,
Reviews, PharmacyOrders, OrderBoard, LeadsFunnel, PatientProfile360, AlertsBoard,
LanguageSettings, AuditLog, DoctorApplications, FailedPayments, BlogManagement, HelpCenter,
ControImport, AnalyticsDashboard (CRM), FinanceReports, AiNudges, ComplianceConsole.

**Health coach** (`role:admin,health_coach`): `admin/coach-console`.

**Partner** (`auth`): `partner/dashboard`.

**Webhooks / integrations (no auth, signature-verified):** `POST /payment/notify` (PayFast ITN),
`POST /subscription/notify`, `POST /api/pharmacy/status`, WordPress plugin info + download
endpoints (`/api/wp-plugin/info`, `/downloads/zapmed-booking-widget.zip`).

---

## 7. The Core Flows (how it actually works)

**Patient consultation:**
browse treatment → complete `Assessment` (conditional questions) → pay via PayFast
(once-off or subscribe) → pick a slot → `BookAppointment` auto-assigns the best available
verified doctor → join `VideoCall` (Daily.co) → doctor runs `ConsultationScreen`, writes notes +
`Prescription` (AI suggests, doctor approves) → PDF generated + signed → patient pays for meds →
`PharmacyDispatchService` sends to pharmacy → patient tracks `Order` status + logs `ProgressLog`.

**Payment (PayFast):** app creates a `Payment` (pending) → redirect to PayFast → PayFast posts
ITN to `/payment/notify` → `PayFastService::validateItn` + server-to-server verify → on success
activate booking/subscription, fire confirmation email/SMS; on fail mark failed + allow retry.
Card data never touches our servers.

**Contro reconciliation:** `ControImport` admin screen / scheduled pull →
`ControClient` authenticates and pages through Contro → `ControPullService` stores raw rows →
`ControReconciler` maps them into our models, quarantining anything that doesn't match →
`ControParityReport` shows whether we're in sync. This is how legacy Contro data flows in.

**CRM:** every meaningful patient action is captured as a `CrmFunnelEvent` via `JourneyCapture`;
`LeadFunnel` advances the lead's stage; `RiskScorer`/`CrmAiService` score churn risk and draft
`CrmNudge`s (admin approves before send); `AlertScanner` raises `Alert`s for stale orders, failed
payments, no-shows, cold signups.

---

## 8. Environments & Access

| | Local | Staging |
|---|---|---|
| URL | `http://127.0.0.1:8000` ⚠️ **port 8000, not 3000** | `https://zapdev.co.za` |
| DB | SQLite | PostgreSQL 16 |
| Access | `php artisan serve --port=8000` | **SSH** `forge@zapdev.co.za` (key-based) — **not FTP** |
| App path | repo root | `/home/forge/zapdev.co.za/current/` |

**Test logins (staging, all `Testing123!`):** `admin@zapmed.co.za`, `doctor@zapmed.co.za`,
`patient@zapmed.co.za`. Reset: `artisan zapmed:setup-test-accounts`.
Local admin was reset to `admin@zapmed.co.za` / `password`.

**Deploy (manual, no auto-deploy):** push to master → SSH pull on server → `composer install`
if deps changed → `artisan migrate --force` if needed → `config:clear` + `route:clear`.
⚠️ The staging server does **not** accept quoted remote SSH commands or pipes — run them
unquoted, one at a time. Rollback = repoint the `current` symlink to a previous release + reload
php-fpm. Logs: `storage/logs/laravel.log`, `/var/log/nginx/3336986-error.log`, `/health` endpoint.

---

## 9. Gotchas (learned the hard way — read before touching)

1. **Port 8000, not 3000.** The recurring "Dashboard running on port 3000" hook is for a
   different project (WP Unhack). Always serve ZapMed on 8000.
2. **OpenAI key on staging may be a file path, not a real `sk-` key** → AI silently falls back
   to keyword responses. Verify before demoing AI.
3. **PowerShell has no `&&`** and mangles `$var` in inline `php -r`/tinker → use temp `.php`
   scripts run via `artisan tinker script.php`.
4. **Soft-deleted users** must use `->withTrashed()` on `Appointment` relations or audit views
   show "Unknown/??".
5. **Date columns** are stored as `Y-m-d 00:00:00` — query availability/blocked dates with
   `whereDate()`, never exact string match (caused real double-booking + ignored blocked dates).
6. **`.env` holds live Brevo creds** — must stay gitignored; confirm before any checkout.
7. **Fees are in cents.** Never store or compare rands directly.
8. **Pre-existing test failure:** one AuthenticationTest navigation case (302 vs 200), unrelated
   to current work — don't chase it.

---

## 10. Spec vs Reality (don't be misled)

`C:\Users\zande\.kiro\vault\zapmed-system-specification.md` is an **investor document (v2.0)**.
It is aspirational and in places wrong:
- Says Laravel 11 → it's **Laravel 12**.
- Claims "everything built and ready for production" → staging is sandbox; several services
  (Daily.co, BulkSMS, real OpenAI key) are **not yet configured live**.
- Omits the entire **CRM, Contro, SPAR, finance, compliance** subsystems that exist in code.
- Describes production on AWS Cape Town → that's **planned**, current hosting is Hetzner/Forge.

**When spec and code disagree, the code wins.** This file tracks the code.

---

## 11. Related Context Files (in `.kiro/project-context/`)

- `zapmed.md` — project summary
- `zapmed-deployment.md` — deploy steps, SSH, rollback, log paths
- `zapmed-services.md` — external API keys/config + pre-launch checklist
- `zapmed-logins.md` — test accounts + admin URLs
