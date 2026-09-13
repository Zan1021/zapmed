# PARKED TASK — Pharmacy / RxHub Integration

**Status:** PARKED for a future session (Captain Zan, 2026-09-15). Not started.
**Why its own task:** distinct from Task 11 (checkout write-path); muddling them invites scope creep.

---

## Summary
Port Craig's inbound RxHub integration into our Laravel app and wire it to the CRM `OrderStatusMachine`.
The inbound side is BUILDABLE NOW from Craig's blueprint. The OUTBOUND "submit prescription to RxHub"
API contract is NOT in Craig's repo → still BLOCKED on Craig for full closure.

## What Craig's repo HAS (blueprint to port — KB 8edda19c)
- `pharmacy/src/application/handle-rxhub-webhook.usecase.ts` — inbound webhook handler. Maps 5 event codes
  (New → PharmacyProcessing, ScriptProcessed → PreparingMedication, ScriptRejected → ClaimRejected,
  ScriptDispatched → Despatched, ScriptDelivered → Delivered), creates delivery + tracking (carrier default
  "courierguy"), handles claim rejection, and DRIVES the order state machine (trigger_type 'rxhub').
- HMAC webhook signature verification: `rxhubWebhookSecret` + timestamp tolerance (default 300s).
- Pharmacy schema: pharmacy_dispatch / pharmacy_delivery / pharmacy_claim / pharmacy_rxhub_event (+ RLS).
- Reusable outbound-webhook infra in the `partners` module (signing, retry w/ backoff, delivery queue).

## What we ALREADY have on our side
- `OrderStatusMachine::statusForRxhubEvent()` (Task 2) — event→status hook, currently UNCONNECTED.
- `PharmacyService::dispatch()` — posts to a generic `/prescriptions` endpoint (not the real RxHub contract).
- `PharmacyDispatchService` — multi-channel (api/email/fax) dispatcher. NOTE: overlaps PharmacyService —
  a pre-existing DUPLICATION to rationalise (flag for cleanup, not mine).
- `PharmacyWebhookController` — exists; needs wiring to the ported inbound handler.

## What's BLOCKED on Craig
- The OUTBOUND RxHub submit API: endpoint URL, auth/credentials, exact request payload for sending a NEW
  prescription to the hub. Craig's code only RECEIVES RxHub webhooks; the submit contract isn't shared.

## Proposed scope when un-parked
1. Port the inbound RxHub webhook handler to Laravel (event mapping + dispatch/delivery/claim writes).
2. Add HMAC signature verification on `PharmacyWebhookController` (rxhub secret + timestamp tolerance).
3. Connect it to `OrderStatusMachine` via the existing `statusForRxhubEvent()` hook (trigger 'rxhub').
4. Rationalise the PharmacyService / PharmacyDispatchService duplication (decision needed).
5. Outbound submit: build behind the graceful-degradation pattern; STUB until Craig sends the API spec.
6. Tests: inbound event → status transition, signature rejection, idempotent event replay.

## Blockers to raise with Craig
- Full outbound RxHub API instructions (endpoint, auth, payload) + the webhook signing secret for inbound.
