# Dietitian / Health Coach - DESIGN (proposal)

Follows `requirements.md`. Laravel 12 + Livewire 3, same conventions as the rest of the app. Reuse `CoachingService`, `Subscription*`, `WhatsAppChannel`, `ConsentRecord`.

## 1. Journey

```
Doctor consult (R700)  ->  doctor prescribes  ->  patient dashboard: "Add your Health Coach - R450/month"
        ->  PayFast subscription (existing)  ->  ITN active  ->  auto-assign dietitian (CoachingService::assign)
        ->  welcome message in the Coach thread (+ WhatsApp when Phase B is live)
        ->  member home: plan for today, message coach, log weight, content
```
Patients who never subscribe keep the normal patient dashboard. The member area is gated on an active subscription (`weight-loss` plan).

## 2. Data (new tables, migrations)

| Table | Key columns |
|---|---|
| `coach_conversations` | patient_id, coach_id (current assignee), status (open/closed), last_message_at, patient_last_read_at, coach_last_read_at |
| `coach_messages` | conversation_id, sender_id, sender_type (patient/coach/system), body, channel (app/whatsapp), wa_message_id, delivered_at, read_at |
| `weight_logs` | patient_id, weight_kg, note, logged_at, source (app/coach/consult) |
| `side_effect_logs` (optional, B/C) | patient_id, symptom, severity, logged_at |
| `member_content` | type (recipe/exercise/learn/guide), slug, title, summary, body, media_path, tags (json), is_published, published_at |
| `partner_offers` (D) | partner, title, code_or_link, valid_until, is_published |

Existing: `coaching_assignments` (one active coach per patient), `coaching_touchpoints`, `coaching_offers`, `subscriptions`.
Dietitian profile: a `health_coach` user + small `coach_profiles` row (credential e.g. RD(SA), registration number, bio, photo, capacity) so dietitians stop being stored as role Doctor.

## 3. Screens

**Patient / member** (`/member`, mobile first, installable PWA like Craig's phone mock-up)
- Home: "Hi {first name}. Here's your plan for today." Next check-in, today's tip, log weight, unread coach message.
- Coach: chat thread with the assigned dietitian (photo, name, "RD(SA)").
- Progress: weight trend chart (Craig's `weight-trend.html` style), BMI, starting point, change.
- Recipes / Exercise guides / Learning hub: lists + detail pages from `member_content`.
- Community (D): link/embed to the moderated group.

**Dietitian workspace** (`/coach`, role `health_coach`)
- Caseload: patients with unread, days since last contact, trend flag (stalled / fast loss), subscription status.
- Patient page: thread, weight chart, read-only clinical summary (medicines, allergies, doctor's plan, flags), touchpoint notes (existing `CoachingService::logTouchpoint`), "flag to doctor" button (creates a note for the prescribing doctor).
- Canned replies + snippets library.

**Admin** (extend existing `/admin/coach-console`): assign / reassign, capacity per dietitian, content manager (CRUD for `member_content`), message audit.

## 4. Rules

- Coach sees only assigned patients; every read of a patient summary is audit-logged (reuse the clinical read audit).
- Auto-assign picks the dietitian with the lowest active caseload under capacity; reassign keeps the thread.
- Messages are non-clinical advice; red-flag keywords ("chest pain", "vomiting a lot", "can't keep fluids") trigger an on-screen safety banner and notify the doctor.
- Out-of-hours: auto-reply with hours and emergency guidance.
- Subscription lapse: thread becomes read-only, member content locked after a grace period (decision 6).
- POPIA: health data over WhatsApp kept minimal; opt-in recorded in `consent_records` with wording; STOP honoured.

## 5. WhatsApp (Phase B)

- Outbound inside 24h window: free-form. Outside: approved templates (check-in, reminder, welcome).
- Inbound: Meta webhook -> match phone to patient -> `coach_messages` (channel whatsapp) -> unread for the coach.
- Number: dedicated ZapMed coaching number (same approach agreed for SPAR). Credentials already map to `services.whatsapp.token` + `phone_number_id`; `WhatsAppChannel` flips from dev-log to live.
- Lead time risk: Meta business verification and template approval are outside our control.

## 6. Reuse vs new

Reuse: `CoachingService`, `Subscription*` + PayFast, `WhatsAppChannel`, `ConsentRecord`, notification channels, Livewire layout/CSS tokens (journey CSS for the member area look).
New: conversation/message/weight/content models, member area, dietitian workspace, hand-over note, assignment automation.

## 7. Risks

Meta approvals; unclear "unlimited" fair-use; dietitian capacity and cover; content supply (recipes/exercises need an owner and image rights); clinical scope creep (dietitians must not change medication advice, only escalate); two billing events (R700 now, R450 monthly) must reconcile with PayFast subscription tokens.
