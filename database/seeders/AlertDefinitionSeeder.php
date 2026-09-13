<?php

namespace Database\Seeders;

use App\Models\AlertDefinition;
use Illuminate\Database\Seeder;

/**
 * Seeds the alert definition catalogue — verbatim parity with Mark's alerts module seed
 * (alerts/migrations/0001_init.up.sql + 0002_pending_booking + 0003_abandoned_carts).
 *
 * Idempotent: upserts by code, so re-running never duplicates and preserves any runtime threshold edits
 * only where we don't overwrite (we DO reset name/description/severity to the canonical catalogue).
 */
class AlertDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['orders.stale', 'Order stale at stage', 'An order has not progressed past its current status within the configured threshold.', 'warning', 'orders', ['stale_hours' => 24]],
            ['orders.stale_critical', 'Order critically stale', 'An order has been stale for more than the critical threshold.', 'critical', 'orders', ['stale_hours' => 72]],
            ['orders.pending_booking', 'Order awaiting booking', 'An order has been in PendingBooking status for longer than the configured threshold — patient has not yet booked a consultation.', 'warning', 'orders', ['stale_hours' => 48]],
            ['orders.abandoned_carts', 'Abandoned carts (PendingPayment)', 'Aggregate of orders left unpaid at PendingPayment past the threshold — a conversion problem, rolled up into a single alert rather than one-per-order.', 'informational', 'orders', ['stale_hours' => 24]],
            ['payments.failed', 'Payment failed', 'A payment attempt failed.', 'warning', 'payments', []],
            ['payments.repeat_failed', 'Repeat payment failure', 'Multiple consecutive payment failures on the same order.', 'critical', 'payments', ['min_failures' => 2]],
            ['consults.no_show', 'Consult no-show', 'Patient did not attend the booked consultation.', 'warning', 'consultations', []],
            ['consults.awaiting_info', 'Consult awaiting information', 'Doctor has requested information from the patient.', 'informational', 'consultations', ['stale_hours' => 48]],
            ['subs.churn_risk', 'Subscription churn risk', 'Subscriber due for renewal in next N days with no auto-renewal confirmed.', 'warning', 'subscriptions', ['days_ahead' => 7]],
            ['crm.cold_signup', 'Cold sign-up', 'Sign-up has not progressed past intake within the dropoff threshold.', 'informational', 'crm', ['stale_days' => 7]],
        ];

        foreach ($definitions as [$code, $name, $description, $severity, $module, $threshold]) {
            AlertDefinition::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'default_severity' => $severity,
                    'source_module' => $module,
                    'threshold_config' => $threshold,
                    'is_active' => true,
                    'auto_resolve' => true,
                ]
            );
        }
    }
}
