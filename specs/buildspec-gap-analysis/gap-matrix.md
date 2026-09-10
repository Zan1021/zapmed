# ZapMed Telehealth — Build-Spec Gap Analysis

**Status:** Draft for review (Naz / Team Lead)
**Date:** 2026-09-02
**Their spec:** `C:\Users\zande\Documents\Zapmed\zapmed-buildspec\` (files 00–10)
**Our system:** Laravel 12 + Livewire 3 ZapMed — `C:\Users\zande\Documents\Zapmed\zapmed`

---

## Executive summary

The build-spec is a **from-scratch architecture brief written by an advisor** who assumed the
system did not yet exist. It reverse-engineers patterns from `Zapmed_CRM` — **which is our own
code, hosted in the client's company org.** It is therefore NOT a set of foreign requirements to
conform to; it references patterns we wrote and have since re-implemented (and in places
improved) in the current Laravel ZapMed.

**Confirmed business decisions (Captain Zan):**
1. **We keep our current stack (Laravel/Livewire).** The advisor's Node/Fastify/Postgres-RLS
   stack was an assumption, not a client mandate. Declined.
2. **`Zapmed_CRM` = our code / their company.** No third-party system to integrate against;
   "reuse module X" lines refer to our own prior work.

3. **Support chat: we use our OWN system, not Crisp.** Our Help Center (searchable KB) + AI
   Health Assistant (OpenAI, KB-aware, treatment-routing) covers the self-service/chatbot role.
   The live-agent-inbox half is an OPTIONAL future enhancement we'd build in-stack, not buy.

**Net result:** No rebuild. We already satisfy the large majority of the spec's *functional*
requirements, and exceed it in places (video, field-level encryption, AI assistant). The real
delta is **5 compliance-hardening items** driven mainly by the **March 2026 POPIA health-data
regulations** (binding, no grace period), plus **1 optional in-house enhancement** (live-agent
inbox) if the client wants human live-chat with system context.

---

## Verdict legend
- **KEEP** — we have it; decline the spec's suggested third-party tool.
- **BUILD** — genuine gap; implement in our stack.
- **INTEGRATE** — genuine gap best filled by a third party.
- **HARDEN** — we have most of it; close a specific compliance/robustness gap.

---

## Gap matrix

| # | Requirement (their spec) | Their assumed 3rd-party | What WE have (verified in code) | Verdict |
|---|---|---|---|---|
| 1 | Booking / scheduling | **Acuity** (widget + webhooks) | `BookAppointment` + `DoctorAvailability` + blocked dates + slot logic | **KEEP** — decline Acuity |
| 2 | Payments once-off + subscription + ITN | PayFast | `PayFastService` (signature, ITN webhook, subscriptions) + `PaymentController` | **KEEP** |
| 3 | Video consultation | (not specced!) | Daily.co — `DailyService`, `VideoCall`, `ConsultationScreen` | **KEEP** — ahead of spec |
| 4 | Notifications (templated, multi-channel) | reuse CRM module / SendGrid / Twilio | Brevo (email) + BulkSMS + reminder commands | **KEEP core** — decline SendGrid/Twilio |
| 5 | Prescription → legal PDF | custom render | `PdfController` (prescription, sick note, cert) + DomPDF + authz gate | **KEEP** — ahead of spec |
| 6 | Structured script (status, refills, sig) | `clinical_prescription` | `Prescription`: `RX-` ref, `sign()`, `signature_hash`, chronic+repeats, `valid_until` | **KEEP** |
| 7 | Field-level encryption of PII/clinical | app-layer AES / pgcrypto | `EncryptsSensitiveFields` on Consultation(7), Prescription(2), User, SparPatient | **KEEP** — spec lists this as a *gap*; we already fixed it |
| 8 | Versioned, typed consent | `compliance` module | `ConsentRecord`: `consent_type`, `version`, `granted`, `granted_at`, `revoked_at`, `ip_address` | **KEEP** |
| 9 | POPIA data-subject rights (export/erase) | `compliance` module | `PopiaService::exportUserData()` + `processDataDeletion()` (anonymise + retain medical) | **KEEP** |
| 10 | Clinical record-keeping (notes, ICD-10) | `clinical_review`/`clinical_note` | `Consultation`: diagnosis, hx, exam findings, treatment plan, doctor_notes, `icd10_code` | **KEEP** |
| 11 | Support chat (self-service) | **Crisp.chat** | Help Center (searchable KB) + AI Health Assistant (OpenAI, KB-aware, treatment routing, keyword fallback) | **KEEP** — decline Crisp; use our own |
| 11b | Support chat (LIVE human agent + system-context notes into thread) | Crisp.chat | Not built — AI assistant is stateless/anonymous, no agent inbox, no conversation persistence | **OPTIONAL BUILD** — in-stack, only if client needs live human chat |
| 12 | HPCSA number captured ON the script at issue | `doctor_hpcsa_number` on prescription | **DONE** — snapshot `prescriber_name/hpcsa_number/qualification` at `sign()`; PDF reads snapshot w/ live fallback | **CLOSED** |
| 13 | Read-auditing of clinical records (who viewed, when) | `compliance` audit | **DONE** — `clinical_audit` log channel + `ClinicalAuditLogger` wired into all 3 PdfController endpoints | **CLOSED** |
| 14 | Explicit internal-vs-patient-visible note flag | `clinical_note.is_internal` | **DONE** — Consultation `INTERNAL_FIELDS`/`PATIENT_VISIBLE_FIELDS` + `patientVisibleData()` boundary | **CLOSED** |
| 15 | Retention field per data category + disposal | retention per category | **DONE** — `config/retention.php` + `RetentionService` + `data:apply-retention` cmd (dry-run default). Also fixed latent NOT-NULL bug blocking POPIA anonymisation | **CLOSED** |
| 16 | Marketing vs transactional opt-out split | opt-out table split by category | **DONE** — `CommunicationPreferenceService` (transactional never blocked; marketing opt-out honoured) | **CLOSED** |
| 17 | Webhook security (HMAC + replay + dedupe + scopes) | copy CRM pattern | **DONE (partial)** — PayFast ITN now constant-time compare; pharmacy webhook now HMAC-SHA256 verified + fail-closed. **Dedupe tables still TODO** (see note) | **CLOSED (with follow-up)** |

---

## The punch-list — STATUS

**Support chat — DONE (decision):** use our own Help Center + AI Assistant; Crisp declined.
Optional live-agent inbox remains a future in-stack build only if the client needs it.

**Compliance-hardening — ALL 5 CLOSED (2026-09-02), each with tests + green regression gate:**
- ✅ **HPCSA number snapshot** onto `Prescription` at `sign()`. (`PrescriberSnapshotTest`)
- ✅ **Read-audit** of clinical-document access via `clinical_audit` channel + `ClinicalAuditLogger`. (`ClinicalReadAuditTest`)
- ✅ **Internal-note boundary** on `Consultation` (`patientVisibleData()`). (`ClinicalNoteVisibilityTest`)
- ✅ **Retention per category** (`config/retention.php` + `RetentionService` + `data:apply-retention`). (`DataRetentionTest`) — also fixed a latent NOT-NULL bug that blocked POPIA anonymisation.
- ✅ **Marketing/transactional opt-out split** (`CommunicationPreferenceService`). (`CommunicationPreferenceTest`)

**Webhook security — DONE with one follow-up:**
- ✅ PayFast ITN: signature compare hardened to constant-time `hash_equals`.
- ✅ Pharmacy webhook: now requires HMAC-SHA256 signature (`X-Pharmacy-Signature`), fails
  closed if no secret configured. (`PharmacyWebhookSecurityTest`)
- ⏳ **Follow-up (not yet done):** idempotent dedupe tables per webhook (`<provider>_event`
  keyed on provider event id) to prevent double-processing on retries. Recommended but not
  blocking; flagged for a future pass.

**Test suite:** 71 passing / 1 pre-existing unrelated fail (dashboard-redirect test). No regressions.

---

## Recommendation

**Do not rebuild. Do not adopt Acuity / SendGrid / Twilio / Crisp / the Node stack.** Present
this matrix to the client as the "why we already meet the spec" document. Then close the
5-item compliance punch-list — all are hardening we'd want regardless of this client, given the
March-2026 POPIA regulations. Support chat is handled by our own Help Center + AI Assistant;
a live-agent inbox is an optional in-stack build only if the client specifically needs human
live-chat with system context.

**Flag (not legal advice):** the compliance items map to binding SA law (POPIA health-data regs,
effective 2026-03-06, no grace period) + HPCSA telehealth rules. Get the compliance officer /
attorney to sign off the consent wording and retention policy — that is wording/process, not code.
Do NOT represent the platform as "HIPAA compliant" — HIPAA is US law and does not apply here
(the advisor's spec makes this point too).

## Open (for the client, not code)
- Does the client need LIVE human support chat (agent inbox), or is self-service (our AI + KB)
  sufficient? Only the former requires the optional in-house build (item 11b).
- Confirm no *contractual* obligation to Acuity / Node / Crisp before we formally decline them
  in writing.
