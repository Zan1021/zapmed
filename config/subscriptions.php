<?php

/**
 * Subscription repeat-lifecycle configuration — parity with Mark's SubscriptionsConfig (Dave's spec).
 * Values as DATA so they're tunable without code changes.
 */
return [
    // Days from origin order Completed → follow-up due. Dave's spec: 180.
    'followup_after_days' => 180,

    // Consecutive cycle failures before auto-cancel. Dave's spec: 3 (the "3-strike" rule).
    'max_consecutive_failures' => 3,

    // Default renewal interval (days) when the plan/caller doesn't supply one.
    'default_interval_days' => 30,

    // Cycle runner look-ahead: only schedule/run cycles due within this window.
    'cycle_runner_lookback_hours' => 24,

    /*
     * Craig's build guide (19 Nov 2026), section 3.2 and the order status table.
     *
     * - Charges and retries happen at 09:30 South African time ("Typical repeat is at 9:30 AM").
     * - A failed repeat is retried at 09:30 on three successive days. The third failure PAUSES the order and blocks
     *   charges until the patient responds. It never cancels (set after_max_failures to 'cancel' for the old rule).
     * - A pack first repeats (pack days - buffer) days after the prescription, so a 28 day pack first repeats on
     *   day 23 (5 day delivery buffer). Later repeats are every pack days.
     * - A paused order can be continued within 90 days. Continuing starts the repeat cycle again like a first
     *   repeat (ASSUMPTION, to confirm with Craig); the old cycles stay in history.
     * This service only RECORDS outcomes and schedules bookkeeping rows. It never charges anything.
     */
    'charge_time' => '09:30',
    'charge_timezone' => 'Africa/Johannesburg',
    'retry_after_days' => 1,
    'after_max_failures' => 'pause',          // pause | cancel
    'first_repeat_buffer_days' => 5,
    'pause_resume_window_days' => 90,
    'initiators' => ['patient', 'operations', 'system'],
    'pause_reason_three_failures' => 'Three failed repeat payments in a row. Waiting for the patient to respond.',

    /*
     * Charging repeat orders ourselves (PayFast "tokenisation": the patient pays once, we charge the saved token on
     * each due repeat with PayFast's ad hoc API). OFF unless SUBSCRIPTIONS_CHARGE_ENABLED=true, and only for
     * subscriptions whose charge_mode is 'adhoc'. Memberships stay 'payfast_recurring' (PayFast bills them itself) and
     * are never charged by us. Needs "tokenisation" and "ad hoc" switched on in the PayFast merchant account.
     * With payfast.test_mode true every call goes to the PayFast sandbox (?testing=true).
     */
    // Repeat orders that start from a signed prescription with repeats (medication refills charged on a saved card).
    'repeat_orders' => [
        // The FIRST medication payment saves the card (PayFast tokenisation). Leave OFF until PayFast has enabled tokenisation on
        // the merchant account: with it off the normal payment form is unchanged and no repeat order starts by itself.
        'tokenise_first_payment' => (bool) env('SUBSCRIPTIONS_TOKENISE_FIRST_PAYMENT', false),
        'default_pack_days' => 28,       // when no medication line says how long it lasts
    ],
    'charging' => [
        'enabled' => (bool) env('SUBSCRIPTIONS_CHARGE_ENABLED', false),
        'timeout_seconds' => 20,
        'stale_placed_days' => 3,    // a charge with no PayFast confirmation after this long is reported for manual review
    ],
];
