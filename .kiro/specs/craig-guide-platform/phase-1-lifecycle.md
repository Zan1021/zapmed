# Craig's build guide, platform phase 1: money and lifecycle rules

**Date:** 2026-11-24 - **Owner:** Naz for Captain Zan - **Status:** rules built and tested, deployed to zapdev (staging only). Source: Craig's System Build Guide (19 Nov 2026), sections 3.2, 3.3 and the order status table (guide text copy `C:\workspace\zapmed-guide\guide.md`). Gap analysis: vault `2026-11-23_mem_craig-build-guide-gap-analysis-and-plan.md`.

## What was built (all in `SubscriptionLifecycle`, `config/subscriptions.php`, `config/orders.php`, `OrderStatusMachine`)
The lifecycle service only RECORDS outcomes and schedules bookkeeping rows. It never charges anything (standing import-safety rule).

- [x] **Retries at 09:30 on three successive days.** A failed repeat schedules the next attempt for tomorrow 09:30 South African time (metadata `retry_of`, `attempt`). Charge time and zone are config (`charge_time`, `charge_timezone`, `retry_after_days`).
- [x] **Third failure PAUSES, never cancels.** Pause event with initiator `system` and the reason "Three failed repeat payments..."; no fourth attempt; the order waits for the patient (continue = resume). Old rule is one setting away (`after_max_failures = cancel`).
- [x] **Runner at 09:30 SA** (was 03:00 server time): `subscriptions:run-due` also retries `payment_failed` orders when their retry is due; paused, cancelled and imported orders are skipped.
- [x] **First-repeat buffer.** `scheduleFirstRepeat($sub, $prescribedAt, $packDays)`: first repeat (pack - 5) days after the prescription at 09:30, so a 28 day pack repeats on day 23, then every pack days. Pack length is remembered on the subscription (`repeat_cycle_days`).
- [x] **Pause and cancel with reason + initiator.** Patient must give a reason. Initiator `patient | operations | system` is stored (`subscription_pause_events.initiator`, `subscriptions.cancelled_initiator`, `cancelled_by`). Uncharged cycles are cancelled but kept in history. `Subscription::cancel()` (membership) records the patient as initiator.
- [x] **Continue within 90 days.** `resume()` refuses after 90 days unless Operations passes the override. Continuing clears the failed-payment strikes and starts the repeat cycle again like a first repeat; old cycles stay in history.
- [x] **Four new order statuses** (29 in total): `PendingBloodTests`, `PendingReviewFeedback`, `PendingFollowUpRequest`, `PendingFollowUpProfileBook`, with transitions (doctor requests blood tests or a follow-up, patient books it, doctor reviews and submits) and a place on the order board.
- [x] **Repeats blocked while a follow-up is pending.** `orders.repeat_blocking_statuses` (paused, cancelled, three failures, and all four follow-up statuses). The runner leaves the repeat scheduled and checks again next run. Blood tests and review/feedback do NOT block (the guide does not say they do).
- [x] **Second no-show cancels.** `OrderStatusMachine::statusAfterNoShow($priorNoShows)` returns NoShow once, then Cancelled (`orders.no_show.max_reschedules = 1`); transition `NoShow -> Cancelled` added.
- [x] `ThreeRepeatFailures -> Paused` added; `ThreeRepeatFailures -> Cancelled` kept so imported Contro history still validates.

Tests: `SubscriptionRulesTest` (25), updated `SubscriptionLifecycleTest`, `OrderStateMachineTest`, `OrderBoardTest`, `StatsOrdersTest`.

## NOT built (needs the live engine or a decision)
- **No live charge engine calls this yet.** Nothing in the app currently triggers the repeat charge, calls `recordSuccess` / `recordFailure` from PayFast results for medication repeats, or applies the order status changes (the state machine is a rules checker, no service moves orders). So the rules are ready and tested, but they only bite once that engine is wired. The membership (R450 etc.) still uses PayFast's own recurring billing and the old failure email.
- Calling `scheduleFirstRepeat` when a prescription is signed (needs the "order advances after Rx signed" fix, still open).
- Applying `statusAfterNoShow` where an appointment is marked missed.
- Patient-facing pause / cancel / continue screens (phase 3, My Orders tab) and the messages for the three failed payments.
- Retiring PaymentFailed / PaymentReceived / AwaitingInformation (Craig's open question 13).

## Assumptions to confirm with Craig
1. **Continue after a pause** restarts the cycle like a first repeat (pack - 5 days from the day it is continued). The guide says "as specified" without the detail.
2. **Retry timing:** the first retry is the day after the failure at 09:30, then the day after that; the third failure pauses.
3. **Blood tests / review-feedback statuses** do not block repeats.
4. **Cancel always needs a reason**, for any initiator. Pause needs a reason from a patient only.
