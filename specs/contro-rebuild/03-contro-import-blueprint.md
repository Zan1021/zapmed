# Task 8 — Contro Import Blueprint

**Author:** Naz · **Date:** 2026-09-12
**Principle:** Contro is the **source of truth**. Before ZapMed goes live we import all Contro production
data into the owned system. Map **ours → theirs**. This blueprint is **API-complete now** (from the
Contro DTOs/schemas) and becomes **DB-complete** when Craig purchases the Contro DB dump (the API exposes
no clinical notes).

> Reuses the reconcile semantics proven in Mark's backend: idempotent upsert keyed on
> `(upstream_source, upstream_id)`, stub-then-fill principals, fire domain events, derive prescription
> status from linked order state. Their method names (`upsertUpstream*`, `ensureUpstreamPrincipal`) are
> the reference contract even if we implement in Laravel.

---

## 1. Contro source inventory (7 entity sets)

Retrieved via an OData-ish API: `POST /api/crm/auth/login` (email+password → bearer), then
`GET /api/crm/<set>?$filter=<watermarkField> gt :hwm`, pageSize 100, retries/backoff. Send
`Accept: application/json;IEEE754Compatible=true` so int64 IDs arrive as **strings** (do not parse to
JS/DB integers — keep as text). Optional response encryption (JWE) is **not** live on Contro yet.

| entity_set | path | id field | watermark field (verbatim, inconsistent!) |
|---|---|---|---|
| orders | `/api/crm/orders` | `id` (int64→string) | `dtCreatedModified` |
| order_status_history | `/api/crm/order-status-history` | `id` | `changedAt` |
| patients | `/api/crm/patients` | **`userHash`** (only key; int64 id NOT exposed) | **`lastModifiedDT`** (upper T) |
| payments | `/api/crm/payments` | `id` | `dtCreatedModified` |
| prescriptions | `/api/crm/prescriptions` | `id` | **`lastModifiedDt`** (lower t) |
| products | `/api/crm/products` | `id` | `dtCreatedModified` |
| coupons | `/api/crm/coupons` | `id` | `lastModifiedDt` |

> The watermark casing genuinely differs per entity (`lastModifiedDT` vs `lastModifiedDt`). Configure it
> per entity; do not assume one field name.

### Field-level DTOs (names verbatim, casing preserved)

- **PatientDto:** `userHash`, `email`, `firstName`, `lastName`, `phone`, `paymentType`,
  `medicalAidProvider/Number/Plan`, `dateOfBirth`, `deliveryAddress{addressLine1/2, suburb, city,
  province, postalCode, country, deliveryNotes}`, `createDT`, `lastModifiedDT`, `lastLogin`.
- **OrderListDto:** `id`, `orderNumber`, `patientUserHash`, `status`, `statusChangedAt`, `isSubscription`,
  `serviceCategory`, `paymentType`, `orderDate`, `dtCreatedModified`.
  **OrderDetailDto adds 7** (fetched per order via `GET /api/crm/orders/{orderNumber}`):
  `assignedDoctorUserHash`, `assignedDoctorName`, `serviceFee`, `medicationFee`, `nextRepeatDate`,
  `cancellationReason`, `pharmacyScriptReference`.
- **PrescriptionDto:** `id`, `orderId`, `orderNumber`, `patientUserHash`, `doctorUserHash`,
  `medications[]{productId, name, unitPrice, quantity, totalPrice, instructions}`, `totalMedicationCost`,
  `serviceFee`, `repeatCycleDays`, `nextRepeatDate`, `deliveryMethod`, `pharmacyScriptReference`,
  `createdDt`, `lastModifiedDt`.
- **PaymentDto:** `id`, `orderId`, `orderNumber`, `patientUserHash`, `amount`, `type`, `method`, `status`,
  `failureReason`, `retryCount`, `isRepeatCharge`, `medicalAidClaimStatus`, `paidAt`, `dtCreatedModified`.
- **ProductDto:** `id`, `productName`, `nappiCode`, `price`, `packQty`, `isMasterBundle`,
  `requiresSubscription`, `isEnabled`, `dtCreatedModified`.
- **CouponDto:** `id`, `code`, `type`, `value`, `expiryDt`, `numberOfUses`, `maxUses`, `isCancelled`,
  `createDt`, `lastModifiedDt`, `usage[]{...}`.

### DB-only fields (arrive with the Contro dump, not the API)

- **Doctor / clinical notes / consultation reviews** — the API exposes **none**. Prescriptions carry
  medication lines + instructions, but no consult notes, no `clinical_review`, no doctor observations.
  These, if they exist at all, live only in Contro's database.
- Any internal Contro int64 patient id (API only gives `userHash`).
- Provisional slots for DB-only fields are marked `⟨dump⟩` in the mapping tables below.

## 2. Field mapping (ours ← theirs), keyed to reconcile methods

Money: Contro sends **doubles in rands**; canonical stores **minor units (cents, integer)**. Convert
`round(rands * 100)` using banker's rounding to match their `money/v1`. Currency = ZAR throughout.

### 2.1 patients → `patient_profile` (`ensureUpstreamPrincipal` + `upsertUpstreamPatient`)

| Contro | Ours | Transform |
|---|---|---|
| userHash | `patient_profile.upstream_id` (source='contro') | store as-is; the crosswalk key |
| email, firstName, lastName, phone | profile contact | phone → E.164 `mobile_msisdn` |
| paymentType | profile.payment_type | passthrough label |
| medicalAidProvider/Number/Plan | profile medical-aid fields | **must-build columns** |
| dateOfBirth | profile.date_of_birth | |
| deliveryAddress{...} | `address` (label='delivery') | geocode later; primary-per-label |
| createDT, lastModifiedDT, lastLogin | profile timestamps + `upstream_synced_at` | preserve originals |

First, `ensureUpstreamPrincipal(userHash, type='patient', mode='stub')` so orders/payments/prescriptions
that reference the patient can land even if the patient page hasn't synced yet; later the patient sync
runs `mode='upsert'` to fill name/identifier.

### 2.2 products → `catalog_item` (`upsertUpstreamProduct`)

`id→upstream_id`, `productName→name`, `nappiCode→nappi`, `price→price (minor, time-versioned)`,
`packQty`, `isMasterBundle`, `requiresSubscription`, `isEnabled→status active/retired`.

### 2.3 coupons → `catalog_coupon` (+ `catalog_coupon_usage`) (`upsertUpstreamCoupon`)

`id→upstream_id`, `code`, `type`, `value`, `expiryDt`, `numberOfUses/maxUses`, `isCancelled`.
`usage[]` → child rows (pre/post value minor, is_redeemed, payment_reference_id, expiry_dt, create_dt).

### 2.4 orders → `orders_order` (`upsertUpstreamOrder`)

`id→upstream_id`, `orderNumber`, `patientUserHash→patient principal (via crosswalk)`, `status` (map to
our 25-status enum — preserve Contro spelling incl. `PendingConsulation`), `isSubscription`,
`serviceCategory` (passthrough label, **not** an FK), `paymentType`, `orderDate`, money snapshot from
detail (`serviceFee→service_fee_minor`, `medicationFee→medication_fee_minor`), `nextRepeatDate`,
`cancellationReason`, `pharmacyScriptReference`, `assignedDoctorUserHash→doctor principal (stub)`.

### 2.5 order_status_history → `orders_order_status_history` (`upsertUpstreamStatusHistory`)

`id→upstream_id`, `orderNumber→order`, `status→to_status`, `changedAt`. Derive `from_status` from the
prior history row per order (ordered by `changedAt`). **Validate each transition** against the seeded
rules via `orders_is_transition_allowed()`; on failure **do not reject** — land the row and **quarantine
+ flag** the anomaly (Contro data may predate the current rules). History is immutable.

### 2.6 payments → `payments_payment` (`upsertUpstreamPayment`)

`id→upstream_id`, `orderNumber→order`, `amount→amount_minor` (allow **≥ 0**; Contro sends zero-amount
pending rows), `type→payment_type`, `method`, `status`, `failureReason`, `retryCount`, `isRepeatCharge`,
`medicalAidClaimStatus`, `paidAt`. **Do not** create `payment_attempt`/`refund` side-effects on import.
Resolve **PayFast vs peach** before mapping `provider` (see doc 04 Q1).

### 2.7 prescriptions → `clinical_prescription` (+ items) (`upsertUpstreamPrescription`)

`id→upstream_id`, `orderNumber→order`, `patientUserHash`/`doctorUserHash→principals (stub)`,
`totalMedicationCost→total_medication_cost_minor`, `serviceFee→service_fee_minor`, `repeatCycleDays`,
`nextRepeatDate`, `deliveryMethod`, `pharmacyScriptReference`, `createdDt/lastModifiedDt`.
`medications[]` → `clinical_prescription_item` (`productId→catalog crosswalk`, `name`, `unitPrice→
unit_price_minor`, `quantity`, `totalPrice→total_price_minor`, `instructions→sig`).
Derive `status` from the linked order state (issued/fulfilled/cancelled), do not invent it.
`⟨dump⟩` doctor notes / review outcomes → `clinical_review` + `clinical_note(is_internal=true)` **only
when the DB dump arrives**.

## 3. Import architecture

```
Contro API ──auth+watermark pull──▶  [STAGING]  ingested_row(entity_set, upstream_id, payload jsonb,
                                                 upstream_updated_at, pulled_at, run_id)   PK(entity_set,upstream_id)
                                     sync_state(entity_set, last_high_watermark)
                                     sync_run(run audit)
                                              │  (raw, newer-wins upsert — no business logic yet)
                                              ▼
                              [RECONCILE]  idempotent upsert into canonical tables via the
                                           upsertUpstream* semantics, in dependency order:
                                           1 patients (ensure principals: patients + doctors as stubs)
                                           2 products → 3 coupons(+usage)
                                           4 orders → 5 order_status_history
                                           6 payments
                                           7 prescriptions(+items)
                                              │
                                              ▼
                              [CROSSWALK]  every canonical row carries upstream_id/upstream_source/
                                           upstream_synced_at + UNIQUE(upstream_source, upstream_id)
```

**Rules:**

1. **Staging-first ELT.** Always land raw decrypted JSON first (`newer-wins` on watermark). Reconcile is a
   separate, replayable step reading from staging — never straight API→canonical.
2. **Idempotent.** Reconcile is upsert-only on `(upstream_source='contro', upstream_id)`. Re-running a run
   changes nothing. Safe to re-run after partial failure.
3. **Dependency order** (above). Use **stub principals** so a referenced patient/doctor that hasn't synced
   yet doesn't block orders/payments; fill them when their own entity syncs.
4. **userHash crosswalk.** Patients keyed only on `userHash`. Never guess identity from email/name —
   if a referenced userHash has no patient row yet, create a stub principal; if a patient can't be matched
   at all, **quarantine** the record, don't merge.
5. **Quarantine, don't drop.** Anomalies (disallowed transitions, unmatched refs, schema drift) go to a
   quarantine table with the raw payload + reason for manual review. Nothing is silently discarded.
6. **NO SIDE-EFFECTS.** Import must not: send emails/SMS/WhatsApp, charge or refund payments, submit
   scripts to RxHub, create prescriptions in a doctor's live queue, or fire outbound webhooks. Domain
   events may be emitted for audit lineage **only** if their handlers are import-safe (no external calls);
   otherwise suppress event dispatch during import. Preserve original dates/authors; never bulk-publish.
7. **Money + types.** int64 ids as strings; money doubles→minor units (banker's rounding); allow
   zero-amount payments; preserve Contro status spelling.

## 4. Cutover approach

1. **Backfill run:** full pull (watermark from epoch) → staging → reconcile. Measure counts per entity.
2. **Reconcile-verify:** row-count + spot-check parity report (Contro vs canonical) per entity; review
   the quarantine queue with Craig/Dave.
3. **Delta runs:** incremental watermark pulls on a schedule while Contro is still live.
4. **Freeze + final delta:** at cutover, freeze Contro writes, run a final delta, verify, then flip
   ZapMed to system-of-record. Keep Contro read-only as a fallback for a defined window.

## 5. Dependencies / blockers (owned by Craig)

1. **Contro DB dump** — the *only* source for doctor/clinical notes + any DB-only fields.
   **Confirmed coming — expected in a few days** (Captain Zan, 2026-09-13). Until it lands the blueprint
   is API-complete; clinical-notes mapping stays `⟨dump⟩`.
2. **Contro service-account creds + prod HTTPS base URL** (Swagger lists `http://`; we need https).
3. **Confirm the Contro login response shape** (token field name) and whether JWE envelope will be enabled
   (if so we need the keypair).
4. **PayFast vs peach** gateway confirmation (doc 04 Q1) before payment provider mapping.
5. **RxHub API docs** — only needed for live outbound fulfilment, not for the historical import.

## 6. What we build on our side to execute this (ties to doc 02 backlog)

Minimum to run the import: `upstream_sync` staging + reconciler, the `Order` aggregate + status history +
transition rules, `catalog` (products/coupons/usage), `payments` extensions, `patient_profile` extensions,
`clinical_prescription` extensions, and `upstream_id` crosswalk columns on all recipient tables. Items 1–8
of the doc 02 backlog, in that order.
