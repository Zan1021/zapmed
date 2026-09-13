# Task 11 — Live Write-Path Adoption: Cutover Plan & Task List

**Status:** PLAN — REVISED for PRE-LAUNCH (Captain Zan approved simplified plan, 2026-09-15). The phased
shadow rollout below is SUPERSEDED by the simplified build in §8. Kept as the future-live-traffic playbook.
**Author:** Naz · **Date:** 2026-09-15 (rev 2) · **Design:** `09-live-writepath-design.md`

Guiding rule: **staging-first, feature-flagged, phased, reversible.** Never big-bang a live payment path.

---

## 1. Feature flags (config/commerce.php — new, additive)

```
commerce.live_writepath.mirror_orders   (bool, default FALSE)  // SHADOW mirror on
commerce.live_writepath.finance_authoritative (bool, default FALSE)
commerce.live_writepath.flow_authoritative     (bool, default FALSE)
```

All default OFF → merging the code changes NOTHING in production until a flag is deliberately flipped.

## 2. Phases & go/no-go gates

### Phase 0 — Scaffolding (zero runtime effect)
- Build `LiveOrderBridge` (all methods no-op while flags off) + additive `orders.source_ref` migration.
- Wire the 3 call sites (`confirmBooking`, `subscribe`, `handleCompletePayment`).
- **Gate:** full suite green; with flags OFF, a booking/payment/subscribe test proves ZERO behavioural change.

### Phase 1 — SHADOW on staging (`zapdev.co.za`, PayFast sandbox)
- Flip `mirror_orders` ON in staging only.
- Drive real sandbox flows: book → pay (ITN) → subscribe. Assert new `Order` + `finance_revenue_entries`
  mirror the legacy `Payment`/`Appointment` correctly (a **parity assertion** script).
- Confirm mirror failures are swallowed + audited, never surfaced to the user (inject a fault, verify checkout still succeeds).
- **Gate:** ≥N sandbox transactions with 100% mirror parity, 0 customer-facing errors. Captain Zan reviews the parity report.

### Phase 2 — SHADOW in production
- Flip `mirror_orders` ON in prod. Monitor for a defined soak period (e.g. 1 week).
- Daily parity check: every completed live `Payment` has a matching `Order` + revenue entry.
- **Gate:** soak clean, parity 100%, no error-log noise from the bridge.

### Phase 3 — Finance authoritative
- Flip `finance_authoritative` ON. Ops dashboards/reporting now read the new finance ledger.
- Old rows still drive fulfilment → still low risk (reporting only).
- **Gate:** finance figures reconcile against the legacy source for the soak window.

### Phase 4 — Flow authoritative (the real cutover)
- Flip `flow_authoritative` ON. New `Order` + `OrderStatusMachine` drive fulfilment transitions;
  `handleCompletePayment` routes state through the aggregate.
- Staging-first (repeat Phase-1-style validation), then prod, ideally low-traffic window.
- **Gate:** staging validation + explicit Captain Zan go. Rollback = flip flag OFF.

## 3. Rollback

Every phase reverts by flipping its flag back to FALSE — no redeploy. Because SHADOW never removes or
mutates legacy writes, the legacy path remains fully functional at every phase. Worst case at Phase 4 is a
flag flip back to Phase 3 behaviour.

## 4. Test strategy

- **Flags-off regression:** existing checkout/booking/subscription tests must pass unchanged (proves additive).
- **Shadow parity tests:** for each flow, after a simulated ITN, assert Order + finance entry match the Payment.
- **Fault-injection test:** force `LiveOrderBridge` to throw; assert the customer transaction still completes.
- **Idempotency test:** replay the same ITN twice; assert no duplicate Order/revenue rows.
- **No-charge test:** assert the bridge never calls PayFastService to initiate a charge.

## 5. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Mirror bug breaks live checkout | try/catch swallow + audit; flags-off default; fault-injection test |
| ITN replay double-writes | idempotent recordCashFromPayment + source_ref unique upsert |
| Consult PayFast step is stubbed (§2 design) | verify FIRST; if stubbed, separate bug ticket — do not fix under cover of refactor |
| Divergence between old/new truth | daily parity check job during soak; finance-authoritative only after parity proven |
| Accidental real charge in testing | staging uses PayFast SANDBOX creds only |

## 6. Implementation task list (FUTURE build — do NOT start until sign-off)

1. `config/commerce.php` with the three flags (all default FALSE).
2. Additive migration: `orders.source_ref` (nullable, unique per source type) + index.
3. `App\Services\Commerce\LiveOrderBridge` — 3 flag-gated, try/catch-wrapped methods; unit tests incl. fault injection.
4. Wire bridge into `BookAppointment::confirmBooking`, `MySubscription::subscribe`, `PaymentController::handleCompletePayment` (single call each, behind flags).
5. Shadow parity assertion command: `commerce:parity-check` (live Payment ↔ Order/finance).
6. Phase-1 staging validation on zapdev.co.za (sandbox) + parity report for review.
7. Phase-2 prod soak + daily parity monitoring.
8. Phase-3 finance-authoritative flip + reconciliation gate.
9. Phase-4 flow-authoritative: route fulfilment through OrderStatusMachine; staging-first; Captain Zan go.
10. Decommission plan for any now-redundant legacy write (only after Phase 4 proven — likely a *future* task, not this one).

## 7. Sign-off gates (Captain Zan)

- [ ] Approve this design + phased plan.
- [ ] Answer the 4 open questions in design §7 (consult step status, sandbox creds, source_ref migration, traffic timing).
- [ ] Explicit go for EACH of: Phase 1 (staging shadow), Phase 2 (prod shadow), Phase 3 (finance auth), Phase 4 (flow auth).

Nothing in Phases 1–4 proceeds without the corresponding tick. Phase 0 (flags-off scaffolding) is safe to
build on approval of this plan, since it cannot change production behaviour.


---

## 8. REVISED BUILD PLAN (rev 2) — pre-launch, direct integration

Captain Zan approved the simplified plan (no live traffic → no shadow phasing). This is the ACTUAL build.
All in PayFast **sandbox**. Additive to existing behaviour (nothing working is removed).

### Build steps
1. **Migration** — additive `orders.source_ref` (nullable, string) + unique index on
   `(source_type, source_ref)` so an Order links back to its originating Payment/Appointment/Subscription
   and a duplicate ITN can't double-create.
2. **`App\Services\Commerce\LiveOrderBridge`** — creates/updates the `Order` aggregate + a
   `FinanceService::recordCashFromPayment` entry from the live flow. Idempotent (source_ref upsert).
   No feature flags — it runs as part of the normal flow.
3. **Wire the three call sites:**
   - `BookAppointment::confirmBooking` → create a consult `Order` (status per OrderStatusMachine).
   - `MySubscription::subscribe` → create a subscription `Order`.
   - `PaymentController::handleCompletePayment` → on completed ITN, upsert the Order to paid + record cash.
   Preserve every existing write + side-effect (pharmacy dispatch, commissions, payouts, emails).
4. **Tests (sandbox / feature):** booking creates an Order; completed ITN marks it paid + records finance;
   duplicate ITN is idempotent (no double Order/revenue); bridge never calls PayFast to initiate a charge;
   existing checkout/booking/subscription tests still pass unchanged.
5. **Launch-day doc note:** production sets `PAYFAST_TEST_MODE=false`; sandbox stays default true.

### Explicitly still deferred / out of scope
- Pharmacy/RxHub wiring → parked task `11-pharmacy-rxhub-integration-TASK.md`.
- Verifying/fixing the possibly-stubbed consult→PayFast step → Captain Zan says nothing takes real payment
  yet, so this is expected pre-launch state, NOT a bug to fix under this task.
- Task 12 (full backfill) → still blocked on Craig's dump + creds.

### Rollback
Additive: the new Order aggregate sits alongside existing rows. If anything misbehaves in sandbox, the
existing Payment/Appointment/Subscription flow is untouched and still authoritative.