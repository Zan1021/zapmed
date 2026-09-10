# SPAR WhatsApp — Meta Cloud API setup & templates (Phase 6)

Provider decision (6.1): **Meta Cloud API direct** (no BSP). The `WhatsAppChannel`
(`packages/spar-core/src/Services/Channels/WhatsAppChannel.php`) is provider-agnostic behind the
`MessagingChannel` contract and ships with a **`log` driver** so the entire flow is testable and
demoable **before** any Meta credentials exist. Flip `SPAR_WHATSAPP_DRIVER=cloud_api` once the WABA is live.

---

## 6.2 — Meta onboarding runbook (Captain Zan's real-world actions)

These cannot be done from the shell. One action at a time:

1. **Meta Business Manager** → create/verify the Business (business.facebook.com). Business verification
   may require company docs; it can take a few days.
2. **Add WhatsApp** product → create a **WhatsApp Business Account (WABA)**.
3. **Add a phone number** for WhatsApp (must not already be on a personal WhatsApp; a fresh number is
   cleanest). Verify it. Note the **Phone Number ID** (not the phone number itself).
4. **System user + permanent token:** Business Settings → Users → System Users → create a system user,
   assign the WABA, generate a **permanent access token** with `whatsapp_business_messaging` +
   `whatsapp_business_management` scopes.
5. Give me (or drop into env, never into chat) the **Phone Number ID** and **token** — I reference them
   by name only; treat the token as a secret and rotate if it ever appears in chat.
6. Submit the templates below for approval (6.3). Approved template **names** go into the env keys.

Set in the deployed env once you have them:

```
SPAR_WHATSAPP_ENABLED=true
SPAR_WHATSAPP_DRIVER=cloud_api
SPAR_WHATSAPP_PHONE_NUMBER_ID=<from step 3>
SPAR_WHATSAPP_TOKEN=<from step 4>          # secret
SPAR_WA_TPL_ONBOARDING=<approved template name>
SPAR_WA_TPL_MONTHLY=<approved template name>
SPAR_WA_TPL_RENEWAL=<approved template name>
SPAR_WA_TPL_MISSED=<approved template name>
```

Until then, leave `SPAR_WHATSAPP_DRIVER=log` (default) — sends are recorded to the `spar_audit`
channel, nothing leaves the server, and SMS remains the working fallback.

---

## 6.3 — Template set for Meta approval

Proactive (outside the 24h customer-service window) messages **must** use pre-approved templates.
Author these in Meta Business Manager → WhatsApp Manager → Message Templates. Category **UTILITY**
(transactional), language `en`. `{{n}}` are body variables (passed via the payload `vars` array, in
order); the URL button carries the signed tracker link.

### onboarding_consent  (key: `onboarding_consent`)
> Hi {{1}}, {{2}} can now manage your chronic medication on WhatsApp. Tap below to review and give
> consent — you're in control and can opt out any time.
> **Button (URL):** View & consent

### monthly_collection  (key: `monthly_collection`)
> Hi {{1}}, your chronic medication at {{2}} is due for collection by {{3}}. Tap below to arrange
> collection or delivery.
> **Button (URL):** Manage my order

### renewal_due  (key: `renewal_due`)
> Hi {{1}}, your prescription at {{2}} is due for renewal. Tap below to see your options and continue
> your medication without interruption.
> **Button (URL):** View renewal options

### missed_renewal  (key: `missed_renewal`)
> Hi {{1}}, we noticed your prescription at {{2}} hasn't been renewed yet. Tap below so we can help you
> stay on your medication.
> **Button (URL):** Renew now

> Standalone renewal copy never mentions a ZapMed online doctor (NullTelehealthBridge). The renewal
> message body is still produced by `SparReminderService`/`TelehealthBridge`; the template wording above
> is host-neutral.

---

## 6.4 / 6.5 — What's implemented

- `WhatsAppChannel` (`key: whatsapp`) registered as a channel factory in **both** hosts
  (`SparServiceProvider` + `SparStandaloneServiceProvider`).
- **Driver switch:** `log` (default, no creds) | `cloud_api` (real Meta).
- **24h session window:** free-form text only inside the window (tracked via patient
  `metadata.wa_last_inbound_at`); outside it, only approved templates send, else the send **defers**
  (returns false) so the dispatcher falls back to SMS. No patient is left unreachable.
- **E.164 normalisation:** local SA numbers (`0XXXXXXXXX`) → `27XXXXXXXXX`.
- Tests: `SparWhatsAppChannelTest` (9 pass) — log driver without creds, cloud_api request shape via
  faked HTTP, API-error fallback, and the window rule. No test ever contacts Meta.

## To make WhatsApp the PRIMARY channel

Set the channel priority (SMS stays as fallback):

```
SPAR_CHANNELS=inapp,whatsapp,email,sms
```

The dispatcher tries channels in this order; `whatsapp` defers gracefully to `sms` whenever it can't
send (disabled, no template, outside window), so this is safe to set even while the driver is still `log`.
