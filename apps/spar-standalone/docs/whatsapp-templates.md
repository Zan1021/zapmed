# SPAR WhatsApp Message Templates (Meta submission drafts)

**Purpose:** these are the pre-approved templates the SPAR standalone app sends to patients.
Because patients never message us first, **every message we send is proactive** and therefore
**must** use a Meta-approved template (free-form text is only allowed inside a 24h reply window,
which won't normally exist for us).

## Submission settings (apply to ALL four)
- **Category: `Utility`** — NOT Marketing. These are transactional service messages (prescription
  status, renewals). Utility approves faster, is cheaper, and is free when sent inside an open
  24h session window.
- **Language:** English (`en`). (Add `af` / other SA languages later as separate template locales.)
- **Sender:** WABA "Zapmed", number +27 68 929 2616.
- Submit in **WhatsApp Manager → Account tools → (Templates)** → Create template.
- Variables are positional: `{{1}}`, `{{2}}`… Provide sample values when submitting or Meta rejects.
- Each has a **URL button** carrying the patient's signed tracker link.

> After each template is APPROVED, copy its exact NAME into the standalone `.env`:
> `SPAR_WA_TPL_ONBOARDING`, `SPAR_WA_TPL_MONTHLY`, `SPAR_WA_TPL_RENEWAL`, `SPAR_WA_TPL_MISSED`.
> The code maps payload keys → these names via `config('spar.whatsapp.templates.*')`.

---

## 1. Onboarding / consent  —  key `onboarding_consent`  →  `SPAR_WA_TPL_ONBOARDING`
Sent once, when a patient is first added, to invite them to view their meds and give consent.

**Suggested template name:** `spar_onboarding_consent`

**Body:**
```
Hi {{1}}, this is SPAR Pharmacy. You can now view and manage your chronic medication online.
Tap below to see your prescription details and confirm you're happy for us to keep you updated.
```
- `{{1}}` = patient first name (sample: `Michelle`)
- **Button (URL, "View my medication"):** the tracker link.
- Footer (optional): `Reply STOP to opt out at any time.`

---

## 2. Monthly collection reminder  —  key `monthly_collection`  →  `SPAR_WA_TPL_MONTHLY`
Sent when a repeat is due for collection/delivery.

**Suggested template name:** `spar_monthly_collection`

**Body:**
```
Hi {{1}}, your repeat medication at SPAR Pharmacy is due on {{2}}.
Tap below to confirm collection or delivery.
```
- `{{1}}` = first name (sample: `Michelle`)
- `{{2}}` = due date (sample: `15 September 2026`)
- **Button (URL, "Manage my repeat"):** the tracker link.

---

## 3. Renewal due  —  key `renewal_due`  →  `SPAR_WA_TPL_RENEWAL`
Sent when the script is on its final repeat and needs a new prescription.

**Suggested template name:** `spar_renewal_due`

**Body:**
```
Hi {{1}}, your chronic script at SPAR Pharmacy has reached its final repeat and needs to be renewed.
Tap below to see your options for getting a new prescription.
```
- `{{1}}` = first name (sample: `Michelle`)
- **Button (URL, "Renew my prescription"):** the tracker link.

---

## 4. Missed renewal follow-up  —  key `missed_renewal`  →  `SPAR_WA_TPL_MISSED`
Sent as a gentle follow-up when a renewal wasn't actioned.

**Suggested template name:** `spar_missed_renewal`

**Body:**
```
Hi {{1}}, we noticed your chronic medication at SPAR Pharmacy hasn't been renewed yet.
Staying on your treatment matters — tap below to renew or chat to your pharmacy.
```
- `{{1}}` = first name (sample: `Michelle`)
- **Button (URL, "Renew now"):** the tracker link.

---

## Notes for the developer wiring
- The channel builds the Cloud API payload in `WhatsAppChannel::templateMessage()`:
  body `{{n}}` params come from `payload['vars']` (ordered); the link is attached as a
  URL button parameter at `index 0`. Keep template button order in sync (link = first button).
- Until templates are approved, `SPAR_WHATSAPP_DRIVER=log` records the exact payload without sending.
  A proactive send with NO approved template name returns false → dispatcher falls back to SMS.
- Go live: set `SPAR_WHATSAPP_DRIVER=cloud_api`, fill `SPAR_WHATSAPP_PHONE_NUMBER_ID` + token,
  and the four `SPAR_WA_TPL_*` names. No code change.
