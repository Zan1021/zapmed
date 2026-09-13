# Task 11 — Live Write-Path Adoption: Design

**Status:** DESIGN — REVISED for PRE-LAUNCH reality (Captain Zan, 2026-09-15: nothing takes real payment
yet; everything tested in PayFast sandbox). The cautious shadow-mirror phasing below is SUPERSEDED — see
§8. Kept for historical context + the day real traffic exists.
**Author:** Naz · **Date:** 2026-09-15 (rev 2)
**Companion docs:** `10-live-writepath-cutover.md` (rollout plan), `08-crm-build-spec.md` (§2, feature designs).

---

## 1. Problem statement

Tasks 1–9 built a complete, tested CRM/commerce layer — the `Order` aggregate, `OrderStatusMachine`,
`Subscription` lifecycle, `FinanceService`, `AuditTrail`, etc. But it is a **parallel system**: today's
live customer traffic does NOT touch any of it. Task 11 adopts the new write-path for *live* flow so new
orders/payments/consults flow through the new aggregates — **without breaking the running checkout**.

This is the highest-blast-radius task in the project: it modifies code that moves real money.

## 2. What the live path does TODAY (mapped from source, not assumed)

| Flow | Entry point | Writes today | Side-effects |
|---|---|---|---|
| Book consultation | `Livewire/Patient/BookAppointment::confirmBooking()` | `Appointment` (status `pending`, `is_paid=false`); links `Assessment` | none at booking; **NB** a code comment ("For now, we mark as paid") indicates the consult→PayFast step may be partially stubbed — MUST verify before relying on it |
| Pay (consult or medication) | `Http/Controllers/PaymentController::checkout()` → PayFast | shows PayFast form for an existing `Payment` | — |
| **PayFast ITN webhook** | `PaymentController::notify()` → `handleCompletePayment()` | `Payment` → completed/paid_at; `Appointment` → is_paid/confirmed; `Prescription::markPaid` + `PharmacyService::dispatch`; `ReferralService` commission; `PayoutService` payout; 3 emails | **the money-moving heart** |
| Subscribe | `Livewire/Patient/MySubscription::subscribe()` | `Subscription` (status `pending`) → PayFast redirect | `cancelSubscription()` calls PayFast + `Subscription::cancel` |
| Gateway | `Services/PayFastService` | merchant creds `config('payfast')`; `generatePaymentData` / `generateSubscriptionData` / `validateItn` / `getProcessUrl` / `cancelSubscription` | — |

**Key fact:** the live path writes `Payment` + `Appointment` + `Subscription` + `Prescription` **directly**
and NEVER creates an `Order`. The new aggregates are unused in production.

## 3. Design principle — Strangler Fig, not rip-and-replace

We do NOT rewrite the checkout. We wrap the existing write points with a thin seam that ALSO drives the
new aggregates, then progressively let the new side become the source of truth. Existing behaviour
(payments, pharmacy dispatch, commissions, payouts, emails) stays byte-for-byte intact throughout.

Three escalating integration modes, each behind a feature flag:

1. **SHADOW (mirror, read-only truth = old):** on each live write, ALSO create/update the new `Order`
   aggregate + a `FinanceService::recordCashFromPayment` entry. The new rows are *observational*; nothing
   downstream reads them yet. Any error in the mirror is caught + logged, NEVER bubbles to the customer.
2. **AUTHORITATIVE-FINANCE:** the finance ledger + order board are trusted for reporting/ops. Old rows
   still drive fulfilment. Low risk — reporting only.
3. **AUTHORITATIVE-FLOW:** the new `Order` + `OrderStatusMachine` drive fulfilment transitions. This is
   the real cutover and the last, most-guarded phase.

Each mode is independently reversible by flipping a flag back — no redeploy required.

## 4. The seam

Introduce a single integration service so the live controllers gain ONE call, not scattered logic:

```
App\Services\Commerce\LiveOrderBridge
  ├─ onAppointmentBooked(Appointment): ?Order        // SHADOW: mirror a consult order
  ├─ onSubscriptionStarted(Subscription): ?Order     // SHADOW: mirror a subscription order
  └─ onPaymentCompleted(Payment): void               // SHADOW: upsert Order + FinanceService cash entry
```

- Every method is a **no-op unless its feature flag is on** (`config('commerce.live_writepath.*')`).
- Every method is wrapped in try/catch: a mirror failure logs to `AuditTrail` + Log and returns — the
  customer's payment/booking is never affected. (Shadow mode must be incapable of breaking checkout.)
- Called from exactly three existing places: `confirmBooking()`, `MySubscription::subscribe()`, and
  `PaymentController::handleCompletePayment()`.

## 5. Idempotency + safety boundaries

- `FinanceService::recordCashFromPayment()` is already idempotent (firstOrCreate per payment) — safe to
  call from the ITN even on PayFast retries.
- Order mirror keyed on the live `Payment`/`Appointment`/`Subscription` id (a stable `source_ref`) so an
  ITN replay updates, never duplicates.
- **Import boundary preserved:** the Contro reconciler still owns imported history and NEVER charges. The
  bridge only ever fires from genuine live gateway events. Import-safety non-negotiable is untouched.
- **No new gateway calls.** The bridge records outcomes of charges PayFast already made; it never initiates
  a charge. (Same discipline as `SubscriptionLifecycle`/`FinanceService`.)

## 6. Explicitly OUT of scope for this design

- Fixing the possibly-stubbed consult→PayFast step in `BookAppointment` (flagged in §2). If real, that is a
  **separate bug ticket** — Task 11 must not silently "fix" live payment behaviour under cover of a refactor.
  To be confirmed with Captain Zan.
- Changing PayFast credentials, ITN URL, or gateway behaviour.
- Touching the Contro import path.

## 7. Open questions for Captain Zan (must answer before build)

1. Is the consult-booking PayFast step actually live, or stubbed? (Affects whether SHADOW can mirror it.)
2. Confirm staging (`zapdev.co.za`) has PayFast **sandbox** creds so we can drive real ITNs without real money.
3. Acceptable to add `source_ref` columns (nullable) to `orders` for the mirror link? (Additive migration.)
4. Any current live checkout traffic that makes even a zero-risk additive deploy sensitive to timing?


---

## 8. REVISED APPROACH (rev 2) — pre-launch, no live traffic

Captain Zan confirmed: **nothing takes real payment yet; the platform is pre-launch and everything is
tested in PayFast sandbox** (`config/payfast.php` defaults `test_mode=true` → sandbox.payfast.co.za).

The §3–§5 shadow-mirror / production-soak phasing exists to protect **live customers and real money**.
There are none. So that ceremony is overkill. **Direct integration is appropriate here.**

### What changes
- **No feature-flag phasing, no shadow mode, no production soak.** We integrate the new `Order` aggregate
  directly into the checkout flow and make it a first-class record from the start.
- Hook points unchanged: `BookAppointment::confirmBooking`, `MySubscription::subscribe`,
  `PaymentController::handleCompletePayment`.
- The new `Order` + `FinanceService` cash entry are created as part of the normal flow (not a mirror).
- Existing `Payment`/`Appointment`/`Subscription` writes + side-effects (pharmacy dispatch, commissions,
  payouts, emails) are PRESERVED — we ADD the Order aggregate alongside, we don't remove working behaviour.

### What we KEEP from the cautious plan (cheap, saves us at launch)
1. **Idempotency** — a duplicate PayFast ITN must not double-create an Order / revenue entry. Keyed on the
   live `Payment` id via `orders.source_ref` + `FinanceService::recordCashFromPayment` (already idempotent).
   This bites the day you go live, so build it right the first time.
2. **Launch-day checklist item:** production MUST set `PAYFAST_TEST_MODE=false`; staging/local stay sandbox
   (default true). Documented so nobody ships to real money in sandbox or tests against real cards.
3. **No new gateway calls** — still only record outcomes of charges PayFast already made.
4. **Import boundary** — the Contro reconciler still owns imported history and never charges. Untouched.

### Net
Simpler, faster, correct for a pre-launch system. The heavy phasing in §2–§7 is the playbook for IF/WHEN
this ever needs re-doing against live traffic — retained deliberately, not deleted.