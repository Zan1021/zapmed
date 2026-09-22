# ZapMed — UAT Walkthrough (Craig)

**Purpose:** step-by-step script to acceptance-test the ZapMed telehealth platform end to end.
**Last verified:** 2026-09-13 by Naz (full Chrome E2E regression — all flows GREEN).
**Environment:** staging/local. App base URL as provided by the team (local dev = http://127.0.0.1:8000).

---

## Test logins
| Role    | Email                 | Password   | Notes |
|---------|-----------------------|------------|-------|
| Admin   | admin@zapmed.co.za    | (as set)   | Full admin. |
| Doctor  | zander@ten21.co.za    | (as set)   | Verified, has availability. |
| Doctor  | doctor@zapmed.co.za   | (as set)   | Dr Sarah Naidoo. |
| Patient | register a new one    | —          | Or use an existing verified patient. |

> Passwords for the seeded accounts are managed by the team — ask if unsure.

---

## Known limitations during UAT (NOT bugs — external config pending)
These are deliberate pre-launch stubs / missing third-party credentials. Each degrades gracefully (no crash):
- **Payments (#18):** booking marks the consult "paid" WITHOUT taking a real charge. PayFast is in sandbox;
  no money moves. The medication payment record is created as `pending`.
- **Video consults (#17):** starting a video call shows "Video calling is not configured. Please set
  DAILY_API_KEY". Consultations can still be completed without video. Live video needs a Daily.co key.
- **AI Health Assistant (#14):** answers via a built-in keyword fallback (still helpful + links treatments).
  Full conversational AI + admin knowledge-base answers need a real OpenAI API key.
- **Email:** on this machine live Brevo SMTP rejects the IP; for testing, mail is written to the log. All
  emails (verification, prescription-ready) render correctly — they're just logged, not delivered.

---

## FLOW 1 — Patient registration & onboarding
1. Go to `/register`. Enter First Name, Last Name, Email, Password, Confirm Password → **Register**.
2. You are taken to **Verify Email**. (In production: click the link in the verification email. In test:
   the team marks the account verified.)
3. After verification, visiting the app redirects you to **Complete Your Profile** (`/onboarding`).
4. Complete the 4 steps:
   - **Step 1 Personal Info:** phone, date of birth, gender, full address (street/city/province/postal).
   - **Step 2 Medical History:** emergency contact name/phone/relationship (required); medical aid + health
     info optional.
   - **Step 3 Allergies & Conditions:** optional — add any, or skip.
   - **Step 4 Consent:** tick all four consents → **Complete Registration**.
5. **Expected:** land on the patient **Dashboard** ("Welcome back, <name>", member number shown).

## FLOW 2 — Book a consultation (7-step wizard)
1. Dashboard → **Book a Consultation** (`/book`).
2. **Step 1 Treatment:** pick a category + treatment (e.g. General Health → Acid Reflux) → Continue.
3. **Step 2 Assessment:** answer the treatment questions → Continue. (Answers saved as an Assessment.)
4. **Step 3 Communication preference:** Video / Audio / Text → Continue.
5. **Step 4 Payment:** confirm (sandbox — no real charge, see #18) → calendar unlocks.
6. **Step 5 Date & time:** pick a date within 14 days; choose an available slot. (Slots are 15-min; blocked
   dates and already-booked slots do not appear.)
7. **Step 6 Confirm:** the system auto-assigns the best available doctor → **Confirm Booking**.
8. **Expected (Step 7):** booking success. An Appointment (status pending), a CRM Order, and a CRM funnel
   advance to "Consult Booked" are all created behind the scenes.

## FLOW 3 — Doctor consultation & prescription
1. Log in as the assigned **doctor**. Open the consultation: `/doctor/consultation/{appointmentId}`.
   (Doctors with no availability are redirected to set it first.)
2. (Optional) **Start Video Call** — currently shows the "not configured" notice (#17); skip for UAT.
3. Fill clinical notes — **Presenting complaint, Diagnosis, Treatment plan** are required (ICD-10 optional)
   → **Complete Consultation**. Consultation + appointment move to "completed".
4. Open the **Prescription Builder**: `/doctor/prescription/{consultationId}`.
   - Search the medication catalogue OR **Add custom medication**.
   - Set dosage, frequency, route, duration, quantity, instructions → **Add** (repeat for more items).
   - (AI dosage suggestions need the OpenAI key — #14; enter manually for UAT.)
   - **Sign Prescription.**
5. **Expected:** prescription is signed; a pending medication Payment is created; a "Prescription Ready"
   email is sent (logged in test); CRM funnel advances to "Script Issued".
6. **Prescription PDF:** open `/pdf/prescription/{prescriptionId}` → a valid PDF downloads (prescriber
   details, diagnosis, medication lines).

## FLOW 4 — Admin oversight
1. Log in as **admin** → `/admin`.
2. **Stats** (`/admin/stats`): review Business overview, Acquisition, Subscriptions, Orders, Finance, and the
   CRM funnel. The patient journey above should be reflected (order count, lead in the funnel).
   - Note: some Rand figures read low because seed medication prices are 0 (#16) — data quality, not a bug.
3. Spot-check Users and Doctor Applications screens load and list records.

## FLOW 5 — Public AI Health Assistant
1. Visit any public page (e.g. `/blog` or a treatment page).
2. Click the floating **"Chat with our AI Doctor"** button (bottom-right).
3. Ask a health question (e.g. "I want to lose weight", "hair loss", "acne"). 
4. **Expected:** a helpful reply plus a **"View <Treatment> →"** link to the matching treatment page.
   (Keyword fallback today; richer answers once the OpenAI key is set — #14.)

---

## Result of Naz's pre-UAT regression (2026-09-13)
All five flows executed end-to-end in a real browser — **GREEN**. PHPUnit suite: 405 passed / 1 pre-existing
unrelated failure (an auth-navigation test that expects 200 but gets a 302 redirect — not part of these flows).

### Items for Captain Zan before/at UAT (see leftover-tasks note)
- Provide real **OpenAI key** (#14), **Daily.co key** (#17); decide **payment charge-point** (#18); fix seed
  **medication prices** (#16).
- **CAL-1:** consult duration (30 min) vs 15-min slot granularity — two consults booked 15 min apart with the
  same doctor could overlap. Confirm true consult length.
- **AI-1:** `AiAssistantService::isConfigured()` treats a non-empty-but-invalid key as configured (a file path
  is currently set) — recommend also requiring an `sk-` prefix so it skips straight to the fast fallback.
